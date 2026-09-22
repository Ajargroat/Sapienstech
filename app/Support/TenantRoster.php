<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Live, tenant-scoped facts about a tenant's institution — the numbers and
 * people a landing page wants to show.
 *
 * Nothing here is written into config. Every value is read from the tenant's
 * own rows through the models' tenant scope, so one section template renders
 * Moein's nine classrooms and nine teachers, a consultancy's consultants, or
 * nothing at all, without a branch per tenant. The config only decides *which*
 * facts to show and what to call them.
 *
 * The hierarchy_type is the only branch, and it mirrors the platform's own
 * vocabulary (see StudentAccess): a `school` has classrooms and grades, a
 * consultancy has individually-assigned students and consultants.
 */
final class TenantRoster
{
    /**
     * Metric key => the label a section falls back to when config omits one.
     *
     * These are deliberately generic ("کادر آموزشی", not "دبیر"); a school
     * tenant names its own staff vocabulary through the section's config.
     */
    public const METRIC_LABELS = [
        'students'   => 'دانش‌آموز',
        'staff'      => 'کادر آموزشی',
        'classrooms' => 'کلاس',
        'grades'     => 'پایه تحصیلی',
        'subjects'   => 'درس',
    ];

    /** Metric key => its default icon, so config only overrides when it cares. */
    private const METRIC_ICONS = [
        'students'   => 'fa-solid fa-user-graduate',
        'staff'      => 'fa-solid fa-user-tie',
        'classrooms' => 'fa-solid fa-building',
        'grades'     => 'fa-solid fa-layer-group',
        'subjects'   => 'fa-solid fa-book',
    ];

    /** Accent rotation for the metric cards, so a caller need not name colours. */
    private const METRIC_ACCENTS = ['primary', 'secondary', 'accent_teal', 'accent_amber', 'accent_blue', 'accent_rose'];

    /** A school groups its students into classrooms; a consultancy does not. */
    public static function isSchool(?Tenant $tenant): bool
    {
        return $tenant?->hierarchy_type === 'school';
    }

    /**
     * Raw counts behind the metric keys, resolved against one tenant.
     *
     * Unknown keys are simply absent, so a section can name a metric this
     * class does not know and it drops out rather than rendering empty.
     *
     * @return array<string, int>
     */
    public static function metrics(?Tenant $tenant): array
    {
        if (! $tenant) {
            return [];
        }

        $out = [
            'students' => (int) Student::query()->where('students.tenant_id', $tenant->id)->count(),
            'staff'    => self::staffQuery($tenant)->count(),
        ];

        if (self::isSchool($tenant)) {
            $out['classrooms'] = (int) Classroom::query()->where('classrooms.tenant_id', $tenant->id)->count();
            $out['grades']     = count(self::gradeNames($tenant));
            $out['subjects']   = self::subjectCount($tenant);
        }

        return array_filter($out, static fn (int $n): bool => $n > 0);
    }

    /**
     * Turn a section's configured metric list into renderable rows.
     *
     * A metric that resolves to zero (or is unknown) is dropped, so a tenant
     * whose data does not support a metric never shows an empty "۰".
     *
     * @param  list<array<string, mixed>>  $configured  rows from the section config
     * @return list<array{key:string,value:int,label:string,icon:?string,accent:string,path:int}>
     */
    public static function resolveMetrics(array $configured, ?Tenant $tenant): array
    {
        $values = self::metrics($tenant);
        $rows   = [];

        foreach (array_values($configured) as $index => $row) {
            $key = is_array($row) ? (string) ($row['key'] ?? '') : (string) $row;

            if (! isset($values[$key])) {
                continue;
            }

            $rows[] = [
                'key'    => $key,
                'value'  => $values[$key],
                'label'  => (string) ($row['label'] ?? self::METRIC_LABELS[$key] ?? $key),
                'icon'   => $row['icon'] ?? self::METRIC_ICONS[$key] ?? null,
                'accent' => $row['accent'] ?? self::METRIC_ACCENTS[count($rows) % count(self::METRIC_ACCENTS)],
                // The row's index in the *stored* list, so the studio can
                // address it even when earlier rows were skipped.
                'path'   => $index,
            ];
        }

        return $rows;
    }

