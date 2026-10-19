<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Support\Api\TokenAbilities;
use App\Support\Demo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin-only (route gate `manage-api-tokens` + token ability tokens:manage).
 */
class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Collection<int, PersonalAccessToken> $tokens */
        $tokens = $request->user()->tokens()->orderByDesc('id')->get();

        $tokens = $tokens->map(fn (PersonalAccessToken $t): array => [
            'id' => $t->id,
            'name' => $t->name,
            'abilities' => $t->abilities,
            'tenant_ids' => $t->tenant_ids,
            'expires_at' => $t->expires_at?->toIso8601String(),
            'last_used_at' => $t->last_used_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if(Demo::enabled(), 403, __('demo.tokens_locked'));

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'abilities' => 'nullable|array',
            'abilities.*' => ['string', Rule::in(TokenAbilities::grantable())],
            'tenant_ids' => 'nullable|array',
            'tenant_ids.*' => 'integer|exists:tenants,id',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $abilities = TokenAbilities::normalize($data['abilities'] ?? TokenAbilities::readOnly());
        $tenantIds = isset($data['tenant_ids']) ? array_values(array_unique(array_map('intval', $data['tenant_ids']))) : null;

        $this->ensureWithinCaller($request, $abilities, $tenantIds);

        $token = $request->user()->createToken(
            name: $data['name'],
            abilities: $abilities,
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
        );
        $token->accessToken->forceFill(['tenant_ids' => $tenantIds])->save();

        return response()->json([
            'data' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
                'tenant_ids' => $tenantIds,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
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

    /**
     * A token may only mint tokens narrower than itself, otherwise a limited
     * token holding tokens:manage could escalate to full access.
     *
     * @param  list<string>  $abilities
     * @param  list<int>|null  $tenantIds
     */
    private function ensureWithinCaller(Request $request, array $abilities, ?array $tenantIds): void
    {
        $caller = $request->user()->currentAccessToken();
        if (! $caller instanceof PersonalAccessToken) {
            return;
        }

        $extra = array_values(array_filter($abilities, fn (string $a): bool => ! $caller->can($a)));
        if ($extra !== []) {
            throw ValidationException::withMessages([
                'abilities' => 'Abilities not held by the calling token: '.implode(', ', $extra),
            ]);
        }

        if ($caller->tenant_ids !== null
            && ($tenantIds === null || array_diff($tenantIds, array_map('intval', $caller->tenant_ids)) !== [])) {
            throw ValidationException::withMessages([
                'tenant_ids' => 'Tenants not allowed to the calling token.',
            ]);
        }
    }
}
