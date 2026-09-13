<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Services\Security\ProviderKeyStorage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Support\ProviderSchema;
use Tests\Support\SecurityTestCase;

class ProviderKeyWriteTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ProviderSchema::create();
        app(ProviderKeyStorage::class)->upgrade();
        $this->actingAs($this->user('Administrator'));
    }

    private function provider(): ApiProvider
    {
        return ApiProvider::create([
            'name' => 'Synthetic provider', 'type' => 'dhru', 'url' => 'https://provider.invalid/',
            'api_key' => 'original-test-key', 'params' => ['auth_mode' => 'sha256', 'timeout' => 30],
            'active' => true,
        ]);
    }

    private function form(array $extra = []): array
    {
        return array_merge(['name' => 'Updated provider', 'type' => 'dhru', 'url' => 'https://provider.invalid/'], $extra);
    }

    public function test_all_blank_replacements_preserve_the_existing_key_without_javascript(): void
    {
        $provider = $this->provider();
        $cipher = $provider->getRawOriginal('api_key');
        foreach ([[], ['api_key' => ''], ['api_key' => null], ['api_key' => '   ']] as $replacement) {
            $this->put(route('admin.apis.update', $provider), $this->form($replacement))
                ->assertRedirect(route('admin.apis.index'));
            $this->assertSame($cipher, $provider->fresh()->getRawOriginal('api_key'));
            $this->assertSame('original-test-key', $provider->fresh()->api_key);
        }
    }

    public function test_replacement_is_saved_and_encrypted_in_the_database(): void
    {
        $provider = $this->provider();
        $this->put(route('admin.apis.update', $provider), $this->form(['api_key' => 'replacement-test-key']))
            ->assertRedirect(route('admin.apis.index'));
        $fresh = $provider->fresh();
        $this->assertSame('replacement-test-key', $fresh->api_key);
        $this->assertNotSame('replacement-test-key', $fresh->getRawOriginal('api_key'));
        $this->assertSame('replacement-test-key', Crypt::decryptString($fresh->getRawOriginal('api_key')));
    }

    public function test_omitted_provider_settings_and_flags_are_preserved(): void
    {
        $provider = $this->provider();
        $this->put(route('admin.apis.update', $provider), $this->form())->assertRedirect();
        $this->assertSame(['auth_mode' => 'sha256', 'timeout' => 30], $provider->fresh()->params);
        $this->assertTrue($provider->fresh()->active);
    }

    public function test_explicit_settings_and_flags_can_still_be_changed(): void
    {
        $provider = $this->provider();
        $this->put(route('admin.apis.update', $provider), $this->form(['params' => '{"timeout":40}', 'active' => '0']))
            ->assertRedirect();
        $this->assertSame(['timeout' => 40], $provider->fresh()->params);
        $this->assertFalse($provider->fresh()->active);
    }

    public function test_simple_link_transition_clears_the_unused_key_and_keeps_the_link(): void
    {
        $provider = $this->provider();
        $this->put(route('admin.apis.update', $provider), $this->form([
            'type' => 'simple_link', 'url' => 'https://provider.invalid/?device={imei}',
            'main_field_name' => 'device', 'method' => 'GET',
        ]))->assertRedirect();
        $fresh = $provider->fresh();
        $this->assertNull($fresh->api_key);
        $this->assertSame('https://provider.invalid/?device={imei}', $fresh->url);
        $this->assertSame('device', $fresh->params['main_field']);
    }

    public function test_provider_creation_encrypts_the_key_without_contacting_a_provider(): void
    {
        $this->post(route('admin.apis.store'), $this->form(['api_key' => 'create-test-key', 'active' => 1]))
            ->assertRedirect(route('admin.apis.index'));
        $provider = ApiProvider::firstOrFail();
        $this->assertSame('create-test-key', $provider->api_key);
        $this->assertNotSame('create-test-key', $provider->getRawOriginal('api_key'));
        Http::assertNothingSent();
    }

    public function test_zero_is_a_valid_nonblank_replacement(): void
    {
        $provider = $this->provider();
        $this->put(route('admin.apis.update', $provider), $this->form(['api_key' => '0']))->assertRedirect();
        $this->assertSame('0', $provider->fresh()->api_key);
    }

    public function test_invalid_or_encrypted_replacement_is_rejected_without_changes(): void
    {
        $provider = $this->provider();
        $cipher = $provider->getRawOriginal('api_key');
        foreach ([['api_key' => ['bad']], ['api_key' => str_repeat('x', 256)], ['api_key' => $cipher], ['params' => '{bad json}']] as $bad) {
            $this->putJson(route('admin.apis.update', $provider), $this->form($bad))->assertStatus(422);
            $this->assertSame($cipher, $provider->fresh()->getRawOriginal('api_key'));
        }
    }

    public function test_validation_does_not_flash_api_keys_to_the_session(): void
    {
        $provider = $this->provider();
        $this->from(route('admin.apis.edit', $provider))
            ->put(route('admin.apis.update', $provider), $this->form(['name' => '', 'api_key' => 'never-flash-this-test-key']))
            ->assertSessionHasErrors('name')->assertSessionMissing('_old_input.api_key');
    }

    public function test_create_and_edit_forms_never_prefill_api_keys(): void
    {
        $provider = $this->provider();
        $this->withSession(['_old_input' => ['api_key' => 'old-session-test-key']]);
        foreach ([route('admin.apis.create'), route('admin.apis.edit', $provider)] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('original-test-key', false)
                ->assertDontSee('old-session-test-key', false)
                ->assertSee('type="password"', false)
                ->assertSee('name="api_key"', false);
        }
    }

    public function test_customer_cannot_replace_provider_credentials(): void
    {
        $provider = $this->provider();
        $before = $provider->getRawOriginal('api_key');
        $this->actingAs($this->user('Basic'))->putJson(route('admin.apis.update', $provider), $this->form(['api_key' => 'denied-test-key']))->assertForbidden();
        $this->assertSame($before, $provider->fresh()->getRawOriginal('api_key'));
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }
}
