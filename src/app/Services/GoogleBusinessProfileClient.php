<?php

namespace Backpack\Reviews\app\Services;

use Backpack\Reviews\app\Models\GoogleReviewConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Backpack\Settings\Facades\Settings;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleBusinessProfileClient
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('backpack.reviews.google', []);
    }

    public function isEnabled(): bool
    {
        return (bool) Settings::get('rw.google.enabled', false);
    }

    public function getClientId(): ?string
    {
        return Settings::get('rw.google.client_id');
    }

    public function getClientSecret(): ?string
    {
        return Settings::get('rw.google.client_secret');
    }

    public function getRedirectUri(): ?string
    {
        return Settings::get('rw.google.redirect_uri');
    }

    public function buildAuthUrl(string $state, array $overrides = []): string
    {
        $clientId = $this->getClientId();
        $redirectUri = $this->getRedirectUri();
        if (!$clientId || !$redirectUri) {
            throw new RuntimeException('Google OAuth client_id or redirect_uri is missing.');
        }

        $authUrl = $this->config['auth_url'] ?? 'https://accounts.google.com/o/oauth2/v2/auth';
        $scopes = $this->config['scopes'] ?? [];
        $params = array_merge([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => $this->config['access_type'] ?? 'offline',
            'prompt' => $this->config['prompt'] ?? 'consent',
            'state' => $state,
        ], $overrides);

        return $authUrl . '?' . http_build_query($params);
    }

    public function exchangeCode(string $code): array
    {
        Log::info('google_oauth_client.exchange_code.request', [
            'code_len' => strlen($code),
            'has_client_id' => !empty($this->getClientId()),
            'has_client_secret' => !empty($this->getClientSecret()),
            'redirect_uri' => $this->getRedirectUri(),
        ]);

        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        $redirectUri = $this->getRedirectUri();
        if (!$clientId || !$clientSecret || !$redirectUri) {
            Log::warning('google_oauth_client.exchange_code.misconfigured', [
                'has_client_id' => !empty($clientId),
                'has_client_secret' => !empty($clientSecret),
                'has_redirect_uri' => !empty($redirectUri),
            ]);
            throw new RuntimeException('Google OAuth credentials are not configured.');
        }

        $tokenUrl = $this->config['token_url'] ?? 'https://oauth2.googleapis.com/token';
        $response = Http::asForm()->post($tokenUrl, [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->ok()) {
            Log::error('google_oauth_client.exchange_code.http_error', [
                'status' => $response->status(),
                'response_body' => Str::limit($response->body(), 1000),
            ]);
            throw new RuntimeException('Failed to exchange OAuth code: ' . $response->body());
        }

        $payload = $response->json() ?? [];
        Log::info('google_oauth_client.exchange_code.response', [
            'has_access_token' => !empty(Arr::get($payload, 'access_token')),
            'has_refresh_token' => !empty(Arr::get($payload, 'refresh_token')),
            'token_type' => Arr::get($payload, 'token_type'),
            'expires_in' => Arr::get($payload, 'expires_in'),
        ]);

        return $payload;
    }

    public function refreshAccessToken(GoogleReviewConnection $connection): array
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();
        if (!$clientId || !$clientSecret) {
            throw new RuntimeException('Google OAuth credentials are not configured.');
        }
        if (!$connection->refresh_token) {
            throw new RuntimeException('Google refresh_token is missing.');
        }

        $tokenUrl = $this->config['token_url'] ?? 'https://oauth2.googleapis.com/token';
        $response = Http::asForm()->post($tokenUrl, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->ok()) {
            throw new RuntimeException('Failed to refresh OAuth token: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    public function ensureAccessToken(GoogleReviewConnection $connection): string
    {
        $leeway = (int) ($this->config['token_leeway'] ?? 60);
        $expiresAt = $connection->token_expires_at;
        $shouldRefresh = !$connection->access_token;

        if ($expiresAt instanceof Carbon) {
            $shouldRefresh = $shouldRefresh || $expiresAt->lte(now()->addSeconds($leeway));
        }

        if ($shouldRefresh) {
            $tokens = $this->refreshAccessToken($connection);
            $connection->access_token = Arr::get($tokens, 'access_token', $connection->access_token);
            $connection->token_type = Arr::get($tokens, 'token_type', $connection->token_type);
            $connection->scope = Arr::get($tokens, 'scope', $connection->scope);
            $connection->token_expires_at = $this->resolveExpiresAt($tokens);
            $connection->meta = array_merge($connection->meta ?? [], ['token' => $tokens]);
            $connection->save();
        }

        if (!$connection->access_token) {
            throw new RuntimeException('Access token not available.');
        }

        return $connection->access_token;
    }

    public function listAccounts(string $accessToken): array
    {
        $baseUrl = rtrim(
            $this->config['accounts_api_base']
                ?? $this->config['api_base']
                ?? 'https://mybusinessaccountmanagement.googleapis.com/v1',
            '/'
        );
        $response = Http::withToken($accessToken)->get($baseUrl . '/accounts', [
            // Account Management API limits pageSize to 20.
            'pageSize' => min((int) ($this->config['accounts_page_size'] ?? 20), 20),
        ]);

        if (!$response->ok()) {
            throw new RuntimeException('Failed to fetch accounts: ' . $response->body());
        }

        return $response->json('accounts') ?? [];
    }

    public function listLocations(string $accessToken, string $accountName): array
    {
        $baseUrl = rtrim(
            $this->config['locations_api_base']
                ?? $this->config['api_base']
                ?? 'https://mybusinessbusinessinformation.googleapis.com/v1',
            '/'
        );
        $readMask = $this->config['location_read_mask']
            ?? 'name,title,storeCode,languageCode,storefrontAddress';

        $accountName = ltrim($accountName, '/');
        $response = Http::withToken($accessToken)->get($baseUrl . '/' . $accountName . '/locations', [
            'pageSize' => $this->config['locations_page_size'] ?? 100,
            'readMask' => $readMask,
        ]);

        // Backward compatibility for legacy deployments that still use v4 endpoints.
        if (!$response->ok() && (int) $response->status() === 404) {
            $legacyBaseUrl = rtrim($this->config['api_base'] ?? 'https://mybusiness.googleapis.com/v4', '/');
            if ($legacyBaseUrl !== $baseUrl) {
                $response = Http::withToken($accessToken)->get($legacyBaseUrl . '/' . $accountName . '/locations', [
                    'pageSize' => $this->config['locations_page_size'] ?? 100,
                    'readMask' => $readMask,
                ]);
            }
        }

        if (!$response->ok()) {
            throw new RuntimeException('Failed to fetch locations: ' . $response->body());
        }

        return $response->json('locations') ?? [];
    }

    public function listReviews(
        string $accessToken,
        string $locationName,
        ?string $pageToken = null,
        ?string $accountName = null
    ): array
    {
        $baseUrl = rtrim(
            $this->config['reviews_api_base']
                ?? $this->config['api_base']
                ?? 'https://mybusiness.googleapis.com/v4',
            '/'
        );
        $locationResource = $this->resolveReviewLocationResource($locationName, $accountName);
        $query = [
            'pageSize' => $this->config['reviews_page_size'] ?? 50,
        ];
        if ($pageToken) {
            $query['pageToken'] = $pageToken;
        }
        if (!empty($this->config['reviews_order_by'])) {
            $query['orderBy'] = $this->config['reviews_order_by'];
        }

        $response = Http::withToken($accessToken)->get($baseUrl . '/' . ltrim($locationResource, '/') . '/reviews', $query);

        if (!$response->ok()) {
            throw new RuntimeException('Failed to fetch reviews: ' . $response->body());
        }

        return [
            'reviews' => $response->json('reviews') ?? [],
            'nextPageToken' => $response->json('nextPageToken'),
        ];
    }

    protected function resolveReviewLocationResource(string $locationName, ?string $accountName = null): string
    {
        $locationName = ltrim($locationName, '/');
        if (Str::startsWith($locationName, 'accounts/')) {
            return $locationName;
        }

        $accountName = $accountName ? ltrim($accountName, '/') : null;
        if ($accountName && Str::startsWith($accountName, 'accounts/') && Str::startsWith($locationName, 'locations/')) {
            return $accountName . '/' . $locationName;
        }

        return $locationName;
    }

    protected function resolveExpiresAt(array $tokenResponse): ?Carbon
    {
        $expiresIn = Arr::get($tokenResponse, 'expires_in');
        if (!$expiresIn) {
            return null;
        }

        return now()->addSeconds((int) $expiresIn);
    }

    public function normalizeAccountId(?string $accountName): ?string
    {
        if (!$accountName) {
            return null;
        }

        return Str::afterLast($accountName, '/');
    }

    public function normalizeLocationId(?string $locationName): ?string
    {
        if (!$locationName) {
            return null;
        }

        return Str::afterLast($locationName, '/');
    }
}
