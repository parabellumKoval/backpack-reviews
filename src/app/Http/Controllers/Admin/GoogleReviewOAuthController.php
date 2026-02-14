<?php

namespace Backpack\Reviews\app\Http\Controllers\Admin;

use Backpack\Reviews\app\Models\GoogleReviewConnection;
use Backpack\Reviews\app\Services\GoogleBusinessProfileClient;
use Backpack\Reviews\app\Services\GoogleReviewsSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class GoogleReviewOAuthController extends \App\Http\Controllers\Controller
{
    public function redirect(Request $request, GoogleBusinessProfileClient $client): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('google_reviews_oauth_state', $state);

        $url = $client->buildAuthUrl($state);

        return redirect()->away($url);
    }

    public function callback(
        Request $request,
        GoogleBusinessProfileClient $client,
        GoogleReviewsSyncService $syncService
    ): RedirectResponse {
        $state = $request->input('state');
        $expected = $request->session()->pull('google_reviews_oauth_state');

        if (!$state || !$expected || $state !== $expected) {
            return redirect()->to('admin/settings/reviews')
                ->with('error', 'OAuth state mismatch.');
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect()->to('admin/settings/reviews')
                ->with('error', 'OAuth code is missing.');
        }

        try {
            $tokens = $client->exchangeCode($code);
            $connection = GoogleReviewConnection::query()->first() ?? new GoogleReviewConnection();
            $connection->label = $connection->label ?: 'Google Business Profile';
            $connection->access_token = Arr::get($tokens, 'access_token', $connection->access_token);
            $connection->refresh_token = Arr::get($tokens, 'refresh_token', $connection->refresh_token);
            $connection->token_type = Arr::get($tokens, 'token_type', $connection->token_type);
            $connection->scope = Arr::get($tokens, 'scope', $connection->scope);
            $connection->token_expires_at = $this->resolveExpiresAt($tokens);
            $connection->status = 'active';
            $connection->meta = array_merge($connection->meta ?? [], ['token' => $tokens]);
            $connection->save();

            $syncService->syncConnection($connection);

            return redirect()->to('admin/settings/reviews')
                ->with('success', 'Google Business Profile connected.');
        } catch (Throwable $e) {
            return redirect()->to('admin/settings/reviews')
                ->with('error', 'OAuth failed: ' . $e->getMessage());
        }
    }

    protected function resolveExpiresAt(array $tokenResponse): ?Carbon
    {
        $expiresIn = Arr::get($tokenResponse, 'expires_in');
        if (!$expiresIn) {
            return null;
        }

        return now()->addSeconds((int) $expiresIn);
    }
}
