<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCurrencyRequest;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::query()->orderBy('ordering')->orderBy('code')->get();
        return view('admin.settings.currencies.index', compact('currencies'));
    }

    public function store(SaveCurrencyRequest $request): RedirectResponse
    {
        $this->validateSeparators($request);
        DB::transaction(function () use ($request): void {
            $data = $this->payload($request);
            $data['is_default'] = $request->boolean('is_default') || !Currency::query()->where('is_default', true)->exists();
            if ($data['is_default']) {
                $this->rebaseCurrencies((string) $data['exchange_rate']);
                $data['active'] = true;
                $data['exchange_rate'] = '1';
            }
            Currency::create($data);
        });
        return back()->with('ok', 'Currency created.');
    }

    public function update(SaveCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $this->validateSeparators($request);
        DB::transaction(function () use ($request, $currency): void {
            Currency::query()->lockForUpdate()->findOrFail($currency->id);
            $data = $this->payload($request);
            $makeDefault = $request->boolean('is_default');
            if ($currency->is_default && !$makeDefault) {
                throw ValidationException::withMessages(['is_default' => 'Choose another default currency before removing this default.']);
            }
            if ($makeDefault) {
                if (!$currency->is_default) {
                    $this->rebaseCurrencies((string) $data['exchange_rate'], $currency->id);
                }
                Currency::query()->where('id', '!=', $currency->id)->update(['is_default' => false]);
                $data['is_default'] = true;
                $data['active'] = true;
                $data['exchange_rate'] = '1';
            }
            $currency->update($data);
        });
        return back()->with('ok', 'Currency updated.');
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->is_default) {
            return back()->withErrors(['currency' => 'The default currency cannot be deleted.']);
        }
        $currency->delete();
        return back()->with('ok', 'Currency deleted.');
    }

    private function payload(SaveCurrencyRequest $request): array
    {
        $data = $request->validated();
        $data['active'] = $request->boolean('active');
        $data['is_default'] = $request->boolean('is_default');
        $data['ordering'] = (int) ($data['ordering'] ?? 0);
        $data['thousands_separator'] = $data['thousands_separator'] ?? '';
        $data['rate_updated_at'] = now();
        return $data;
    }

    private function validateSeparators(SaveCurrencyRequest $request): void
    {
        if ($request->input('decimal_separator') === $request->input('thousands_separator')) {
            throw ValidationException::withMessages(['thousands_separator' => 'Thousands and decimal separators must be different.']);
        }
    }

    private function rebaseCurrencies(string $newBaseRate, ?int $newBaseId = null): void
    {
        $currencies = Currency::query()->lockForUpdate()->get();
        foreach ($currencies as $existing) {
            if ($existing->id === $newBaseId) {
                continue;
            }
            $rate = bcdiv((string) $existing->exchange_rate, $newBaseRate, 8);
            if (bccomp($rate, '0.00000001', 8) < 0 || bccomp($rate, '999999999999.99999999', 8) > 0) {
                throw ValidationException::withMessages(['exchange_rate' => 'Changing the base would move an existing currency outside the supported rate range.']);
            }
            $existing->update([
                'exchange_rate' => $rate,
                'is_default' => false,
                'rate_updated_at' => now(),
            ]);
        }
    }
}
