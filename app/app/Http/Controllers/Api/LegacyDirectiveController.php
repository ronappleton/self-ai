<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegacyDirective;
use App\Support\Legacy\LegacyDirectiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyDirectiveController extends Controller
{
    public function __construct(private readonly LegacyDirectiveService $service)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('owner')) {
            abort(Response::HTTP_FORBIDDEN, 'Only the owner may manage the legacy directive.');
        }

        $validated = $request->validate([
            'beneficiaries' => ['sometimes', 'array'],
            'beneficiaries.*.name' => ['required_with:beneficiaries', 'string', 'max:120'],
            'beneficiaries.*.relationship' => ['nullable', 'string', 'max:120'],
            'beneficiaries.*.contact' => ['nullable', 'string', 'max:160'],
            'beneficiaries.*.notes' => ['nullable', 'string', 'max:500'],
            'topics' => ['sometimes', 'array'],
            'topics.allow' => ['sometimes', 'array'],
            'topics.allow.*' => ['string', 'max:120'],
            'topics.deny' => ['sometimes', 'array'],
            'topics.deny.*' => ['string', 'max:120'],
            'duration' => ['sometimes', 'array'],
            'duration.starts_at' => ['nullable', 'date'],
            'duration.ends_at' => ['nullable', 'date'],
            'duration.max_session_minutes' => ['nullable', 'integer', 'min:0'],
            'duration.max_total_hours' => ['nullable', 'integer', 'min:0'],
            'rate_limits' => ['sometimes', 'array'],
            'rate_limits.requests_per_day' => ['nullable', 'integer', 'min:0'],
            'rate_limits.concurrent_sessions' => ['nullable', 'integer', 'min:0'],
            'rate_limits.cooldown_hours' => ['nullable', 'integer', 'min:0'],
            'unlock_policy' => ['sometimes', 'array'],
            'unlock_policy.executor' => ['nullable', 'array'],
            'unlock_policy.executor.name' => ['required_with:unlock_policy.executor', 'string', 'max:120'],
            'unlock_policy.executor.contact' => ['nullable', 'string', 'max:160'],
            'unlock_policy.proofs_required' => ['nullable', 'array'],
            'unlock_policy.proofs_required.*' => ['string', 'max:120'],
            'unlock_policy.time_delay_hours' => ['nullable', 'integer', 'min:0'],
            'unlock_policy.passphrase_hint' => ['nullable', 'string', 'max:160'],
            'unlock_policy.panic_contact' => ['nullable', 'string', 'max:160'],
            'passphrase' => ['nullable', 'string', 'min:8'],
            'reactivate' => ['sometimes', 'boolean'],
        ]);

        $payload = $validated;

        if (array_key_exists('passphrase', $validated) && $validated['passphrase'] === null) {
            unset($payload['passphrase']);
        }

        $this->service->upsert($user, $payload);

        return response()->json([
            'directive' => $this->service->export($user),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('owner')) {
            abort(Response::HTTP_FORBIDDEN, 'Only the owner may view the legacy directive.');
        }

        return response()->json([
            'directive' => $this->service->export($user),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('owner')) {
            abort(Response::HTTP_FORBIDDEN, 'Only the owner may erase the legacy directive.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:160'],
        ]);

        $this->service->erase($user, $validated['reason'] ?? null);

        return response()->json([
            'directive' => $this->service->export($user),
        ]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'directive_id' => ['required', 'uuid', 'exists:legacy_directives,id'],
            'executor_name' => ['required', 'string', 'max:160'],
            'passphrase' => ['required', 'string'],
            'proof_reference' => ['nullable', 'string', 'max:255'],
            'confirm' => ['sometimes', 'boolean'],
        ]);

        /** @var LegacyDirective $directive */
        $directive = LegacyDirective::query()->findOrFail($validated['directive_id']);

        $result = $this->service->requestUnlock($request->user(), $directive, $validated);

        $status = match ($result['status']) {
            'denied' => Response::HTTP_FORBIDDEN,
            'unavailable' => Response::HTTP_LOCKED,
            'not_found' => Response::HTTP_NOT_FOUND,
            'approved' => Response::HTTP_OK,
            default => Response::HTTP_ACCEPTED,
        };

        return response()->json($result, $status);
    }

    public function panicDisable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'directive_id' => ['required', 'uuid', 'exists:legacy_directives,id'],
            'reason' => ['nullable', 'string', 'max:160'],
        ]);

        /** @var LegacyDirective $directive */
        $directive = LegacyDirective::query()->findOrFail($validated['directive_id']);

        $user = $request->user();

        if (! $user || ! $user->hasRole('owner')) {
            abort(Response::HTTP_FORBIDDEN, 'Only the owner may panic-disable the legacy directive.');
        }

        if ($directive->user_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You may only modify your own legacy directive.');
        }

        $updated = $this->service->panicDisable($user, $directive, $validated['reason'] ?? null);

        return response()->json([
            'directive' => [
                'id' => $updated->id,
                'panic_disabled_at' => optional($updated->panic_disabled_at)?->toIso8601String(),
                'panic_disabled_reason' => $updated->panic_disabled_reason,
            ],
        ]);
    }
}
