<?php

namespace App\Services\Orders;

final class SmmPricingException extends \RuntimeException
{
    public function __construct(private array $validationErrors)
    {
        parent::__construct('SMM pricing validation failed.');
    }

    public function errors(): array
    {
        return $this->validationErrors;
    }
}
