<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
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

    public function render(Request $request)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'message' => $this->getMessage(),
                'provider_id' => $this->providerId,
                'active_orders' => $this->activeOrderCount,
            ], 409);
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
