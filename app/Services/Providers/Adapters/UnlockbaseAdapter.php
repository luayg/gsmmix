<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Services\Providers\ProviderAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class UnlockbaseAdapter implements ProviderAdapterInterface
{
    public function type(): string
    {
        return 'unlockbase';
    }

    public function supportsCatalog(string $kind): bool
    {
        // UnlockBase = IMEI tools/catalog (PlaceOrder/GetOrders etc for IMEI)
        return $kind === 'imei';
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $data = $this->call($provider, 'AccountInfo');

        // Shape: <API><Credits>0.00</Credits>...</API>
        $credits = data_get($data, 'Credits');
        return $this->toFloat($credits);
    }

    public function syncCatalog(ApiProvider $provider, string $kind): int
    {
        if ($kind !== 'imei') {
            return 0;
        }

        // v3 still supports GetTools
        $data = $this->call($provider, 'GetTools');

        $groups = data_get($data, 'Group', []);
        if (!is_array($groups)) $groups = [];

        // Handle single group object case
        if ($groups && array_keys($groups) !== range(0, count($groups) - 1)) {
            $groups = [$groups];
        }

        return DB::transaction(function () use ($provider, $groups) {
            $seen = [];
            $count = 0;

            foreach ($groups as $g) {
                if (!is_array($g)) continue;

                $groupName = (string)($g['Name'] ?? '');
                $groupId   = (string)($g['ID'] ?? '');

                $tools = $g['Tool'] ?? [];
                if (!is_array($tools)) $tools = [];

                // Handle single tool object case
                if ($tools && array_keys($tools) !== range(0, count($tools) - 1)) {
                    $tools = [$tools];
                }

                foreach ($tools as $t) {
                    if (!is_array($t)) continue;

                    $remoteId = (string)($t['ID'] ?? '');
                    if ($remoteId === '') continue;

                    $seen[] = $remoteId;

                    $requires = $this->extractRequires($t);

                    $payload = [
                        'api_provider_id' => $provider->id,
                        'remote_id'       => $remoteId,
                        'name'            => (string)($t['Name'] ?? ''),
                        'group_name'      => $groupName ?: null,
                        'price'           => $this->toFloat($t['Credits'] ?? 0),
                        'time'            => $this->deliveryToText($t),
                        'info'            => $this->cleanStr($t['Message'] ?? null),

                        // Flags (match your DB casts in RemoteImeiService)
                        'network'   => $requires['network'],
                        'mobile'    => $requires['mobile'],
                        'provider'  => $requires['provider'],
                        'pin'       => $requires['pin'],
                        'kbh'       => $requires['kbh'],
                        'mep'       => $requires['mep'],
                        'prd'       => $requires['prd'],
                        'type'      => $requires['type'],
                        'locks'     => $requires['locks'],
                        'reference' => false,
                        'udid'      => false,
                        'serial'    => false,
                        'secro'     => false,

                        // Keep raw
                        'additional_data' => $t,
                        'params' => [
                            'group_id'   => $groupId ?: null,
                            'group_name' => $groupName ?: null,
                            'sms'        => $this->toBool($t['SMS'] ?? null),
                            'delivery'   => [
                                'min'  => $t['Delivery.Min'] ?? null,
                                'max'  => $t['Delivery.Max'] ?? null,
                                'unit' => $t['Delivery.Unit'] ?? null,
                            ],
                        ],

                        // Synthesized additional fields for UI/forms (Optional/Required)
                        'additional_fields' => $this->buildAdditionalFields($t),
                    ];

                    RemoteImeiService::updateOrCreate(
                        ['api_provider_id' => $provider->id, 'remote_id' => $remoteId],
                        $payload
                    );

                    $count++;
                }
            }

            // Cleanup removed services
            if (!empty($seen)) {
                RemoteImeiService::where('api_provider_id', $provider->id)
                    ->whereNotIn('remote_id', $seen)
                    ->delete();
            }

            return $count;
        });
    }

    /* ============================
     * HTTP + XML helpers
     * ============================ */

    private function endpoint(ApiProvider $p): string
    {
        // Allow override from admin: provider.params.endpoint
        $ep = $p->params['endpoint'] ?? null;
        if (is_string($ep) && trim($ep) !== '') return trim($ep);

        // Default: UnlockBase v3 (per v3.2 doc)
        return 'https://www.unlockbase.com/xml/api/v3';
    }

    private function resolveApiKey(ApiProvider $p): string
    {
        $key = trim((string)($p->api_key ?? ''));

        // Fallback: some admins may paste the key into username by mistake
        if ($key === '') {
            $maybe = trim((string)($p->username ?? ''));
            if ($this->looksLikeUnlockbaseKey($maybe)) {
                $key = $maybe;
            }
        }

        // Normalize: remove surrounding parentheses if present: "(XXXX-...)" -> "XXXX-..."
        $key = trim($key);
        $key = preg_replace('/^\((.+)\)$/', '$1', $key) ?? $key;

        return trim($key);
    }

    private function looksLikeUnlockbaseKey(string $s): bool
    {
        $s = trim($s);
        $s = preg_replace('/^\((.+)\)$/', '$1', $s) ?? $s;
        return (bool)preg_match('/^[0-9A-Fa-f]{4}\-[0-9A-Fa-f]{4}\-[0-9A-Fa-f]{4}\-[0-9A-Fa-f]{4}$/', trim($s));
    }

    private function call(ApiProvider $provider, string $action, array $params = []): array
    {
        $apiKey = $this->resolveApiKey($provider);

        $payload = array_merge([
            'Key'    => $apiKey,
            'Action' => $action,
        ], $params);

        $resp = Http::asForm()
            ->timeout(60)
            ->retry(2, 500)
            ->post($this->endpoint($provider), $payload);

        $body = (string)$resp->body();
        $xml  = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            throw new \RuntimeException("UnlockBase: Invalid XML response for action={$action}");
        }

        $arr = json_decode(json_encode($xml), true);
        if (!is_array($arr)) $arr = [];

        // Error handling: <Error>...</Error>
        $err = $arr['Error'] ?? null;
        if (is_string($err) && trim($err) !== '') {
            throw new \RuntimeException(trim($err));
        }

        return $arr;
    }

    private function extractRequires(array $tool): array
    {
        $map = function ($v): string {
            return strtolower(trim((string)$v));
        };

        $reqNetwork  = $map($tool['Requires.Network'] ?? 'none');
        $reqMobile   = $map($tool['Requires.Mobile'] ?? 'none');
        $reqProvider = $map($tool['Requires.Provider'] ?? 'none');
        $reqPin      = $map($tool['Requires.PIN'] ?? 'none');
        $reqKbh      = $map($tool['Requires.KBH'] ?? 'none');
        $reqMep      = $map($tool['Requires.MEP'] ?? 'none');
        $reqPrd      = $map($tool['Requires.PRD'] ?? 'none');
        $reqType     = $map($tool['Requires.Type'] ?? 'none');

        $locks = (int)($tool['Requires.Locks'] ?? 0);

        $needs = fn(string $v): bool => in_array($v, ['required', 'optional'], true);

        return [
            'network'  => $needs($reqNetwork),
            'mobile'   => $needs($reqMobile),
            'provider' => $needs($reqProvider),
            'pin'      => $needs($reqPin),
            'kbh'      => $needs($reqKbh),
            'mep'      => $needs($reqMep),
            'prd'      => $needs($reqPrd),
            'type'     => $needs($reqType),
            'locks'    => $locks > 0,
        ];
    }

    private function buildAdditionalFields(array $tool): array
    {
        $fields = [];

        $mk = function (string $name, string $type, bool $required, array $extra = []) use (&$fields) {
            $fields[] = array_merge([
                'name' => $name,
                'type' => $type,
                'required' => $required,
                'label' => $name,
            ], $extra);
        };

        $needLevel = function ($v): string {
            return strtolower(trim((string)$v));
        };
        $isReq = fn(string $lvl): bool => $lvl === 'required';
        $isOptOrReq = fn(string $lvl): bool => in_array($lvl, ['required', 'optional'], true);

        $n = $needLevel($tool['Requires.Network'] ?? 'none');
        if ($isOptOrReq($n)) $mk('network', 'select', $isReq($n));

        $m = $needLevel($tool['Requires.Mobile'] ?? 'none');
        if ($isOptOrReq($m)) $mk('mobile', 'select', $isReq($m));

        $p = $needLevel($tool['Requires.Provider'] ?? 'none');
        if ($isOptOrReq($p)) $mk('provider', 'text', $isReq($p));

        $pin = $needLevel($tool['Requires.PIN'] ?? 'none');
        if ($isOptOrReq($pin)) $mk('pin', 'text', $isReq($pin));

        $kbh = $needLevel($tool['Requires.KBH'] ?? 'none');
        if ($isOptOrReq($kbh)) $mk('kbh', 'text', $isReq($kbh));

        $mep = $needLevel($tool['Requires.MEP'] ?? 'none');
        if ($isOptOrReq($mep)) $mk('mep', 'text', $isReq($mep));

        $prd = $needLevel($tool['Requires.PRD'] ?? 'none');
        if ($isOptOrReq($prd)) $mk('prd', 'text', $isReq($prd));

        $type = $needLevel($tool['Requires.Type'] ?? 'none');
        if ($isOptOrReq($type)) $mk('type', 'text', $isReq($type));

        $locks = (int)($tool['Requires.Locks'] ?? 0);
        if ($locks > 0) {
            $mk('locks', 'text', true, ['note' => "expects {$locks} locks (NCK, NSCK, SPK, CCK, ESL)"]);
        }

        return $fields;
    }

    private function deliveryToText(array $tool): ?string
    {
        $min  = $tool['Delivery.Min'] ?? null;
        $max  = $tool['Delivery.Max'] ?? null;
        $unit = $tool['Delivery.Unit'] ?? null;

        $txt = trim((string)$min) . '-' . trim((string)$max) . ' ' . trim((string)$unit);
        $txt = trim($txt, "- \t\n\r\0\x0B");
        return $txt === '' ? null : $txt;
    }

    private function toFloat($value): float
    {
        if ($value === null) return 0.0;
        if (is_int($value) || is_float($value)) return (float)$value;

        $s = trim((string)$value);
        $s = str_replace([',', '$', 'USD', 'usd', ' '], '', $s);
        $s = preg_replace('/[^0-9\.\-]/', '', $s) ?? '';
        return is_numeric($s) ? (float)$s : 0.0;
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) return $value;
        $v = strtolower(trim((string)$value));
        return in_array($v, ['1', 'true', 'yes'], true);
    }

    private function cleanStr($value): ?string
    {
        $s = trim((string)($value ?? ''));
        return $s === '' ? null : $s;
    }
}