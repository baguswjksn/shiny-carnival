<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Business-rule exception rendered as JSON: {success:false, message, reason}. */
abstract class ApiException extends RuntimeException
{
    protected int $status = 400;
    protected string $reason = 'error';

    public function render($request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'reason'  => $this->reason,
        ], $this->status);
    }
}
