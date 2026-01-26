<?php

namespace App\Exceptions;

use RuntimeException;

class ProviderApiException extends RuntimeException
{
    public array $response;
    public string $providerType;
    public string $action;

    public function __construct(
        string $providerType,
        string $action,
        array $response = [],
        string $message = 'Provider API error',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->providerType = $providerType;
        $this->action = $action;
        $this->response = $response;
    }
}
