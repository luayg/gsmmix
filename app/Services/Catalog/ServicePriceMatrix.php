<?php

namespace App\Services\Catalog;

use App\Models\ServiceGroupPrice;
use App\Models\User;
use Illuminate\Support\Collection;

final class ServicePriceMatrix
{
    public function prices(object $service, Collection $groups, Collection $groupPrices, ?User $user): array
    {
        $visibleGroups = $user
            ? $groups->where('id', (int) $user->group_id)
            : $groups;

        return $visibleGroups->map(function ($group) use ($service, $groupPrices) {
            /** @var ServiceGroupPrice|null $override */
            $override = $groupPrices->firstWhere('group_id', $group->id);

            return [
                'group_id' => (int) $group->id,
                'group' => (string) $group->name,
                'price' => $override?->finalPrice($service) ?? $this->basePrice($service),
            ];
        })->values()->all();
    }

    private function basePrice(object $service): float
    {
        if (isset($service->price) && is_numeric($service->price)) {
            return max(0, round((float) $service->price, 4));
        }

        return max(0, ServiceGroupPrice::servicePrice($service));
    }
}
