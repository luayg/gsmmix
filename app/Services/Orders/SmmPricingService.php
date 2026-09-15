<?php

namespace App\Services\Orders;

use App\Models\ServiceGroupPrice;
use App\Models\SmmService;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class SmmPricingService
{
    public const SCALE = 4;

    public function quote(SmmService $service, User $user, array $fields, int $fallbackQuantity = 1): array
    {
        $params = $this->decodeArray($service->params ?? []);
        $type = $this->normalizeType((string)($params['smm_type'] ?? 'default'));
        $mode = $this->billingMode($type, $params);

        $providerRate = $this->decimal($service->cost ?? 0);
        $sellRate = $this->sellRateForUser($service, $user);

        if (!is_finite((float)$sellRate) || (float)$sellRate < 0) {
            throw new SmmPricingException(['service_id' => 'SMM service sell rate must be a nonnegative number.']);
        }

        $limits = is_array($params['smm_limits'] ?? null) ? $params['smm_limits'] : [];
        $minimum = max(0, (int)($limits['min'] ?? 0));
        $maximum = max(0, (int)($limits['max'] ?? 0));

        $effectiveQuantity = max(1, $this->positiveInt($fields['quantity'] ?? null) ?? $fallbackQuantity);
        $billableUnits = 0;
        $meta = [];

        switch ($mode) {
            case 'per_order':
                $effectiveQuantity = 1;
                $billableUnits = 1000;
                break;

            case 'per_1000_runs':
                $quantity = $this->positiveInt($fields['quantity'] ?? null) ?? max(1, $fallbackQuantity);
                $runs = $this->positiveInt($fields['runs'] ?? null) ?? 1;
                $this->validateQuantityLimits($quantity, $minimum, $maximum, 'Quantity');
                $effectiveQuantity = $quantity;
                $billableUnits = $quantity * $runs;
                $meta['runs'] = $runs;
                $meta['quantity_per_run'] = $quantity;
                break;

            case 'per_1000_lines_comments':
                $count = $this->lineCount($fields['comments'] ?? null);
                if ($count < 1) {
                    throw new SmmPricingException(['required.comments' => 'At least one comment is required.']);
                }
                $this->validateQuantityLimits($count, $minimum, $maximum, 'Comments count');
                $effectiveQuantity = $count;
                $billableUnits = $count;
                $meta['line_count'] = $count;
                break;

            case 'per_1000_lines_usernames':
                $count = $this->lineCount($fields['usernames'] ?? null);
                if ($count < 1) {
                    throw new SmmPricingException(['required.usernames' => 'At least one username is required.']);
                }
                $this->validateQuantityLimits($count, $minimum, $maximum, 'Usernames count');
                $effectiveQuantity = $count;
                $billableUnits = $count;
                $meta['line_count'] = $count;
                break;

            case 'subscription_fixed':
                $min = $this->positiveInt($fields['min'] ?? null);
                $max = $this->positiveInt($fields['max'] ?? null);
                if ($min === null || $max === null) {
                    throw new SmmPricingException([
                        'required.min' => 'Subscription Min is required for safe billing.',
                        'required.max' => 'Subscription Max is required for safe billing.',
                    ]);
                }
                if ($min !== $max) {
                    throw new SmmPricingException([
                        'required.max' => 'Variable Min/Max subscriptions cannot be pre-charged safely. Set Min and Max to the same value.',
                    ]);
                }
                $this->validateQuantityLimits($min, $minimum, $maximum, 'Subscription quantity per post');

                $posts = max(0, (int)($fields['posts'] ?? 0));
                $oldPosts = max(0, (int)($fields['old_posts'] ?? 0));
                $totalPosts = $posts + $oldPosts;
                if ($totalPosts < 1) {
                    throw new SmmPricingException([
                        'required.posts' => 'A finite Posts or Old Posts count is required. Unlimited subscriptions cannot be pre-charged safely.',
                    ]);
                }

                $billableUnits = $min * $totalPosts;
                $effectiveQuantity = $billableUnits;
                $meta['quantity_per_post'] = $min;
                $meta['posts'] = $posts;
                $meta['old_posts'] = $oldPosts;
                $meta['total_posts'] = $totalPosts;
                break;

            case 'per_1000_quantity':
            default:
                $quantity = $this->positiveInt($fields['quantity'] ?? null) ?? max(1, $fallbackQuantity);
                $this->validateQuantityLimits($quantity, $minimum, $maximum, 'Quantity');
                $effectiveQuantity = $quantity;
                $billableUnits = $quantity;
                break;
        }

        $sellTotal = $mode === 'per_order'
            ? $sellRate
            : $this->rateTimesUnits($sellRate, $billableUnits);

        $providerTotal = $mode === 'per_order'
            ? $providerRate
            : $this->rateTimesUnits($providerRate, $billableUnits);

        $profitTotal = $this->decimal((float)$sellTotal - (float)$providerTotal);

        return [
            'smm_type' => $type,
            'billing_mode' => $mode,
            'price_unit' => $mode === 'per_order' ? 'per_order' : 'per_1000',
            'sell_rate' => $sellRate,
            'provider_rate' => $providerRate,
            'sell_total' => $sellTotal,
            'provider_total' => $providerTotal,
            'profit_total' => $profitTotal,
            'effective_quantity' => $effectiveQuantity,
            'billable_units' => $billableUnits,
            'minimum' => $minimum,
            'maximum' => $maximum,
            'meta' => $meta,
        ];
    }

    private function billingMode(string $type, array $params): string
    {
        $configured = strtolower(trim((string)($params['smm_billing_mode'] ?? '')));
        $allowed = [
            'per_order',
            'per_1000_quantity',
            'per_1000_runs',
            'per_1000_lines_comments',
            'per_1000_lines_usernames',
            'subscription_fixed',
        ];
        if (in_array($configured, $allowed, true)) {
            return $configured;
        }

        if (str_contains($type, 'package')) {
            return 'per_order';
        }
        if ($type === 'subscriptions' || $type === 'subscription') {
            return 'subscription_fixed';
        }
        if (str_contains($type, 'drip')) {
            return 'per_1000_runs';
        }
        if ($type === 'custom comments') {
            return 'per_1000_lines_comments';
        }
        if ($type === 'mentions custom list') {
            return 'per_1000_lines_usernames';
        }

        return 'per_1000_quantity';
    }

    private function sellRateForUser(SmmService $service, User $user): string
    {
        $groupId = (int)($user->group_id ?? 0);
        if ($groupId > 0 && Schema::hasTable('service_group_prices')) {
            $groupPrice = ServiceGroupPrice::query()
                ->where('service_type', 'smm')
                ->where('service_id', (int)$service->id)
                ->where('group_id', $groupId)
                ->first();

            $price = $groupPrice?->finalPrice($service);
            if ($price !== null) {
                return $this->decimal($price);
            }
        }

        foreach ([
            $service->price ?? null,
            $service->sell_price ?? null,
            $service->final_price ?? null,
            $service->customer_price ?? null,
            $service->retail_price ?? null,
        ] as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate) && (float)$candidate > 0) {
                return $this->decimal($candidate);
            }
        }

        $cost = (float)($service->cost ?? 0);
        $profit = (float)($service->profit ?? 0);
        $profitType = (int)($service->profit_type ?? 1);

        $price = $profitType === 2
            ? $cost + ($cost * ($profit / 100))
            : $cost + $profit;

        return $this->decimal(max(0, $price));
    }

    private function validateQuantityLimits(int $quantity, int $minimum, int $maximum, string $label): void
    {
        if ($minimum > 0 && $quantity < $minimum) {
            throw new SmmPricingException(['quantity' => "{$label} must be at least {$minimum}."]);
        }
        if ($maximum > 0 && $quantity > $maximum) {
            throw new SmmPricingException(['quantity' => "{$label} must be at most {$maximum}."]);
        }
    }

    private function lineCount($value): int
    {
        if (is_array($value)) {
            return count(array_values(array_filter(array_map(
                static fn ($item) => trim((string)$item),
                $value
            ), static fn ($item) => $item !== '')));
        }

        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }

        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        return count(array_values(array_filter(array_map('trim', $lines), static fn ($line) => $line !== '')));
    }

    private function positiveInt($value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $value = (int)$value;
        return $value > 0 ? $value : null;
    }

    private function rateTimesUnits(string $rate, int $units): string
    {
        if ($units <= 0 || (float)$rate <= 0) {
            return $this->decimal(0);
        }

        return $this->decimal(((float)$rate * $units) / 1000);
    }

    private function decimal($value): string
    {
        $value = is_numeric($value) ? (float)$value : 0.0;
        return number_format(round($value, self::SCALE), self::SCALE, '.', '');
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        $type = str_replace(['_', '-'], ' ', $type);
        return trim((string)preg_replace('/\s+/', ' ', $type));
    }

    private function decodeArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
