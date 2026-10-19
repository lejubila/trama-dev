<?php

declare(strict_types=1);

namespace App\Services\OAuth;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An RFC 6749 error ("invalid_grant", "invalid_client"…), rendered as the
 * JSON body OAuth clients expect.
 */
class OAuthException extends RuntimeException
{
    public function __construct(public readonly string $error, string $description = '', public readonly int $status = 400)
    {
        parent::__construct($description);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'error' => $this->error,
            'error_description' => $this->getMessage(),
        ]), $this->status, ['Cache-Control' => 'no-store']);
    }
}
