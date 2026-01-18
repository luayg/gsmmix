<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;

interface ProviderAdapterInterface
{
    /** اسم النوع: dhru/webx/gsmhub/unlockbase/simple_link */
    public function type(): string;

    /** هل يدعم كتالوج خدمات لهذا النوع (imei/server/file) */
    public function supportsCatalog(string $serviceType): bool;

    /** إحضار الرصيد (إن كان مدعومًا) */
    public function fetchBalance(ApiProvider $provider): float;

    /**
     * مزامنة الكتالوج وملء جداول remote_*.
     * يرجع عدد السجلات التي تم إضافتها/تحديثها.
     */
    public function syncCatalog(ApiProvider $provider, string $serviceType): int;
}
