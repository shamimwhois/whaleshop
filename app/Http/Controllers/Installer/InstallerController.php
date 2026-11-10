<?php

namespace App\Http\Controllers\Installer;


use App\Http\Controllers\Controller;
use App\Libraries\AppLibrary;
use App\Services\InstallerPermissionCheckerService;
use App\Services\InstallerRequirementsCheckerService;
use App\Services\InstallerService;
use Dipokhalder\EnvEditor\EnvEditor;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class InstallerController extends Controller
{
    public InstallerService $installerService;
    protected InstallerRequirementsCheckerService $installerRequirementsCheckerService;
    protected InstallerPermissionCheckerService $installerPermissionCheckerService;

    public function __construct(InstallerService $installerService, InstallerRequirementsCheckerService $installerRequirementsCheckerService, InstallerPermissionCheckerService $installerPermissionCheckerService)
    {
        $this->installerService                    = $installerService;
        $this->installerRequirementsCheckerService = $installerRequirementsCheckerService;
        $this->installerPermissionCheckerService   = $installerPermissionCheckerService;

        if (file_exists(storage_path('installed'))) {
            Redirect::to(env('APP_URL'))->send();
        }
    }

    public function index(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        return view('installer.welcome');
    }

    public function requirement(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        $phpSupportInfo = $this->installerRequirementsCheckerService->checkPhpVersion(config('installer.core.minPhpVersion'));
        $requirements   = $this->installerRequirementsCheckerService->check(config('installer.requirements'));
        return view('installer.requirement', compact('requirements', 'phpSupportInfo'));
    }

    public function permission(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        $permissions = $this->installerPermissionCheckerService->check(config('installer.permissions'));
        return view('installer.permission', compact('permissions'));
    }

    public function license(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('installer.license');
    }

    public function licenseStore(Request $request)
    {
        $rules     = config('installer.license.form.rules');
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect(route('installer.license'))->withErrors($validator)->withInput();
        }

        try {
            $response = $this->installerService->licenseCodeChecker($request->all());
            if (isset($response->status) && $response->status) {
                $envService = new EnvEditor();
                $envService->addData([
                    'VITE_API_KEY' => $request->license_key,
                ]);
                return redirect(route('installer.site'));
            } else {
                return redirect(route('installer.license'))->withErrors(['global' => $response->message])->withInput();
            }
        } catch (Exception $e) {
            return redirect(route('installer.license'))->withErrors(['global' => $e->getMessage()])->withInput();
        }
    }

    public function site(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        return view('installer.site');
    }

    public function siteStore(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $rules     = config('installer.site.form.rules');
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect(route('installer.site'))->withErrors($validator)->withInput();
        }

        try {
            $this->installerService->siteSetup($request);
            return redirect(route('installer.database'));
        } catch (Exception $e) {
            return redirect(route('installer.site'))->withErrors($e->getMessage())->withInput();
        }
    }

    public function database(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        return view('installer.database');
    }

    public function databaseStore(Request $request)
    {
        $rules     = config('installer.database.form.rules');
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect(route('installer.database'))->withErrors($validator)->withInput();
        }

        $connection = $this->installerService->checkDatabaseConnection($request);
        if ($connection) {
            $dbVersion       = DB::select("SELECT version() as version")[0]->version;
            $platformVersion = DB::connection()->getDriverName();

            if (AppLibrary::lowerWithReplaceToSpace($platformVersion) == 'mysql' && (float)$dbVersion < 8.0) {
                return redirect(route('installer.database'))->withErrors(['global' => trans('installer.database.fail_mysql_version')])->withInput();
            } elseif (AppLibrary::lowerWithReplaceToSpace($platformVersion) == 'mariadb' && (float)$dbVersion < 10.2) {
                return redirect(route('installer.database'))->withErrors(['global' => trans('installer.database.fail_mariadb_version')])->withInput();
            } elseif (AppLibrary::lowerWithReplaceToSpace($platformVersion) == 'postgresql' && (float)$dbVersion < 9.4) {
                return redirect(route('installer.database'))->withErrors(['global' => trans('installer.database.fail_postgresql_version')])->withInput();
            } elseif (AppLibrary::lowerWithReplaceToSpace($platformVersion) == 'sqlserver' && (float)$dbVersion < 2008) {
                return redirect(route('installer.database'))->withErrors(['global' => trans('installer.database.fail_sqlserver_version')])->withInput();
            } elseif (AppLibrary::lowerWithReplaceToSpace($platformVersion) == 'singlestore' && (float)$dbVersion < 8.1) {
                return redirect(route('installer.database'))->withErrors(['global' => trans('installer.database.fail_singlestore_version')])->withInput();
            }

            try {
                $response = $this->installerService->databaseSetup($request);
                if ($response) {
                    return redirect(route('installer.final'));
                }
                return redirect(route('installer.database'))->withErrors(['global' => trans('installer.database.fail_message')])->withInput();
            } catch (Exception $e) {
                return redirect(route('installer.database'))->withErrors(['global' => $e->getMessage()])->withInput();
            }
        }
        return redirect(route('installer.database'))->withErrors(['global' => trans('installer.database.fail_message')])->withInput();
    }

    public function final(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        return view('installer.final');
    }

    public function finalStore(): \Illuminate\Routing\Redirector|\Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse
    {
        try {
            $this->installerService->finalSetup();
            return redirect(env('APP_URL'));
        } catch (Exception $e) {
            return redirect(route('installer.site'))->withErrors(['global' => $e->getMessage()]);
        }
    }

}
