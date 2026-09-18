<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Language;
use App\Services\Settings\CurrencyConverter;
use Tests\Support\SecurityTestCase;
use Illuminate\Http\UploadedFile;

final class LanguageCurrencySettingsTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_09_16_000200_create_languages_and_currencies_tables.php'))->up();
        $this->actingAs($this->user('Administrator'));
    }

    public function test_seeded_defaults_and_pages_are_available(): void
    {
        $this->get(route('admin.settings.languages'))->assertOk()->assertSee('English');
        $this->get(route('admin.settings.currencies'))->assertOk()->assertSee('US Dollar');
        $this->assertDatabaseHas('languages', ['code' => 'en', 'is_default' => 1, 'active' => 1]);
        $this->assertDatabaseHas('currencies', ['code' => 'USD', 'exchange_rate' => 1, 'is_default' => 1]);
    }

    public function test_new_default_language_is_active_and_the_old_default_is_cleared(): void
    {
        $this->post(route('admin.settings.languages.store'), $this->languagePayload([
            'name' => 'Arabic', 'native_name' => 'العربية', 'code' => 'ar', 'locale' => 'ar_JO',
            'direction' => 'rtl', 'active' => '0', 'is_default' => '1',
        ]))->assertRedirect()->assertSessionHas('ok');
        $this->assertDatabaseHas('languages', ['code' => 'ar', 'active' => 1, 'is_default' => 1]);
        $this->assertDatabaseHas('languages', ['code' => 'en', 'is_default' => 0]);
    }

    public function test_active_language_can_be_selected_and_persisted_in_session(): void
    {
        Language::query()->create([
            'name' => 'Arabic', 'native_name' => 'العربية', 'code' => 'ar', 'locale' => 'ar',
            'direction' => 'rtl', 'flag' => '🇯🇴', 'active' => true, 'is_default' => false, 'ordering' => 1,
        ]);

        $this->from(route('home'))->post(route('locale.update', 'ar'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('locale', 'ar');

        Language::query()->where('code', 'ar')->update(['active' => false]);
        $this->post(route('locale.update', 'ar'))->assertNotFound();
    }

    public function test_default_language_cannot_be_demoted_or_deleted(): void
    {
        $english = Language::query()->where('code', 'en')->firstOrFail();
        $this->put(route('admin.settings.languages.update', $english), $this->languagePayload(['is_default' => '0']))
            ->assertSessionHasErrors('is_default');
        $this->delete(route('admin.settings.languages.destroy', $english))->assertSessionHasErrors('language');
        $this->assertDatabaseHas('languages', ['id' => $english->id, 'is_default' => 1]);
    }

    public function test_translations_are_validated_and_saved(): void
    {
        $english = Language::query()->where('code', 'en')->firstOrFail();
        $this->put(route('admin.settings.languages.translations.update', $english), ['translations' => [
            ['key' => 'navigation.home', 'value' => 'Home'], ['key' => 'orders.title', 'value' => 'Orders'],
        ]])->assertRedirect()->assertSessionHas('ok');
        $this->assertDatabaseHas('language_translations', ['language_id' => $english->id, 'translation_key' => 'navigation.home', 'value' => 'Home']);
        $this->get(route('admin.settings.languages.translations', $english))->assertOk()->assertSee('navigation.home');
    }

    public function test_default_currency_rate_is_one_and_conversion_is_decimal_safe(): void
    {
        $this->post(route('admin.settings.currencies.store'), $this->currencyPayload([
            'code' => 'JOD', 'name' => 'Jordanian Dinar', 'symbol' => 'JD', 'exchange_rate' => '0.70900000', 'is_default' => '1',
        ]))->assertRedirect()->assertSessionHas('ok');
        $jod = Currency::query()->where('code', 'JOD')->firstOrFail();
        $this->assertSame('1.00000000', $jod->exchange_rate);
        $this->assertFalse((bool) Currency::query()->where('code', 'USD')->value('is_default'));
        $this->assertSame('1.41043723', Currency::query()->where('code', 'USD')->value('exchange_rate'));

        $this->post(route('admin.settings.currencies.store'), $this->currencyPayload([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => '0.90000000',
        ]))->assertRedirect();
        $eur = Currency::query()->where('code', 'EUR')->firstOrFail();
        $converter = app(CurrencyConverter::class);
        $this->assertSame('9.00000000', $converter->fromBase('10.00', $eur));
        $this->assertSame('10.00000000', $converter->toBase('9.00', $eur));
    }

    public function test_default_currency_cannot_be_demoted_deleted_or_given_another_rate(): void
    {
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->put(route('admin.settings.currencies.update', $usd), $this->currencyPayload([
            'exchange_rate' => '3.50000000', 'is_default' => '1',
        ]))->assertRedirect();
        $this->assertSame('1.00000000', $usd->fresh()->exchange_rate);
        $this->delete(route('admin.settings.currencies.destroy', $usd))->assertSessionHasErrors('currency');
    }

    public function test_translations_can_be_imported_and_exported(): void
    {
        $english = Language::query()->where('code', 'en')->firstOrFail();
        $file = UploadedFile::fake()->createWithContent('en.json', json_encode(['translations' => ['navigation.home' => 'Home']]));
        $this->post(route('admin.settings.languages.import', $english), ['translation_file' => $file])->assertRedirect()->assertSessionHas('ok');
        $this->get(route('admin.settings.languages.export', $english))->assertOk()->assertHeader('content-type', 'application/json')->assertSee('navigation.home');
    }

    public function test_exchange_rates_can_be_updated_in_bulk_without_changing_base_rate(): void
    {
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->post(route('admin.settings.currencies.store'), $this->currencyPayload(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => '0.9']))->assertRedirect();
        $eur = Currency::query()->where('code', 'EUR')->firstOrFail();
        $this->put(route('admin.settings.currencies.rates.update'), ['rates' => [$usd->id => '9', $eur->id => '0.92']])->assertRedirect()->assertSessionHas('ok');
        $this->assertSame('1.00000000', $usd->fresh()->exchange_rate);
        $this->assertSame('0.92000000', $eur->fresh()->exchange_rate);
    }

    private function languagePayload(array $overrides = []): array
    {
        return array_replace(['name' => 'English', 'native_name' => 'English', 'code' => 'en', 'locale' => 'en', 'direction' => 'ltr', 'flag' => '', 'active' => '1', 'is_default' => '1', 'ordering' => 0], $overrides);
    }

    private function currencyPayload(array $overrides = []): array
    {
        return array_replace(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => '1.00000000', 'decimal_places' => 2, 'symbol_position' => 'before', 'decimal_separator' => '.', 'thousands_separator' => ',', 'active' => '1', 'is_default' => '0', 'ordering' => 0], $overrides);
    }
}
