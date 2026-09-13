<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Services\Security\ProviderKeyStorage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\ProviderSchema;
use Tests\TestCase;

class ProviderKeyStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32)), 'cache.default' => 'array']);
        if (getenv('PROVIDER_KEYS_TEST_MYSQL') === '1') {
            // Only an explicitly named disposable CI database may be modified.
            $this->assertSame('gsmmix_provider_test', getenv('PROVIDER_KEYS_TEST_DATABASE'));
            config(['database.default' => 'provider_test', 'database.connections.provider_test' => [
                'driver' => 'mysql', 'host' => '127.0.0.1',
                'port' => getenv('PROVIDER_KEYS_TEST_PORT') ?: '3306',
                'database' => 'gsmmix_provider_test', 'username' => 'gsmmix_test',
                'password' => 'synthetic-test-only', 'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
            ]]);
        } else {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        }
        DB::purge();
        Schema::dropIfExists('api_providers');
        ProviderSchema::create();
    }

    private function raw(?string $key): int
    {
        return DB::table('api_providers')->insertGetId([
            'name' => 'Synthetic provider', 'type' => 'dhru', 'url' => 'https://provider.invalid/',
            'api_key' => $key, 'balance' => '12.34', 'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function migration(string $file = '2026_09_13_000001_encrypt_api_provider_keys.php'): object
    {
        return require database_path('migrations/' . $file);
    }

    private function textColumn(): void
    {
        Schema::table('api_providers', fn (Blueprint $t) => $t->text('api_key')->nullable()->change());
    }

    private function assertUpgradeRefusedWithoutChanges(): void
    {
        $before = DB::table('api_providers')->orderBy('id')->get()->toJson();
        try {
            $this->migration()->up();
            $this->fail('Unsafe encrypted input was not rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('upgrade stopped', $exception->getMessage());
        }
        $this->assertSame($before, DB::table('api_providers')->orderBy('id')->get()->toJson());
    }

    public function test_legacy_migration_preserves_keys_and_nonsecret_fields(): void
    {
        $plain = str_repeat('L', 255);
        $id = $this->raw($plain);
        $blank = $this->raw('');
        $null = $this->raw(null);
        $zero = $this->raw('0');
        $spaces = $this->raw('   ');
        $this->migration()->up();
        $raw = DB::table('api_providers')->where('id', $id)->first();
        $this->assertGreaterThan(255, strlen($raw->api_key));
        $this->assertSame($plain, Crypt::decryptString($raw->api_key));
        $this->assertSame($plain, ApiProvider::findOrFail($id)->api_key);
        $this->assertEquals('12.34', $raw->balance);
        $this->assertSame('2026-01-01 00:00:00', $raw->updated_at);
        foreach ([$blank, $null, $spaces] as $empty) {
            $this->assertNull(DB::table('api_providers')->where('id', $empty)->value('api_key'));
            $this->assertNull(ApiProvider::findOrFail($empty)->api_key);
        }
        $this->assertSame('0', ApiProvider::findOrFail($zero)->api_key);
        $this->assertSame('text', Schema::getColumnType('api_providers', 'api_key'));
    }

    public function test_both_migrations_can_be_repeated_without_double_encryption(): void
    {
        $id = $this->raw('repeatable-test-key');
        $this->migration()->up();
        $before = DB::table('api_providers')->where('id', $id)->value('api_key');
        $this->migration()->up();
        $this->migration('2026_09_14_000001_validate_api_provider_key_storage.php')->up();
        $this->assertSame($before, DB::table('api_providers')->where('id', $id)->value('api_key'));
        $this->assertSame('repeatable-test-key', ApiProvider::findOrFail($id)->api_key);
    }

    public function test_followup_repairs_blanks_after_the_old_migration_was_recorded(): void
    {
        $this->textColumn();
        $encrypted = Crypt::encryptString('already-migrated');
        $good = $this->raw($encrypted);
        $blank = $this->raw('');
        $this->migration('2026_09_14_000001_validate_api_provider_key_storage.php')->up();
        $this->assertSame($encrypted, DB::table('api_providers')->where('id', $good)->value('api_key'));
        $this->assertNull(DB::table('api_providers')->where('id', $blank)->value('api_key'));
    }

    public function test_a_wrong_application_key_is_not_treated_as_plaintext(): void
    {
        $this->textColumn();
        $this->raw('plain-before-bad-key');
        $other = new Encrypter(random_bytes(32), 'aes-256-cbc');
        $this->raw($other->encryptString('encrypted-under-a-different-key'));
        $this->assertUpgradeRefusedWithoutChanges();
        $this->assertSame(1, app(ProviderKeyStorage::class)->audit()['unreadable']);
    }

    public function test_truncated_ciphertext_is_not_reencrypted(): void
    {
        $this->raw(substr(Crypt::encryptString('truncated-test'), 0, 100));
        $this->assertUpgradeRefusedWithoutChanges();
    }

    public function test_tampered_ciphertext_is_not_reencrypted(): void
    {
        $this->textColumn();
        $payload = json_decode(base64_decode(Crypt::encryptString('tamper-test')), true);
        $payload['mac'] = str_repeat('0', 64);
        $this->raw(base64_encode(json_encode($payload)));
        $this->assertUpgradeRefusedWithoutChanges();
    }

    public function test_nested_ciphertext_requires_manual_recovery(): void
    {
        $this->textColumn();
        $nested = Crypt::encryptString(Crypt::encryptString('nested-test'));
        $id = $this->raw($nested);
        $this->assertUpgradeRefusedWithoutChanges();
        $this->assertSame(1, app(ProviderKeyStorage::class)->audit()['nested']);
        $this->expectException(DecryptException::class);
        ApiProvider::findOrFail($id)->api_key;
    }

    public function test_preflight_checks_every_chunk_before_converting_any_row(): void
    {
        $this->textColumn();
        for ($i = 0; $i < 105; $i++) {
            $this->raw('synthetic-' . $i);
        }
        $other = new Encrypter(random_bytes(32), 'aes-256-cbc');
        $this->raw($other->encryptString('last-row-fails'));
        $this->assertUpgradeRefusedWithoutChanges();
    }

    public function test_more_than_one_chunk_is_upgraded(): void
    {
        for ($i = 0; $i < 105; $i++) {
            $this->raw('synthetic-' . $i);
        }
        $this->migration()->up();
        $this->assertSame(105, app(ProviderKeyStorage::class)->audit()['encrypted']);
    }

    public function test_configured_previous_key_can_read_existing_ciphertext_without_rewriting_it(): void
    {
        $this->textColumn();
        $oldKey = random_bytes(32);
        $old = new Encrypter($oldKey, 'aes-256-cbc');
        $encrypted = $old->encryptString('legacy-keyring-test');
        $id = $this->raw($encrypted);
        Crypt::getFacadeRoot()->previousKeys([$oldKey]);
        $this->migration()->up();
        $this->assertSame($encrypted, DB::table('api_providers')->where('id', $id)->value('api_key'));
        $this->assertSame('legacy-keyring-test', ApiProvider::findOrFail($id)->api_key);
    }

    public function test_cast_remains_compatible_with_laravel_encrypted_cast(): void
    {
        $this->migration()->up();
        $provider = ApiProvider::create(['name' => 'Test', 'type' => 'dhru', 'url' => 'https://provider.invalid/', 'api_key' => 'compatible-test-key']);
        $raw = DB::table('api_providers')->where('id', $provider->id)->value('api_key');
        $native = new class extends Model {
            protected $casts = ['api_key' => 'encrypted'];
        };
        $native->setRawAttributes(['api_key' => $raw]);
        $this->assertSame('compatible-test-key', $native->api_key);
        $this->assertArrayNotHasKey('api_key', $provider->fresh()->toArray());
        $this->assertStringNotContainsString('compatible-test-key', $provider->fresh()->toJson());
    }

    public function test_audit_is_read_only_and_never_prints_secret_values(): void
    {
        $id = $this->raw('audit-sentinel-key');
        $this->assertSame(1, Artisan::call('providers:keys-audit', ['--json' => true]));
        $out = Artisan::output();
        $this->assertStringNotContainsString('audit-sentinel-key', $out);
        $this->assertSame(1, json_decode($out, true)['legacy_plaintext']);
        $this->assertSame('audit-sentinel-key', DB::table('api_providers')->where('id', $id)->value('api_key'));
        $this->migration()->up();
        $raw = DB::table('api_providers')->where('id', $id)->value('api_key');
        $this->assertSame(0, Artisan::call('providers:keys-audit', ['--json' => true]));
        $this->assertStringNotContainsString($raw, Artisan::output());
        $this->assertSame($raw, DB::table('api_providers')->where('id', $id)->value('api_key'));
    }

    public function test_plaintext_model_reads_fail_closed_before_migration(): void
    {
        $id = $this->raw('unmigrated-test-key');
        $this->expectException(DecryptException::class);
        ApiProvider::findOrFail($id)->api_key;
    }

    public function test_blank_model_reads_are_safe_and_model_cannot_double_encrypt(): void
    {
        $this->assertNull(ApiProvider::findOrFail($this->raw(''))->api_key);
        $this->expectException(\InvalidArgumentException::class);
        $provider = new ApiProvider;
        $provider->api_key = Crypt::encryptString('do-not-encrypt-twice');
    }

    public function test_rollback_cannot_restore_plaintext(): void
    {
        $this->expectException(\LogicException::class);
        $this->migration()->down();
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }
}
