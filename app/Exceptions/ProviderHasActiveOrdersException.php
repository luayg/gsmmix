<?php

namespace App\Exceptions;

use RuntimeException;

class ProviderHasActiveOrdersException extends RuntimeException
{
    public function __construct(
        public readonly int $providerId,
        public readonly int $activeOrderCount,
    ) {
        parent::__construct(
            "Cannot delete provider #{$providerId}: {$activeOrderCount} active/in-progress order(s) still depend on it. Finish or resolve those orders first."
        );
    }
}
