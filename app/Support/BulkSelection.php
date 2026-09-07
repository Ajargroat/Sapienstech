<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Turns a bulk request's selection controls into a concrete, tenant-scoped
 * set of student ids.
 *
 * Two modes, mirroring "by filter and by name":
 *   - explicit: student_ids[] from the checkboxes. Each id is re-checked
 *     against the tenant (BelongsToTenant scope) so a crafted id from
 *     another tenant is silently dropped.
 *   - select_all: the client sends only the filter set; the ids are
 *     re-derived server-side via StudentFilter so the batch covers exactly
 *     what the picker claimed, including rows the client never enumerated.
 *
 * select_all wins when both are present (the "select all matching" affordance
 * is explicit intent to widen beyond the visible checkboxes).
 */
class BulkSelection
{
    /**
     * @return list<int> resolved student ids
     */
    public static function resolve(Request $request): array
    {
        $filters = StudentFilter::fromRequest($request);

        if ($request->boolean('select_all')) {
            return StudentFilter::ids($filters);
        }

        $ids = collect((array) $request->input('student_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        // Re-scope through the tenant: Student::whereIn keeps only ids that
        // belong to the current tenant.
        return \App\Models\Student::query()
            ->whereIn('id', $ids->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
