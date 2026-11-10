<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Previously this used env('VITE_API_KEY') with a loose `==` comparison:
     *  - env() returns null under `config:cache`, so an absent/empty header
     *    could compare equal and let the request through;
     *  - `==` is not constant time.
     *
     * Now the value comes from config() and is compared with hash_equals().
     * An empty configured key disables the gate explicitly (fail closed).
     *
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('security.api_key', '');
        $provided = (string) $request->header('x-api-key', '');

        if ($expected !== '' && $provided !== '' && hash_equals($expected, $provided)) {
            return $next($request);
        }

        return response()->json(trans('all.message.invalid_api_key'), 400);
    }
}
