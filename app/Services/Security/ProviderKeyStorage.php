<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ProviderKeyStorage
{
    public function __construct(private readonly ProviderKeyCipher $cipher) {}

    /** Read-only; returns counts, never keys, names, URLs or database bindings. */
    public function audit(): array
    {
        $this->requireTable();
        $counts = array_fill_keys(['total', 'encrypted', 'empty', 'legacy_plaintext', 'unreadable', 'nested'], 0);
        DB::table('api_providers')->select(['id', 'api_key'])->orderBy('id')
            ->chunkById(100, function ($rows) use (&$counts): void {
                foreach ($rows as $row) {
                    $counts['total']++;
                    $counts[$this->cipher->state($row->api_key)]++;
                }
            });
        return $counts;
    }

    public function upgrade(): void
    {
        // Check the entire table before any schema/data write. Never infer plaintext
        // from a decryption exception alone (wrong APP_KEY is not plaintext).
        $counts = $this->audit();
        if ($counts['unreadable'] || $counts['nested']) {
            throw new RuntimeException('Provider key upgrade stopped: unreadable or nested encryption. Run providers:keys-audit and recover the correct application key first.');
        }

        // MySQL DDL is not transactional; widening is harmless if the DML rolls back.
        if (!in_array(Schema::getColumnType('api_providers', 'api_key'), ['text', 'mediumtext', 'longtext'], true)) {
            Schema::table('api_providers', function (Blueprint $table): void {
                $table->text('api_key')->nullable()->change();
            });
        }

        // Stop writers/workers during deployment. Row locks and revalidation also
        // prevent overwriting a key replaced between preflight and the transaction.
        DB::transaction(function (): void {
            DB::table('api_providers')->select(['id', 'api_key'])->orderBy('id')->lockForUpdate()
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $value = $this->cipher->upgradedValue($row->api_key);
                        if ($value !== $row->api_key) {
                            DB::table('api_providers')->where('id', $row->id)->update(['api_key' => $value]);
                        }
                    }
                });
        });
    }

    private function requireTable(): void
    {
        if (!Schema::hasTable('api_providers') || !Schema::hasColumn('api_providers', 'api_key')) {
            throw new RuntimeException('Provider key storage is missing. Check the selected database and baseline migrations.');
        }
    }
}
