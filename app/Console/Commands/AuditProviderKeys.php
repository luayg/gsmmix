<?php

namespace App\Console\Commands;

use App\Services\Security\ProviderKeyStorage;
use Illuminate\Console\Command;
use Throwable;

final class AuditProviderKeys extends Command
{
    protected $signature = 'providers:keys-audit {--json : Print counts as JSON}';
    protected $description = 'Read-only audit of provider API key storage; no secrets or network requests.';

    public function handle(): int
    {
        try {
            $counts = app(ProviderKeyStorage::class)->audit();
        } catch (Throwable) {
            // Database exception messages can contain sensitive connection details.
            $message = 'Audit could not run. Check the database, schema and application key locally.';
            $this->line($this->option('json') ? json_encode(['error' => $message]) : $message);
            return self::FAILURE;
        }
        $this->line($this->option('json') ? json_encode($counts) : 'Read-only provider key audit');
        if (!$this->option('json')) {
            $this->table(['State', 'Count'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->all());
        }
        return ($counts['legacy_plaintext'] || $counts['unreadable'] || $counts['nested'])
            ? self::FAILURE : self::SUCCESS;
    }
}
