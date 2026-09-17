<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\ServiceGroupPrice;
use App\Models\User;
use App\Services\Catalog\ServicePriceMatrix;
use PHPUnit\Framework\TestCase;

final class ServicePriceMatrixTest extends TestCase
{
    public function test_guest_sees_every_group_price_and_fallback(): void
    {
        $groups = collect([$this->group(1, 'Normal'), $this->group(2, 'VIP'), $this->group(3, 'Reseller')]);
        $override = new ServiceGroupPrice(['group_id' => 2, 'price' => 8.5, 'discount' => 0, 'discount_type' => 1]);

        $prices = (new ServicePriceMatrix())->prices((object) ['price' => 10], $groups, collect([$override]), null);

        self::assertSame(['Normal', 'VIP', 'Reseller'], array_column($prices, 'group'));
        self::assertSame([10.0, 8.5, 10.0], array_column($prices, 'price'));
    }

    public function test_signed_in_user_sees_only_their_group(): void
    {
        $groups = collect([$this->group(1, 'Normal'), $this->group(2, 'VIP')]);
        $user = new User();
        $user->group_id = 2;

        $prices = (new ServicePriceMatrix())->prices((object) ['price' => 12], $groups, collect(), $user);

        self::assertCount(1, $prices);
        self::assertSame('VIP', $prices[0]['group']);
        self::assertSame(12.0, $prices[0]['price']);
    }

    private function group(int $id, string $name): Group
    {
        $group = new Group(['name' => $name]);
        $group->id = $id;

        return $group;
    }
}