    /**
     * The school's classrooms, grouped by grade and ordered by the academic
     * vocabulary (هفتم/هشتم/نهم), not alphabetically.
     *
     * @return list<array{grade:string, total:int, rooms:list<array{name:string,students:int}>}>
     */
    public static function classrooms(?Tenant $tenant): array
    {
        if (! $tenant || ! self::isSchool($tenant)) {
            return [];
        }

        $rooms = Classroom::query()
            ->where('classrooms.tenant_id', $tenant->id)
            ->withCount('students')
            ->get();

        if ($rooms->isEmpty()) {
            return [];
        }

        $order = array_flip(self::gradeNames($tenant));

        return $rooms
            ->groupBy('grade')
            ->sortBy(static fn ($group, string $grade): int => $order[$grade] ?? PHP_INT_MAX)
            ->map(static fn ($group, string $grade): array => [
                'grade' => $grade,
                'total' => (int) $group->sum('students_count'),
                'rooms' => $group->sortBy('name')->map(static fn (Classroom $room): array => [
                    'name'     => $room->name,
                    'students' => (int) $room->students_count,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The tenant's teaching/advising staff: teachers for a school, consultants
     * for a consultancy. Each carries the subjects they actually teach, taken
     * from the classroom links rather than restated anywhere.
     *
     * @return list<array{name:string,initials:string,subject:?string,bio:?string,avatar:?string}>
     */
    public static function staff(?Tenant $tenant, int $limit = 12): array
    {
        if (! $tenant) {
            return [];
        }

        $members = self::staffQuery($tenant)
            ->with(self::isSchool($tenant) ? 'classrooms:id,tenant_id,name' : [])
            ->orderBy('name')
            ->limit(max(1, $limit))
            ->get(['id', 'tenant_id', 'name', 'bio', 'avatar']);

        return $members->map(static function (User $member): array {
            $subjects = $member->relationLoaded('classrooms')
                ? $member->classrooms->pluck('pivot.subject')->filter()->unique()->values()->all()
                : [];

            return [
                'name'     => (string) $member->name,
                'initials' => mb_substr((string) $member->name, 0, 1),
                'subject'  => $subjects === [] ? null : implode('، ', $subjects),
                'bio'      => $member->bio ?: null,
                'avatar'   => $member->avatar ?: null,
            ];
        })->all();
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * The staff half of the roster. `User` already carries the tenant scope;
     * the explicit tenant_id keeps this correct when read for an explicit
     * tenant (e.g. `tenant:validate`) that is not the bound one.
     */
    private static function staffQuery(Tenant $tenant)
    {
        $role = self::isSchool($tenant) ? User::ROLE_TEACHER : User::ROLE_CONSULTANT_STAFF;

        return User::query()->where('users.tenant_id', $tenant->id)->where('role', $role);
    }

    /**
     * Grade names in curriculum order. Classrooms are the source of truth for a
     * school; the platform's academic vocabulary only decides the *order*, so a
     * school that adds a grade still shows it (at the end).
     *
     * @return list<string>
     */
    private static function gradeNames(Tenant $tenant): array
    {
        $present = Classroom::query()
            ->where('classrooms.tenant_id', $tenant->id)
            ->distinct()
            ->pluck('grade')
            ->all();

        if ($present === []) {
            return [];
        }

        $ordered = array_values(array_intersect(array_keys(Academics::grades()), $present));

        return array_merge($ordered, array_values(array_diff($present, $ordered)));
    }

    /** How many distinct subjects the tenant's classrooms are actually taught. */
    private static function subjectCount(Tenant $tenant): int
    {
        return (int) DB::table('classroom_teacher')
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('subject')
            ->distinct()
            ->count('subject');
    }
}
