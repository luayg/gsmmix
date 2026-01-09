<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use App\Services\Api\DhruClient;

class SyncDhruServices extends Command
{
    /**
     * استخدم:
     *  php artisan dhru:sync                => يزامن كل المزوّدين النشطين من نوع dhru
     *  php artisan dhru:sync --provider=2   => يزامن مزوّد واحد ID=2
     *  php artisan dhru:sync --provider=2 --provider=5
     *  php artisan dhru:sync --provider=all => صراحةً "all"
     */
    protected $signature = 'dhru:sync {--provider=* : Provider IDs (or "all") to sync}';
    protected $description = 'Sync DHRU services (IMEI/Server/File) into remote_* tables (per provider), with pruning';

    public function handle(): int
    {
        $rawOpt = (array) $this->option('provider');
        $wantAll = empty($rawOpt) || in_array('all', $rawOpt, true);

        $ids = collect($rawOpt)
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int) $v)
            ->values()
            ->all();

        $providers = ApiProvider::query()
            ->where('type', 'dhru')
            ->where('active', 1)
            ->when(!$wantAll && !empty($ids), fn($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->get();

        if ($providers->isEmpty()) {
            $this->warn('No active DHRU providers to sync.');
            return self::SUCCESS;
        }

        foreach ($providers as $p) {
            $this->info("Syncing provider #{$p->id} — {$p->name}");

            $client = new DhruClient($p->url, (string)$p->username, (string)$p->api_key);

            try {
                DB::transaction(function () use ($p, $client) {

                    // ========= RAW (IMEI + SERVER) =========
                    $raw  = $client->allServicesRaw();
                    $succ = $raw['SUCCESS'][0] ?? ($raw['SUCCESS'] ?? []);
                    $list = $succ['LIST'] ?? [];

                    // ---------- IMEI ----------
                    if ($p->sync_imei) {
                        $imeiRows = [];
                        foreach ($list as $groupName => $group) {
                            $services = $group['SERVICES'] ?? [];
                            $gName    = $group['GROUPNAME'] ?? (is_string($groupName) ? $groupName : '');
                            foreach ($services as $srv) {
                                if (strtoupper($srv['SERVICETYPE'] ?? '') !== 'IMEI') continue;

                                $imeiRows[] = [
                                    'api_id'            => $p->id,
                                    'remote_id'         => (string)($srv['SERVICEID'] ?? ''),
                                    'name'              => (string)($srv['SERVICENAME'] ?? ''),
                                    'group_name'        => $gName,
                                    'price'             => (float)($srv['CREDIT'] ?? 0),
                                    'time'              => (string)($srv['TIME'] ?? ''),
                                    'info'              => (string)($srv['INFO'] ?? ''),
                                    'min_qty'           => isset($srv['MINQNT']) ? (string)$srv['MINQNT'] : null,
                                    'max_qty'           => isset($srv['MAXQNT']) ? (string)$srv['MAXQNT'] : null,
                                    'credit_groups'     => isset($srv['CREDITGROUPS']) ? json_encode($srv['CREDITGROUPS']) : null,
                                    'additional_fields' => isset($srv['ADDFIELDS'])    ? json_encode($srv['ADDFIELDS'])    : null,
                                    'additional_data'   => isset($srv['ADDDATA'])      ? json_encode($srv['ADDDATA'])      : null,
                                    'params'            => isset($srv['PARAMS'])       ? json_encode($srv['PARAMS'])       : null,
                                    // Flags
                                    'network'           => (int)!!($srv['Requires.Network']   ?? 0),
                                    'mobile'            => (int)!!($srv['Requires.Mobile']    ?? 0),
                                    'provider'          => (int)!!($srv['Requires.Provider']  ?? 0),
                                    'pin'               => (int)!!($srv['Requires.PIN']       ?? 0),
                                    'kbh'               => (int)!!($srv['Requires.KBH']       ?? 0),
                                    'mep'               => (int)!!($srv['Requires.MEP']       ?? 0),
                                    'prd'               => (int)!!($srv['Requires.PRD']       ?? 0),
                                    'type'              => (int)!!($srv['Requires.Type']      ?? 0),
                                    'locks'             => (int)!!($srv['Requires.Locks']     ?? 0),
                                    'reference'         => (int)!!($srv['Requires.Reference'] ?? 0),
                                    'udid'              => (int)!!($srv['Requires.UDID']      ?? 0),
                                    'serial'            => (int)!!($srv['Requires.SN']        ?? 0),
                                    'secro'             => (int)!!($srv['Requires.SecRO']     ?? 0),
                                ];
                            }
                        }

                        // prune: احذف القديم ثم أدخل الجديد (أبسط وأنظف)
                        RemoteImeiService::where('api_id', $p->id)->delete();
                        if (!empty($imeiRows)) {
                            // أدخل على دفعات لتفادي حدود الباص
                            foreach (array_chunk($imeiRows, 1000) as $chunk) {
                                RemoteImeiService::insert($chunk);
                            }
                        }
                    }

                    // ---------- SERVER ----------
                    if ($p->sync_server) {
                        $serverRows = [];
                        foreach ($list as $groupName => $group) {
                            $services = $group['SERVICES'] ?? [];
                            $gName    = $group['GROUPNAME'] ?? (is_string($groupName) ? $groupName : '');
                            foreach ($services as $srv) {
                                if (strtoupper($srv['SERVICETYPE'] ?? '') !== 'SERVER') continue;

                                $serverRows[] = [
                                    'api_id'            => $p->id,
                                    'remote_id'         => (string)($srv['SERVICEID'] ?? ''),
                                    'name'              => (string)($srv['SERVICENAME'] ?? ''),
                                    'group_name'        => $gName,
                                    'price'             => (float)($srv['CREDIT'] ?? 0),
                                    'time'              => (string)($srv['TIME'] ?? ''),
                                    'info'              => (string)($srv['INFO'] ?? ''),
                                    'min_qty'           => isset($srv['MINQNT']) ? (string)$srv['MINQNT'] : null,
                                    'max_qty'           => isset($srv['MAXQNT']) ? (string)$srv['MAXQNT'] : null,
                                    'credit_groups'     => isset($srv['CREDITGROUPS']) ? json_encode($srv['CREDITGROUPS']) : null,
                                    'additional_fields' => isset($srv['ADDFIELDS'])    ? json_encode($srv['ADDFIELDS'])    : null,
                                    'additional_data'   => isset($srv['ADDDATA'])      ? json_encode($srv['ADDDATA'])      : null,
                                    'params'            => isset($srv['PARAMS'])       ? json_encode($srv['PARAMS'])       : null,
                                ];
                            }
                        }

                        RemoteServerService::where('api_id', $p->id)->delete();
                        if (!empty($serverRows)) {
                            foreach (array_chunk($serverRows, 1000) as $chunk) {
                                RemoteServerService::insert($chunk);
                            }
                        }
                    }

                    // ---------- FILE ----------
                    if ($p->sync_file) {
                        $fileRaw = $client->fileServicesRaw();
                        $succF   = $fileRaw['SUCCESS'][0] ?? ($fileRaw['SUCCESS'] ?? []);
                        $listF   = $succF['LIST'] ?? [];

                        $fileRows = [];
                        foreach ($listF as $groupName => $group) {
                            $services = $group['SERVICES'] ?? [];
                            $gName    = $group['GROUPNAME'] ?? (is_string($groupName) ? $groupName : '');
                            foreach ($services as $srv) {
                                $fileRows[] = [
                                    'api_id'              => $p->id,
                                    'remote_id'           => (string)($srv['SERVICEID'] ?? ''),
                                    'name'                => (string)($srv['SERVICENAME'] ?? ''),
                                    'group_name'          => $gName,
                                    'price'               => (float)($srv['CREDIT'] ?? 0),
                                    'time'                => (string)($srv['TIME'] ?? ''),
                                    'info'                => (string)($srv['INFO'] ?? ''),
                                    'allowed_extensions'  => (string)($srv['ALLOW_EXTENSION'] ?? ''),
                                    'additional_fields'   => isset($srv['ADDFIELDS']) ? json_encode($srv['ADDFIELDS']) : null,
                                    'additional_data'     => isset($srv['ADDDATA'])   ? json_encode($srv['ADDDATA'])   : null,
                                    'params'              => isset($srv['PARAMS'])    ? json_encode($srv['PARAMS'])    : null,
                                ];
                            }
                        }

                        RemoteFileService::where('api_id', $p->id)->delete();
                        if (!empty($fileRows)) {
                            foreach (array_chunk($fileRows, 1000) as $chunk) {
                                RemoteFileService::insert($chunk);
                            }
                        }
                    }
                });

                // علِّم أنه تمت مزامنته
                $p->forceFill(['synced' => 1])->saveQuietly();

            } catch (\Throwable $e) {
                Log::error('dhru:sync failed', [
                    'provider' => $p->id,
                    'msg' => $e->getMessage()
                ]);
                $this->error("Provider #{$p->id} failed: ".$e->getMessage());
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
