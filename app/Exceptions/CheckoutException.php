<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class CheckoutException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors  keyed like validation errors, e.g. "items.12"
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => $this->errors ?: ['cart' => [$this->getMessage()]],
        ], 422);
    }
}
