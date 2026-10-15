<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One consultant's conversation with the planner assistant about one student.
 *
 * The shape is the tenancy guarantee: a thread is keyed
 * (tenant_id, user_id, student_id), so "read my thread about this student" can
 * only ever resolve to a row the current consultant owns, inside the tenant the
 * host resolved. BelongsToTenant adds the tenant scope on top, and the
 * controller re-asserts both — the same fail-closed pattern the schedule
 * controllers use, not a weaker one.
 *
 * `week_start_date` records the week under discussion. It is nullable and
 * informational: reopening the panel mid-conversation does not silently move
 * the thread to a new week, and the assistant is told which week it is
 * discussing from the request, not from this column.
 */
class ScheduleAssistantThread extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'student_id',
        'week_start_date',
        'title',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'last_activity_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ScheduleAssistantMessage::class, 'thread_id')->orderBy('id');
    }

    /**
     * The consultant's single thread about this student, created on demand.
     *
     * `withoutGlobalScopes()` is deliberately NOT used here: the tenant scope is
     * exactly what should apply. The caller has already been checked against
     * tenant(), and the user_id condition is what stops one consultant reading
     * another's thread.
     */
    public static function for(User $user, Student $student): self
    {
        return self::firstOrCreate(
            [
                'user_id' => $user->id,
                'student_id' => $student->id,
            ],
            [
                'tenant_id' => tenant()?->id,
                'week_start_date' => null,
                'last_activity_at' => now(),
            ]
        );
    }
}
