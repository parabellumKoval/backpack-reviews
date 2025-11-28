<?php

namespace Backpack\Reviews\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddXRegionHeadersToRequest
{
    public function handle(Request $request, Closure $next)
    {
        $country = $this->normalizeCountry($request->header('X-Region') ?? $request->header('X-Country'));
        $lang = $this->preferredLanguage($request->header('Accept-Language'));

        $payload = [];

        if ($country) {
            $payload['country'] = $country;
        }

        if ($lang) {
            $payload['lang'] = $lang;
            app()->setLocale($lang);
        }

        if (!empty($payload)) {
            $request->merge($payload);
        }

        return $next($request);
    }

    protected function preferredLanguage(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        $parts = explode(',', $header);
        $primary = trim($parts[0] ?? '');
        $primary = explode(';', $primary)[0] ?? '';

        if ($primary === '') {
            return null;
        }

        return strtolower(substr($primary, 0, 5));
    }

    protected function normalizeCountry(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $cleaned = preg_replace('/[^a-zA-Z]/', '', $value);
        $code = strtoupper(substr($cleaned, 0, 2));

        return strlen($code) === 2 ? $code : null;
    }
}
