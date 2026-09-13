<?php

use App\Services\Security\ProviderKeyStorage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Also covers installations where the earlier migration is already recorded.
        app(ProviderKeyStorage::class)->upgrade();
    }

    public function down(): void
    {
        throw new LogicException('Provider key encryption is irreversible. Restore a verified backup with its matching application key instead.');
    }
};
