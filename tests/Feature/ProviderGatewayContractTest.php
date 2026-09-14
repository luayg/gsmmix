<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\SmmOrder;
use App\Models\SmmService;
use App\Services\Orders\DhruOrderGateway;
use App\Services\Orders\GsmhubOrderGateway;
use App\Services\Orders\SimpleLinkOrderGateway;
use App\Services\Orders\SmmOrderGateway;
use App\Services\Orders\UnlockbaseOrderGateway;
use App\Services\Orders\WebxOrderGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderGatewayContractTest extends TestCase
{
    private function provider(string $type, string $url = 'https://provider.test'): ApiProvider
    {
        return new ApiProvider([
            'name' => strtoupper($type) . ' test',
            'type' => $type,
            'url' => $url,
            'username' => 'test-user',
            'api_key' => 'test-secret-key',
            'active' => 1,
        ]);
    }

    private function imeiOrder(string $remoteService = '101'): ImeiOrder
    {
        $service = new ImeiService(['remote_id' => $remoteService]);
        $order = new ImeiOrder([
            'device' => '356789012345678',
            'comments' => 'contract test',
            'params' => ['fields' => []],
        ]);
        $order->setRelation('service', $service);
        return $order;
    }

    public function test_dhru_place_imei_contract_uses_expected_action_and_reference_id(): void
    {
        Http::fake([
            '*' => Http::response([
                'SUCCESS' => [[
                    'MESSAGE' => 'Order received',
                    'REFERENCEID' => 'DHRU-123',
                ]],
            ], 200),
        ]);

        $result = app(DhruOrderGateway::class)
            ->placeImeiOrder($this->provider('dhru'), $this->imeiOrder());

        $this->assertTrue($result['ok']);
        $this->assertSame('inprogress', $result['status']);
        $this->assertSame('DHRU-123', (string)$result['remote_id']);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            return ($data['action'] ?? null) === 'placeimeiorder'
                && ($data['username'] ?? null) === 'test-user'
                && ($data['apiaccesskey'] ?? null) === 'test-secret-key'
                && str_contains((string)($data['parameters'] ?? ''), '<IMEI>356789012345678</IMEI>')
                && str_contains((string)($data['parameters'] ?? ''), '<ID>101</ID>');
        });
    }

    public function test_webx_place_and_status_contract_without_live_call(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 7788], 200);
            }
            return Http::response(['id' => 7788, 'status' => 4, 'response' => 'DONE'], 200);
        });

        $provider = $this->provider('webx', 'https://webx.test');
        $gateway = app(WebxOrderGateway::class);

        $placed = $gateway->placeImeiOrder($provider, $this->imeiOrder('23'));
        $this->assertTrue($placed['ok']);
        $this->assertSame('7788', (string)$placed['remote_id']);
        $this->assertSame('inprogress', $placed['status']);

        $status = $gateway->getImeiOrder($provider, '7788');
        $this->assertTrue($status['ok']);
        $this->assertSame('success', $status['status']);
        $this->assertSame('7788', (string)$status['remote_id']);
    }

    public function test_gsmhub_place_and_status_contract_without_live_call(): void
    {
        Http::fake(function (Request $request) {
            $action = (string)($request->data()['action'] ?? '');
            if ($action === 'placeimeiorder') {
                return Http::response([
                    'SUCCESS' => [['REFERENCEID' => 'GH-55']],
                ], 200);
            }

            return Http::response([
                'SUCCESS' => [[
                    'STATUS' => 4,
                    'CODE' => 'UNLOCKED',
                ]],
            ], 200);
        });

        $provider = $this->provider('gsmhub', 'https://imei.test/public');
        $gateway = app(GsmhubOrderGateway::class);

        $placed = $gateway->placeImeiOrder($provider, $this->imeiOrder('44'));
        $this->assertTrue($placed['ok']);
        $this->assertSame('GH-55', (string)$placed['remote_id']);

        $status = $gateway->getImeiOrder($provider, 'GH-55');
        $this->assertSame('success', $status['status']);
        $this->assertSame('UNLOCKED', data_get($status, 'response_ui.result_text'));
    }

    public function test_unlockbase_place_and_status_contract_without_live_call(): void
    {
        Http::fake(function (Request $request) {
            $action = (string)($request->data()['Action'] ?? '');
            if ($action === 'PlaceOrder') {
                return Http::response(
                    '<Response><Success>Order placed</Success><ID>UB-90</ID></Response>',
                    200,
                    ['Content-Type' => 'application/xml']
                );
            }

            return Http::response(
                '<Response><Success>OK</Success><Order><ID>UB-90</ID><Status>Delivered</Status><Available>true</Available><Codes>CODE-123</Codes></Order></Response>',
                200,
                ['Content-Type' => 'application/xml']
            );
        });

        $provider = $this->provider('unlockbase', 'https://unlockbase.test');
        $gateway = app(UnlockbaseOrderGateway::class);

        $placed = $gateway->placeImeiOrder($provider, $this->imeiOrder('888'));
        $this->assertTrue($placed['ok']);
        $this->assertSame('UB-90', (string)$placed['remote_id']);

        $status = $gateway->getImeiOrder($provider, 'UB-90');
        $this->assertSame('success', $status['status']);
        $this->assertSame('CODE-123', data_get($status, 'response_ui.result_text'));
    }

    public function test_simple_link_is_synchronous_success_without_remote_id(): void
    {
        Http::fake(['*' => Http::response('Registered', 200)]);

        $provider = $this->provider('simple_link', 'https://simple.test/store.php?api_key=test-link-key');
        $provider->params = ['main_field' => 'imei', 'method' => 'GET'];

        $result = app(SimpleLinkOrderGateway::class)
            ->placeImeiOrder($provider, $this->imeiOrder());

        $this->assertTrue($result['ok']);
        $this->assertSame('success', $result['status']);
        $this->assertNull($result['remote_id']);
        $this->assertSame('Registered', data_get($result, 'response_ui.result_text'));
    }

    public function test_smm_add_and_status_contract_without_live_call(): void
    {
        Http::fake(function (Request $request) {
            $action = (string)($request->data()['action'] ?? '');
            if ($action === 'add') {
                return Http::response(['order' => 4567], 200);
            }
            return Http::response(['status' => 'Completed', 'charge' => '0.1250'], 200);
        });

        $service = new SmmService([
            'remote_id' => '321',
            'params' => ['smm_type' => 'default'],
        ]);
        $order = new SmmOrder([
            'device' => 'https://example.test/post/1',
            'quantity' => 1000,
            'params' => [
                'fields' => [
                    'link' => 'https://example.test/post/1',
                    'quantity' => 1000,
                ],
            ],
        ]);
        $order->setRelation('service', $service);

        $provider = $this->provider('smm', 'https://smm.test/api/v2');
        $gateway = app(SmmOrderGateway::class);

        $placed = $gateway->placeSmmOrder($provider, $order);
        $this->assertTrue($placed['ok']);
        $this->assertSame('4567', (string)$placed['remote_id']);
        $this->assertSame('inprogress', $placed['status']);

        $status = $gateway->getSmmOrderStatus($provider, '4567');
        $this->assertTrue($status['ok']);
        $this->assertSame('success', $status['status']);
        $this->assertSame('completed', $status['provider_status']);
    }
}
