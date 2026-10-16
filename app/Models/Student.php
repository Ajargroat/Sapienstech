<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasLoginIdentifier;
use App\Models\Concerns\NormalizesIdentity;
use App\Support\StudentAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Student extends Authenticatable
{
    use BelongsToTenant, HasFactory, HasLoginIdentifier, NormalizesIdentity, Notifiable;

    protected static function booted(): void
    {
        /**
         * Removed students are invisible everywhere.
         *
         * Removal is a soft marker (`removed_at`), not a delete, so a removed
         * student's deals, attempts and chat history survive — but the person
         * is off the roster: they must not appear in the dashboard, chat
         * pickers, bulk selection, report cards or teacher lists, and must not
         * be able to log in.
         *
         * The scope is deliberately UNCONDITIONAL (unlike `staff_access`, which
         * skips student-portal paths to avoid inheriting a web actor). Because
         * the student session guard resolves its user through this same query,
         * an unconditional scope is what makes "removed" also mean "logged
         * out": the moment a student is removed, their next request can no
         * longer resolve them and the session ends.
         *
         * A named scope rather than being folded into `staff_access` keeps the
         * concerns separate — `staff_access` answers "which students may this
         * actor see", `roster` answers "is this person on the roster at all".
         * Surfaces that must still render a removed student's *history* (deal
         * rows, notifications, chat threads) opt out explicitly with
         * `withoutGlobalScope('roster')`.
         */
        static::addGlobalScope('roster', function (Builder $query) {
            $query->whereNull('students.removed_at');
        });

        static::addGlobalScope('staff_access', function (Builder $query) {
            // Student authentication must not inherit a concurrently logged-in web actor.
            if (request()->is('student', 'student/*')) {
                return;
            }
            $staff = auth('web')->user();
            if ($staff instanceof User) {
                StudentAccess::scope($query, $staff);
            }
        });
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_staff')
            ->withPivot('tenant_id')
            ->whereColumn('users.tenant_id', 'student_staff.tenant_id');
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_student')
            ->withPivot('tenant_id')
            ->whereColumn('classrooms.tenant_id', 'classroom_student.tenant_id');
    }

    protected $fillable = [
        'tenant_id',
        // NULL (for now) = not pinned. Students get domain-pinned login
        // when student authentication is built. Never set from request input.
        'domain_id',
        'name',
        'email',
        'phone',
        'national_code',
        'password',
        'grade',
        'gender',
        'major',
        'avatar',
        'bio',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * Whether this person has been taken off the roster.
     *
     * Normally unreachable through a scoped query (the `roster` global scope
     * hides these rows); it matters when a caller opts out of that scope to
     * render history for a deal, payment or chat thread that outlives the
     * removal.
     */
    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    /**
     * The tenant this student belongs to.
     * Required for Factory::for($tenant) to work in tests.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedQuizzes(): HasMany
    {
        return $this->hasMany(StudentAssignedQuiz::class, 'student_id');
    }

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'student_assigned_quizzes')
            ->withPivot(['assigned_at', 'scheduled_at', 'status']);
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(StudentTestAttempt::class, 'student_id');
    }

    public function flaggedQuestions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'student_flagged_questions')
            ->withPivot('created_at');
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(ScheduleItem::class, 'student_id');
    }

    public function companyExamResults(): HasMany
    {
        return $this->hasMany(CompanyExamResult::class, 'student_id');
    }

    /**
     * The domain this student logs in through. Mirrors User::domain();
     * enforcement kicks in once student authentication exists.
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
