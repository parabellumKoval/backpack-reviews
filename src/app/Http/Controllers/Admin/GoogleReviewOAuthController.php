<?php

namespace Backpack\Reviews\app\Http\Controllers\Admin;

use Backpack\Reviews\app\Models\GoogleReviewConnection;
use Backpack\Reviews\app\Services\GoogleBusinessProfileClient;
use Backpack\Reviews\app\Services\GoogleReviewsSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleReviewOAuthController extends \App\Http\Controllers\Controller
{
    public function redirect(Request $request, GoogleBusinessProfileClient $client): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('google_reviews_oauth_state', $state);

        Log::info('google_oauth.redirect.started', [
            'state_len' => strlen($state),
            'session_id' => $request->session()->getId(),
            'user_id' => backpack_user()?->id,
        ]);

        $url = $client->buildAuthUrl($state);

        Log::info('google_oauth.redirect.success', [
            'has_auth_url' => !empty($url),
        ]);

        return redirect()->away($url);
    }

    public function callback(
        Request $request,
        GoogleBusinessProfileClient $client,
        GoogleReviewsSyncService $syncService
    ): RedirectResponse {
        $state = $request->input('state');
        $expected = $request->session()->pull('google_reviews_oauth_state');
        $code = $request->input('code');

        Log::info('google_oauth.callback.received', [
            'has_code' => !empty($code),
            'has_state' => !empty($state),
            'expected_state_present' => !empty($expected),
            'state_match' => !empty($state) && !empty($expected) && hash_equals((string) $expected, (string) $state),
            'session_id' => $request->session()->getId(),
            'user_id' => backpack_user()?->id,
            'query_keys' => array_keys($request->query()),
        ]);

        if (!$state || !$expected || $state !== $expected) {
            Log::warning('google_oauth.callback.state_mismatch', [
                'has_state' => !empty($state),
                'expected_state_present' => !empty($expected),
                'session_id' => $request->session()->getId(),
            ]);

            return redirect()->to('admin/settings/reviews')
                ->with('error', 'OAuth state mismatch.');
        }

        if (!$code) {
            Log::warning('google_oauth.callback.missing_code', [
                'session_id' => $request->session()->getId(),
            ]);

            return redirect()->to('admin/settings/reviews')
                ->with('error', 'OAuth code is missing.');
        }

        try {
            Log::info('google_oauth.callback.exchange_code.started');
            $tokens = $client->exchangeCode($code);

            Log::info('google_oauth.callback.exchange_code.success', [
                'has_access_token' => !empty(Arr::get($tokens, 'access_token')),
                'has_refresh_token' => !empty(Arr::get($tokens, 'refresh_token')),
                'expires_in' => Arr::get($tokens, 'expires_in'),
                'scope_present' => !empty(Arr::get($tokens, 'scope')),
            ]);

            $connection = GoogleReviewConnection::query()->first() ?? new GoogleReviewConnection();
            $isNewConnection = !$connection->exists;
            $connection->label = $connection->label ?: 'Google Business Profile';
            $connection->access_token = Arr::get($tokens, 'access_token', $connection->access_token);
            $connection->refresh_token = Arr::get($tokens, 'refresh_token', $connection->refresh_token);
            $connection->token_type = Arr::get($tokens, 'token_type', $connection->token_type);
            $connection->scope = Arr::get($tokens, 'scope', $connection->scope);
            $connection->token_expires_at = $this->resolveExpiresAt($tokens);
            $connection->status = 'active';
            $connection->meta = array_merge($connection->meta ?? [], ['token' => $tokens]);
            $connection->save();

            Log::info('google_oauth.callback.connection_saved', [
                'connection_id' => $connection->id,
                'is_new' => $isNewConnection,
                'has_refresh_token' => !empty($connection->refresh_token),
                'token_expires_at' => optional($connection->token_expires_at)->toDateTimeString(),
            ]);

            Log::info('google_oauth.callback.initial_sync.started', [
                'connection_id' => $connection->id,
            ]);
            $syncService->syncConnection($connection);
            Log::info('google_oauth.callback.initial_sync.success', [
                'connection_id' => $connection->id,
            ]);

            return redirect()->to('admin/settings/reviews')
                ->with('success', 'Google Business Profile connected.');
        } catch (Throwable $e) {
            Log::error('google_oauth.callback.failed', [
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

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
