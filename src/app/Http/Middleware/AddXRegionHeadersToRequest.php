<?php

namespace Backpack\Reviews\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddXRegionHeadersToRequest
{
    public function handle(Request $request, Closure $next)
    {
        $region = $request->header('X-Region');

        $request->merge([
            'country' => $region,
        ]);

        return $next($request);
    }
}
