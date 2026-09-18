<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Student extends Authenticatable
{
    use HasFactory, Notifiable, BelongsToTenant;

    protected static function booted(): void
    {
        static::addGlobalScope('staff_access', function (\Illuminate\Database\Eloquent\Builder $query) {
            // Student authentication must not inherit a concurrently logged-in web actor.
            if (request()->is('student', 'student/*')) {
                return;
            }
            $staff = auth('web')->user();
            if ($staff instanceof User) {
                \App\Support\StudentAccess::scope($query, $staff);
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
        ];
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
