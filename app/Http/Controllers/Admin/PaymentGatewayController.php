<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePaymentGatewayRequest;
use App\Models\Currency;
use App\Models\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class PaymentGatewayController extends Controller
{
    public function index()
    {
        $automaticGateways = PaymentGateway::query()->where('is_system', true)->with('currencies')->withCount('transactions')->orderBy('ordering')->get();
        $manualGateways = PaymentGateway::query()->where('is_system', false)->with('currencies')->withCount('transactions')->orderBy('ordering')->orderBy('id')->paginate(15);
        return view('admin.settings.payment.index', compact('automaticGateways', 'manualGateways'));
    }

    public function create() { return view('admin.settings.payment.edit', ['gateway' => null, 'currencies' => $this->currencies()]); }
    public function edit(PaymentGateway $gateway) { $gateway->load('currencies'); return view('admin.settings.payment.edit', ['gateway' => $gateway, 'currencies' => $this->currencies()]); }

    public function store(SavePaymentGatewayRequest $request): RedirectResponse
    {
        $this->ensureActiveCurrencies($request->validated('currency_ids'));
        DB::transaction(function () use ($request): void {
            $gateway = PaymentGateway::create($this->payload($request));
            $gateway->currencies()->sync($request->validated('currency_ids'));
        });
        return redirect()->route('admin.settings.payment')->with('ok', 'Manual payment method created.');
    }

    public function update(SavePaymentGatewayRequest $request, PaymentGateway $gateway): RedirectResponse
    {
        $this->ensureActiveCurrencies($request->validated('currency_ids'));
        $oldLogo = $gateway->logo_path;
        DB::transaction(function () use ($request, $gateway): void {
            $gateway->update($this->payload($request, $gateway));
            $gateway->currencies()->sync($request->validated('currency_ids'));
        });
        if ($oldLogo && $oldLogo !== $gateway->logo_path && str_starts_with($oldLogo, 'payments/')) Storage::disk('public')->delete($oldLogo);
        return redirect()->route('admin.settings.payment')->with('ok', 'Payment gateway updated.');
    }

    public function destroy(PaymentGateway $gateway): RedirectResponse
    {
        if ($gateway->is_system) return back()->withErrors(['gateway' => 'Built-in electronic gateways cannot be deleted.']);
        if ($gateway->transactions()->exists()) return back()->withErrors(['gateway' => 'This gateway has payment history. Disable it instead of deleting it.']);
        $logo = $gateway->logo_path;
        $gateway->delete();
        if ($logo && str_starts_with($logo, 'payments/')) Storage::disk('public')->delete($logo);
        return back()->with('ok', 'Manual payment method deleted.');
    }

    private function payload(SavePaymentGatewayRequest $request, ?PaymentGateway $gateway = null): array
    {
        $system = $gateway?->is_system === true;
        $data = $request->safe()->except(['currency_ids','logo','payment_details','client_id','client_secret','paypal_webhook_id','binance_api_key','binance_secret_key','binance_webhook_public_key','usdt_network','wallet_address','provider_url','provider_api_key','contract_address','confirmations']);
        if ($system) unset($data['name'], $data['slug'], $data['driver'], $data['ordering']);
        else { $data['driver'] = 'manual'; $data['is_system'] = false; }
        $data['active'] = $request->boolean('active');
        $data['sandbox'] = $request->boolean('sandbox');
        $data['tax_percent'] = $data['tax_percent'] ?? '0';
        $data['logo_path'] = $gateway?->logo_path;
        if (!$system) {
            $data['config'] = ['payment_details' => trim((string) $request->input('payment_details'))];
            $data['credentials'] = null;
            if ($request->hasFile('logo')) $data['logo_path'] = $request->file('logo')->store('payments', 'public');
            return $data;
        }
        $config = $gateway->config ?? [];
        if ($gateway->driver === 'usdt') {
            $config['network'] = $request->input('usdt_network', $config['network'] ?? null);
            $config['confirmations'] = (int) $request->input('confirmations', $config['confirmations'] ?? 12);
        }
        $data['config'] = $config;
        $fields = match ($gateway->driver) {
            'paypal' => ['client_id','client_secret','paypal_webhook_id'],
            'binance_pay' => ['binance_api_key','binance_secret_key','binance_webhook_public_key'],
            'usdt' => ['wallet_address','provider_url','provider_api_key','contract_address'],
            default => [],
        };
        $credentials = $gateway->credentials ?? [];
        foreach ($fields as $field) if ($request->filled($field)) $credentials[$field] = trim((string) $request->input($field));
        $data['credentials'] = $credentials ?: null;
        return $data;
    }

    private function ensureActiveCurrencies(array $ids): void
    {
        if (Currency::query()->where('active', true)->whereIn('id', $ids)->count() !== count($ids)) throw ValidationException::withMessages(['currency_ids' => 'Select active currencies only.']);
    }
    private function currencies() { return Currency::query()->where('active', true)->orderBy('ordering')->orderBy('code')->get(); }
}
