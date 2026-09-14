<?php

namespace Tests\Unit;

use App\Models\ApiProvider;
use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Models\SmmOrder;
use App\Services\Orders\DhruOrderGateway;
use App\Services\Orders\GsmhubOrderGateway;
use App\Services\Orders\OrderSender;
use App\Services\Orders\SimpleLinkOrderGateway;
use App\Services\Orders\SmmOrderGateway;
use App\Services\Orders\UnlockbaseOrderGateway;
use App\Services\Orders\WebxOrderGateway;
use Mockery;
use Tests\TestCase;

class OrderSenderProviderContractTest extends TestCase
{
    private function sender(array $overrides = []): OrderSender
    {
        return new OrderSender(
            $overrides['dhru'] ?? Mockery::mock(DhruOrderGateway::class),
            $overrides['webx'] ?? Mockery::mock(WebxOrderGateway::class),
            $overrides['unlockbase'] ?? Mockery::mock(UnlockbaseOrderGateway::class),
            $overrides['gsmhub'] ?? Mockery::mock(GsmhubOrderGateway::class),
            $overrides['simple_link'] ?? Mockery::mock(SimpleLinkOrderGateway::class),
            $overrides['smm'] ?? Mockery::mock(SmmOrderGateway::class),
        );
    }

    public function test_unknown_provider_type_is_rejected_instead_of_falling_back_to_dhru(): void
    {
        $dhru = Mockery::mock(DhruOrderGateway::class);
        $dhru->shouldNotReceive('placeImeiOrder');

        $provider = new ApiProvider(['type' => 'mystery']);
        $result = $this->sender(['dhru' => $dhru])->sendImei($provider, new ImeiOrder());

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['retryable']);
        $this->assertSame('rejected', $result['status']);
        $this->assertSame('unsupported_provider_type', $result['response_raw']['error']);
    }

    public function test_unlockbase_server_order_is_rejected_instead_of_being_sent_to_dhru(): void
    {
        $dhru = Mockery::mock(DhruOrderGateway::class);
        $dhru->shouldNotReceive('placeServerOrder');

        $provider = new ApiProvider(['type' => 'unlockbase']);
        $result = $this->sender(['dhru' => $dhru])->sendServer($provider, new ServerOrder());

        $this->assertFalse($result['ok']);
        $this->assertSame('rejected', $result['status']);
        $this->assertSame('server', $result['response_raw']['order_kind']);
    }

    public function test_async_provider_ack_without_remote_id_is_put_on_manual_hold(): void
    {
        $gsmhub = Mockery::mock(GsmhubOrderGateway::class);
        $gsmhub->shouldReceive('placeImeiOrder')->once()->andReturn([
            'ok' => true,
            'retryable' => false,
            'status' => 'inprogress',
            'remote_id' => null,
            'request' => ['endpoint' => 'https://provider.test/api'],
            'response_raw' => ['SUCCESS' => [['MESSAGE' => 'Order received']]],
            'response_ui' => ['type' => 'success', 'message' => 'Order submitted'],
        ]);

        $provider = new ApiProvider(['type' => 'gsmhub']);
        $result = $this->sender(['gsmhub' => $gsmhub])->sendImei($provider, new ImeiOrder());

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['retryable']);
        $this->assertSame('waiting', $result['status']);
        $this->assertTrue($result['request']['dispatch_hold']);
        $this->assertSame('provider_ack_without_remote_id', $result['request']['contract_error']);
    }

    public function test_synchronous_simple_link_success_is_allowed_without_remote_id(): void
    {
        $simple = Mockery::mock(SimpleLinkOrderGateway::class);
        $simple->shouldReceive('placeImeiOrder')->once()->andReturn([
            'ok' => true,
            'retryable' => false,
            'status' => 'success',
            'remote_id' => null,
            'request' => ['url' => 'https://provider.test/link'],
            'response_raw' => ['raw' => 'Registered'],
            'response_ui' => ['type' => 'success', 'message' => 'Registered'],
        ]);

        $provider = new ApiProvider(['type' => 'simple_link']);
        $result = $this->sender(['simple_link' => $simple])->sendImei($provider, new ImeiOrder());

        $this->assertTrue($result['ok']);
        $this->assertSame('success', $result['status']);
        $this->assertNull($result['remote_id']);
    }

    public function test_non_smm_provider_cannot_be_used_for_smm_orders(): void
    {
        $provider = new ApiProvider(['type' => 'webx']);
        $result = $this->sender()->sendSmm($provider, new SmmOrder());

        $this->assertFalse($result['ok']);
        $this->assertSame('rejected', $result['status']);
        $this->assertSame('smm', $result['response_raw']['order_kind']);
    }
}
