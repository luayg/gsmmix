<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class UsdtPaymentScanner
{
    public function scan(PaymentGateway $gateway): int
    {
        if ($gateway->driver !== 'usdt' || !$gateway->active) return 0;
        return data_get($gateway->config,'network') === 'TRC20' ? $this->tron($gateway) : $this->bsc($gateway);
    }

    private function tron(PaymentGateway $gateway): int
    {
        $c = $gateway->credentials ?? [];
        $url = rtrim($c['provider_url'] ?? '', '/').'/v1/accounts/'.$c['wallet_address'].'/transactions/trc20';
        $request = Http::acceptJson()->timeout(20)->retry(2,500);
        if (!empty($c['provider_api_key'])) $request = $request->withHeaders(['TRON-PRO-API-KEY'=>$c['provider_api_key']]);
        $rows = $request->get($url, ['only_confirmed'=>'true','limit'=>200,'contract_address'=>$c['contract_address'],'min_timestamp'=>now()->subHours(2)->getTimestampMs()])->throw()->json('data', []);
        $settled = 0;
        foreach ($rows as $row) {
            if (($row['to'] ?? '') !== $c['wallet_address']) continue;
            $decimals = (int) data_get($row,'token_info.decimals',6);
            $amount = bcdiv((string) ($row['value'] ?? '0'), bcpow('10',(string)$decimals,0), 8);
            $settled += $this->match($gateway, $amount, (string) ($row['transaction_id'] ?? ''), $row);
        }
        return $settled;
    }

    private function bsc(PaymentGateway $gateway): int
    {
        $c = $gateway->credentials ?? [];
        $rpc = (string) ($c['provider_url'] ?? '');
        $currentHex = Http::timeout(20)->post($rpc, ['jsonrpc'=>'2.0','id'=>1,'method'=>'eth_blockNumber','params'=>[]])->throw()->json('result');
        $current = hexdec((string) $currentHex);
        $config = $gateway->config ?? [];
        $from = (int) ($config['last_scanned_block'] ?? max(0,$current-5000));
        $toTopic = '0x'.str_pad(strtolower(ltrim((string) $c['wallet_address'],'0x')),64,'0',STR_PAD_LEFT);
        $response = Http::timeout(30)->post($rpc, ['jsonrpc'=>'2.0','id'=>2,'method'=>'eth_getLogs','params'=>[0=>[
            'fromBlock'=>'0x'.dechex($from), 'toBlock'=>'latest', 'address'=>$c['contract_address'],
            'topics'=>['0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',null,$toTopic],
        ]]])->throw()->json();
        if (isset($response['error'])) throw new RuntimeException((string) data_get($response,'error.message','BSC RPC error'));
        $settled = 0;
        $confirmations = (int) data_get($gateway->config,'confirmations',12);
        foreach ($response['result'] ?? [] as $row) {
            $block = hexdec((string) ($row['blockNumber'] ?? '0x0'));
            if (($current - $block + 1) < $confirmations) continue;
            $amount = bcdiv($this->hexToDecimal((string) ($row['data'] ?? '0x0')), bcpow('10','18',0), 8);
            $settled += $this->match($gateway, $amount, (string) ($row['transactionHash'] ?? ''), $row);
        }
        $config['last_scanned_block'] = max($from, $current-$confirmations+1);
        $gateway->forceFill(['config'=>$config])->save();
        return $settled;
    }

    private function match(PaymentGateway $gateway, string $amount, string $txid, array $raw): int
    {
        if (!$txid) return 0;
        $payment = PaymentTransaction::query()->where('payment_gateway_id',$gateway->id)->where('status','pending')->where('payable_currency',$amount)->where('created_at','>=',now()->subHours(2))->first();
        if (!$payment) return 0;
        app(PaymentSettlement::class)->paid($payment,$txid,['network'=>data_get($gateway->config,'network'),'transaction'=>$raw]);
        return 1;
    }

    private function hexToDecimal(string $hex): string
    {
        $hex = ltrim(strtolower($hex),'0x'); $value = '0';
        foreach (str_split($hex ?: '0') as $digit) $value = bcadd(bcmul($value,'16',0),(string) hexdec($digit),0);
        return $value;
    }
}
