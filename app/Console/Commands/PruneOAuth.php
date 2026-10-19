<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\OAuthGrant;
use Illuminate\Console\Command;

class PruneOAuth extends Command
{
    protected $signature = 'oauth:prune';

    protected $description = 'Delete expired OAuth codes, ended connections and clients registered but never authorized';

    public function handle(): int
    {
        $codes = OAuthAuthCode::query()->where('expires_at', '<', now()->subDay())->delete();

        // Kept a while after they end, so the audit trail stays readable.
        $grants = 0;
        OAuthGrant::query()
            ->where(fn ($q) => $q->where('revoked_at', '<', now()->subDays(30))
                ->orWhere('refresh_expires_at', '<', now()->subDays(30)))
            ->each(function (OAuthGrant $grant) use (&$grants): void {
                $grant->delete();
                $grants++;
            });

        // Registration is open (RFC 7591): drop clients nobody ever approved.
        $clients = OAuthClient::query()
            ->where('created_at', '<', now()->subDays(7))
            ->whereDoesntHave('grants')
            ->delete();

        $this->info("Removed {$codes} code(s), {$grants} connection(s), {$clients} client(s).");

        return self::SUCCESS;
    }
}
