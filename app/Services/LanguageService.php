<?php

namespace App\Services;


use App\Http\Requests\LanguageFileTextGetRequest;
use App\Libraries\AppLibrary;
use Exception;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\LanguageRequest;
use App\Http\Requests\PaginateRequest;
use App\Libraries\QueryExceptionLibrary;
use Dipokhalder\Settings\Facades\Settings;


class LanguageService
{

    protected $languageFilter = [
        'name',
        'code',
        'status',
    ];
    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'desc';

            return Language::where(function ($query) use ($requests) {
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->languageFilter)) {
                        $query->where($key, 'like', '%' . $request . '%');
                    }
                }
            })->orderBy($orderColumn, $orderType)->$method(
                $methodValue
            );
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function store(LanguageRequest $request)
    {
        try {
            if (!file_exists(base_path("resources/js/languages/{$request->code}.json"))) {
                copy(base_path("resources/js/languages/en.json"), base_path("resources/js/languages/{$request->code}.json"));
            }

            if (!file_exists(base_path("lang/{$request->code}"))) {
                mkdir(base_path("lang/{$request->code}"), 0755);
                $files = scandir(base_path("lang/en"));
                if (count($files) > 2) {
                    foreach ($files as $file) {
                        if ($file != '.' && $file != '..') {
                            copy(base_path("lang/en/{$file}"), base_path("lang/{$request->code}/{$file}"));
                        }
                    }
                }
            }

            $language = Language::create($request->validated());
            if ($request->image) {
                $language->addMediaFromRequest('image')->toMediaCollection('language');
            }

            return $language;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(LanguageRequest $request, Language $language): Language
    {
        try {
            $language->update($request->validated());
            if ($request->image) {
                $language->clearMediaCollection('language');
                $language->addMediaFromRequest('image')->toMediaCollection('language');
            }
            return $language;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(Language $language): void
    {
        try {
            if (Settings::group('site')->get("site_default_language") != $language->id) {
                if (!env('DEMO')) {
                    AppLibrary::deleteDir(base_path("lang/{$language->code}"));
                    if (file_exists(base_path("resources/js/languages/{$language->code}.json"))) {
                        unlink(base_path("resources/js/languages/{$language->code}.json"));
                    }
                }
                $language->delete();
            } else {
                throw new Exception("Default language not deletable", 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(Language $language): Language
    {
        try {
            return $language;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }


    /**
     * @throws Exception
     */
    public function fileList(Language $language)
    {
        try {
            $i = 0;
            $array = [];

            if (file_exists(base_path("resources/js/languages/{$language->code}.json"))) {
                $array[$i] = (object)[
                    'path' => base_path("resources/js/languages/{$language->code}.json"),
                    'name' => "{$language->code}.json"
                ];
                $i++;
            }

            if (file_exists(base_path("lang/{$language->code}"))) {
                $files = scandir(base_path("lang/{$language->code}"));
                if (count($files) > 2) {
                    foreach ($files as $file) {
                        if ($file != '.' && $file != '..') {
                            $array[$i] = (object)[
                                'path' => base_path("lang/{$language->code}/{$file}"),
                                'name' => $file
                            ];
                            $i++;
                        }
                    }
                }
            }
            return collect($array);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }


    /**
     * Resolve a real path and confirm it lives inside one of the given roots.
     *
     * @param string $path
     * @param array  $roots
     *
     * @return string|null The canonical path, or null when it is outside the roots.
     */
    protected function resolveLanguagePath(string $path, array $roots): ?string
    {
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath)) {
            return null;
        }

        foreach ($roots as $root) {
            $realRoot = realpath($root);
            if ($realRoot !== false && str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR)) {
                return $realPath;
            }
        }

        return null;
    }

    public function fileText(LanguageFileTextGetRequest $request)
    {
        $nameParts = explode('.', $request->name);
        if (count($nameParts) < 2) {
            return null;
        }

        if ($nameParts[1] == 'json') {
            $path = $this->resolveLanguagePath($request->path, [base_path('resources/js/languages')]);
            if ($path === null) {
                throw new Exception('Invalid language file path.', 422);
            }

            // Return the raw JSON instead of including it, so a writable file
            // can never be executed as PHP.
            return file_get_contents($path);
        }

        $path = $this->resolveLanguagePath($request->path, [base_path('lang')]);
        if ($path === null) {
            throw new Exception('Invalid language file path.', 422);
        }

        return include($path);
    }

    /**
     * @throws Exception
     */
    public function fileTextStore(Request $request): void
    {
        try {
            $path = $this->resolveLanguagePath(
                (string) $request->x_language_file_path,
                [base_path('lang'), base_path('resources/js/languages')]
            );

            if ($path === null) {
                throw new Exception('Invalid language file path.', 422);
            }

            $fileContent = file_get_contents($path);

            foreach ($request->all() as $key => $value) {
                if ($key != 'x_language_file_path' && $key != 'x_language_file_name') {
                    $key = str_replace('_', ' ', $key);

                    // Escape the value so it stays a valid, inert PHP/JSON string literal.
                    $safeValue = str_replace(
                        ['\\', '"', '$', "\r", "\n"],
                        ['\\\\', '\\"', '\\$', '', ''],
                        (string) $value
                    );

                    if (strpos($fileContent, "'" . $key . "'") !== false) {
                        $fileContent = str_replace("'" . $key . "'", "\"{$safeValue}\"", $fileContent);
                    } elseif (strpos($fileContent, "\"{$key}\"") !== false) {
                        $fileContent = str_replace("\"{$key}\"", "\"{$safeValue}\"", $fileContent);
                    }
                }
            }

            file_put_contents($path, $fileContent, LOCK_EX);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
