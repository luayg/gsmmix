<?php

namespace App\Services\Providers\Adapters;

class DhruAdapter extends DhruStyleAdapter
{
    public function type(): string
    {
        return 'dhru';
    }
}
