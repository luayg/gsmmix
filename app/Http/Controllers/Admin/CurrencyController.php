<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCurrencyRequest;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

final class CurrencyController extends Controller
{
    public function index(Request $request)
    {
        $currencies = Currency::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%')->orWhere('code', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('active', $request->input('status') === 'active'))
            ->orderBy('ordering')->orderBy('code')->paginate(15)->withQueryString();
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

    public function bulkRates(Request $request): RedirectResponse
    {
        abort_unless($request->user('web')?->can('settings.edit'), 403);
        $data = $request->validate(['rates' => ['required', 'array', 'max:250'], 'rates.*' => ['required', 'decimal:0,8', 'between:0.00000001,999999999999.99999999']]);
        DB::transaction(function () use ($data): void {
            $currencies = Currency::query()->whereIn('id', array_keys($data['rates']))->lockForUpdate()->get();
            if ($currencies->count() !== count($data['rates'])) throw ValidationException::withMessages(['rates' => 'One or more currencies no longer exist.']);
            foreach ($currencies as $currency) {
                $rate = $currency->is_default ? '1' : (string)$data['rates'][$currency->id];
                $currency->update(['exchange_rate' => $rate, 'rate_updated_at' => now()]);
            }
        });
        return back()->with('ok', 'Exchange rates updated.');
    }

    public function export()
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w'); fputcsv($out, ['code', 'name', 'exchange_rate', 'active', 'updated_at']);
            Currency::query()->orderBy('code')->each(fn (Currency $c) => fputcsv($out, [$c->code, $c->name, $c->exchange_rate, $c->active ? 1 : 0, $c->rate_updated_at?->toIso8601String()])); fclose($out);
        }, 'currencies.csv', ['Content-Type' => 'text/csv']);
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
