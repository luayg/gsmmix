<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;

class ServiceHasOrdersException extends RuntimeException
{
    public function __construct(
        public readonly string $kind,
        public readonly int $serviceId,
        public readonly int $orderCount,
    ) {
        parent::__construct(
            "Cannot delete {$kind} service #{$serviceId}: {$orderCount} historical order(s) reference it. Deactivate the service instead."
        );
    }

    public function render(Request $request)
    {
        $message = $this->getMessage();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'service_id' => $this->serviceId,
                'service_type' => $this->kind,
                'linked_orders' => $this->orderCount,
            ], 409);
        }

        return redirect()->back()->with('error', $message);
    }
}
