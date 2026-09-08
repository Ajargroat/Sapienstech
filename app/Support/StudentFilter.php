<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The tenant's student filter vocabulary in one place.
 *
 * Extracted from ConsultantDashboardController so the dashboard, the bulk
 * actions picker, and the bulk "apply to all matching" server-side re-query
 * all interpret search/grade/gender/major/sort identically. The bulk flows
 * in particular must re-apply filters server-side — a client-supplied
 * "select all" can never be trusted to enumerate ids.
 */
class StudentFilter
{
    public const SORTS = ['name_asc', 'name_desc', 'newest', 'oldest'];

    /** @return array{search:string,grade:string,gender:string,major:string,sort:string} */
    public static function fromRequest(Request $request): array
    {
        // input() spans query string, POST body and JSON alike, so the same
        // reader serves the dashboard's GET filters and the bulk form's
        // hidden POST fields.
        $sort = (string) $request->input('sort', '');

        if (! in_array($sort, self::SORTS, true)) {
            $sort = '';
        }

        return [
            'search' => trim((string) $request->input('search', '')),
            'grade' => trim((string) $request->input('grade', '')),
            'gender' => trim((string) $request->input('gender', '')),
            'major' => trim((string) $request->input('major', '')),
            'sort' => $sort,
        ];
    }

    /**
     * Apply a normalized filter set to a Student query.
     *
     * @param  array<string, string>  $filters
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $query
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(trim((string) ($filters['grade'] ?? '')) !== '', fn ($q) => $q->where('grade', trim((string) $filters['grade'])))
            ->when(trim((string) ($filters['gender'] ?? '')) !== '', fn ($q) => $q->where('gender', trim((string) $filters['gender'])))
            ->when(trim((string) ($filters['major'] ?? '')) !== '', fn ($q) => $q->where('major', trim((string) $filters['major'])));

        $sort = $filters['sort'] ?? '';

        return match ($sort) {
            'name_desc' => $query->orderByDesc('name'),
            'newest' => $query->orderByDesc('created_at'),
            'oldest' => $query->orderBy('created_at'),
            default => $query->orderBy('name'),
        };
    }

    /**
     * The student ids a filter set selects, resolved server-side.
     *
     * @param  array<string, string>  $filters
     * @return list<int>
     */
    public static function ids(array $filters): array
    {
        $query = Student::query();

        self::apply($query, $filters);

        return $query->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Count of active (non-empty) filters, for the dashboard badge. */
    public static function activeCount(array $filters): int
    {
        return count(array_filter([
            $filters['grade'] ?? '',
            $filters['gender'] ?? '',
            $filters['major'] ?? '',
            $filters['sort'] ?? '',
        ], static fn ($v) => $v !== '' && $v !== null));
    }

    /**
     * Distinct values that exist among the current tenant's students, for
     * filter chips (shared by the dashboard and the bulk picker).
     */
    public static function distinctOptions(string $column): \Illuminate\Support\Collection
    {
        return Student::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }
}
