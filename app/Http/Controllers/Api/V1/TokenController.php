<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->select('id', 'name', 'abilities', 'last_used_at', 'created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string|in:read,write',
        ]);

        $token = $request->user()->createToken(
            name: $data['name'],
            abilities: $data['abilities'] ?? ['read'],
        );

        return response()->json([
            'data' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
                // Plain-text token is shown ONCE at creation time and never again.
                'plain_text' => $token->plainTextToken,
            ],
        ], 201);
    }

    public function destroy(Request $request, int $id): Response
    {
        $deleted = $request->user()->tokens()->whereKey($id)->delete();
        abort_unless($deleted > 0, 404);

        return response()->noContent();
    }
}
