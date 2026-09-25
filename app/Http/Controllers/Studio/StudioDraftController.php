<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\StudioDraftRequest;
use App\Models\StudioDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * THE single Theme Studio draft per tenant+user (Prompts §6: nameable,
 * inspectable, restorable, separate from publish, persistent).
 *
 * Endpoints are JSON only — they carry the snapshot around; nothing here
 * merges it into the site. Restoring is the client re-submitting the payload
 * to studio.save with scope=preview, so the working (session) layer is the
 * only thing a restore can ever touch.
 */
class StudioDraftController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['draft' => $this->toArray($this->find($request))]);
    }

    public function store(StudioDraftRequest $request): JsonResponse
    {
        if ($this->find($request) !== null) {
            return response()->json([
                'message' => 'You can keep only one draft; update or delete the current one.',
                'errors' => ['name' => ['You can keep only one draft.']],
            ], 422);
        }

        $draft = new StudioDraft($request->safe()->only(['name', 'payload']));
        $draft->tenant_id = tenant()->id;
        $draft->user_id = $request->user()->id;
        $draft->save();

        return response()->json(['draft' => $this->toArray($draft)], 201);
    }

    public function update(StudioDraftRequest $request): JsonResponse
    {
        $draft = $this->find($request);

        if ($draft === null) {
            return response()->json(['message' => 'No draft exists.'], 404);
        }

        // safe()->only carries just the present keys — rename without a
        // payload keeps the checkpoint, and vice versa.
        $draft->fill($request->safe()->only(['name', 'payload']));
        $draft->save();

        return response()->json(['draft' => $this->toArray($draft)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->find($request)?->delete();

        return response()->json(['success' => true]);
    }

    private function find(Request $request): ?StudioDraft
    {
        return StudioDraft::query()
            ->where('tenant_id', tenant()->id)
            ->where('user_id', $request->user()->id)
            ->first();
    }

    private function toArray(?StudioDraft $draft): ?array
    {
        if ($draft === null) {
            return null;
        }

        return [
            'name' => $draft->name,
            'payload' => $draft->payload,
            'updated_at' => $draft->updated_at->toIso8601String(),
        ];
    }
}
