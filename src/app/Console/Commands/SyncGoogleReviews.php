<?php

namespace Backpack\Reviews\app\Console\Commands;

use Backpack\Reviews\app\Models\GoogleReviewConnection;
use Backpack\Reviews\app\Services\GoogleBusinessProfileClient;
use Backpack\Reviews\app\Services\GoogleReviewsSyncService;
use Illuminate\Console\Command;

class SyncGoogleReviews extends Command
{
    protected $signature = 'reviews:google:sync {--connection=}';

    protected $description = 'Sync Google Business Profile reviews into local database';

    public function handle(
        GoogleReviewsSyncService $syncService,
        GoogleBusinessProfileClient $client
    ): int {
        if (!$client->isEnabled()) {
            $this->warn('Google reviews sync is disabled in settings.');
            return self::SUCCESS;
        }

        $connectionId = $this->option('connection');
        if ($connectionId) {
            $connection = GoogleReviewConnection::query()->find($connectionId);
            if (!$connection) {
                $this->error('Connection not found: ' . $connectionId);
                return self::FAILURE;
            }

            $stats = $syncService->syncConnection($connection);
            $this->info(sprintf(
                'Synced connection %d: locations=%d, created=%d, updated=%d',
                $connection->id,
                $stats['locations'],
                $stats['reviews_created'],
                $stats['reviews_updated']
            ));
            return self::SUCCESS;
        }

        $stats = $syncService->syncAll();
        $this->info(sprintf(
            'Synced %d connections: locations=%d, created=%d, updated=%d',
            $stats['connections'],
            $stats['locations'],
            $stats['reviews_created'],
            $stats['reviews_updated']
        ));

        return self::SUCCESS;
    }
}
