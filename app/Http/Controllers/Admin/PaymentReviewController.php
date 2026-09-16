<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentSettlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class PaymentReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'review';
        abort_unless(in_array($status, ['review', 'paid', 'rejected', 'all'], true), 404);

        $payments = PaymentTransaction::query()
            ->with(['user', 'gateway', 'approver'])
            ->whereHas('gateway', fn ($query) => $query->where('is_system', false))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.finances.payment-reviews.index', [
            'payments' => $payments,
            'status' => $status,
            'reviewCount' => PaymentTransaction::query()
                ->where('status', 'review')
                ->whereHas('gateway', fn ($query) => $query->where('is_system', false))
                ->count(),
        ]);
    }

    public function show(PaymentTransaction $payment)
    {
        $this->ensureManual($payment);
        $payment->load(['user', 'gateway', 'currency', 'approver']);

        return view('admin.finances.payment-reviews.show', compact('payment'));
    }

    public function proof(PaymentTransaction $payment)
    {
        $this->ensureManual($payment);
        abort_unless($payment->proof_path && str_starts_with($payment->proof_path, 'payment-proofs/'), 404);
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        $path = Storage::disk('local')->path($payment->proof_path);
        $mime = Storage::disk('local')->mimeType($payment->proof_path) ?: 'image/jpeg';
        $extension = pathinfo($payment->proof_path, PATHINFO_EXTENSION) ?: 'jpg';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="payment-proof-'.$payment->id.'.'.$extension.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function approve(Request $request, PaymentTransaction $payment, PaymentSettlement $settlement): RedirectResponse
    {
        $this->ensureManual($payment);
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($payment->status === 'paid') {
            return back()->with('ok', 'This payment was already approved. No duplicate credit was added.');
        }
        if ($payment->status !== 'review') {
            throw ValidationException::withMessages(['payment' => 'Only payments awaiting review can be approved.']);
        }

        $settled = $settlement->paid(
            $payment,
            ($data['reference'] ?? null) ?: 'manual-review:'.$payment->uuid,
            ['manual_review' => [
                'decision' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now()->toIso8601String(),
                'note' => $data['note'] ?? null,
            ]],
        );
        return redirect()->route('admin.finances.payment-reviews.show', $settled)
            ->with('ok', 'Payment approved and customer balance credited successfully.');
    }

    public function reject(Request $request, PaymentTransaction $payment): RedirectResponse
    {
        $this->ensureManual($payment);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $payment, $data): void {
            $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status !== 'review') {
                throw ValidationException::withMessages(['payment' => 'Only payments awaiting review can be rejected.']);
            }

            $metadata = $locked->metadata ?? [];
            $metadata['manual_review'] = [
                'decision' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now()->toIso8601String(),
                'reason' => $data['reason'],
            ];
            $locked->forceFill([
                'status' => 'rejected',
                'approved_by' => $request->user()->id,
                'metadata' => $metadata,
            ])->save();
        });

        return redirect()->route('admin.finances.payment-reviews.show', $payment)
            ->with('ok', 'Payment rejected. The customer balance was not changed.');
    }

    private function ensureManual(PaymentTransaction $payment): void
    {
        $payment->loadMissing('gateway');
        abort_unless($payment->gateway && !$payment->gateway->is_system, 404);
    }
}
