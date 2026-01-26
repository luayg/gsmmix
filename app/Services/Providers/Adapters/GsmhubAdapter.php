<?php

namespace App\Services\Providers\Adapters;

class GsmhubAdapter extends DhruStyleAdapter
{
    public function type(): string
    {
        return 'gsmhub';
    }
}
