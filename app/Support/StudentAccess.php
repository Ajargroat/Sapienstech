<?php

namespace App\Support;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** The same roster boundary for HTTP queries, jobs and explicitly supplied actors. */
class StudentAccess
{
    public static function scope(Builder $query, User $staff): Builder
    {
        $query->where('students.tenant_id', $staff->tenant_id);
        if (! in_array($staff->role, [User::ROLE_TEACHER, User::ROLE_CONSULTANT_STAFF, User::ROLE_TENANT_ADMIN], true)) {
            return $query->whereRaw('1 = 0');
        }

        $tenant = Tenant::find($staff->tenant_id);
        if (! $tenant || $tenant->isSuspended()) {
            return $query->whereRaw('1 = 0');
        }
        if (! $tenant->hierarchy_type || $staff->isTenantOwner()) {
            return $query;
        }

        return $query->where(function (Builder $roster) use ($staff, $tenant) {
            $roster->whereExists(function ($links) use ($staff) {
                $links->selectRaw('1')->from('student_staff')
                    ->whereColumn('student_staff.student_id', 'students.id')
                    ->whereColumn('student_staff.tenant_id', 'students.tenant_id')
                    ->where('student_staff.user_id', $staff->id);
            });
            if ($tenant->hierarchy_type === 'school') {
                $roster->orWhereExists(function ($links) use ($staff) {
                    $links->selectRaw('1')->from('classroom_student')
                        ->join('classroom_teacher', function ($join) {
                            $join->on('classroom_teacher.classroom_id', '=', 'classroom_student.classroom_id')
                                ->on('classroom_teacher.tenant_id', '=', 'classroom_student.tenant_id');
                        })
                        ->whereColumn('classroom_student.student_id', 'students.id')
                        ->whereColumn('classroom_student.tenant_id', 'students.tenant_id')
                        ->where('classroom_teacher.user_id', $staff->id);
                });
            }
        });
    }

    public static function allows(User $staff, Student $student): bool
    {
        if ((int) $staff->tenant_id !== (int) $student->tenant_id) {
            return false;
        }

        // Never inherit another logged-in actor's roster when checking an explicit actor.
        return self::scope(Student::withoutGlobalScope('staff_access'), $staff)
            ->whereKey($student->id)->exists();
    }
}
