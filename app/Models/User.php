<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, BelongsToTenant;

    public const ROLE_PLATFORM_ADMIN = 'platform_admin';
    public const ROLE_TENANT_ADMIN = 'tenant_admin';
    public const ROLE_CONSULTANT_STAFF = 'consultant_staff';
    public const ROLE_TEACHER = 'teacher';

    protected $fillable = [
        'tenant_id',
        'domain_id',
        'name',
        'email',
        'password',
        'role',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === self::ROLE_PLATFORM_ADMIN;
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === self::ROLE_TENANT_ADMIN;
    }

    public function isTenantOwner(): bool
    {
        $tenant = Tenant::find($this->tenant_id);

        return $this->isTenantAdmin() && $tenant
            && ((int) $tenant->owner_user_id === (int) $this->id
                || (! $tenant->hierarchy_type && $tenant->owner_user_id === null));
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_staff')
            ->withPivot('tenant_id')
            ->whereColumn('students.tenant_id', 'student_staff.tenant_id');
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_teacher')
            ->withPivot(['tenant_id', 'subject'])
            ->whereColumn('classrooms.tenant_id', 'classroom_teacher.tenant_id');
    }

    public function isConsultantStaff(): bool
    {
        return $this->role === self::ROLE_CONSULTANT_STAFF;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function lessonMaterials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class, 'teacher_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'teacher_id');
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'teacher_id');
    }

    /**
     * The domain entry rule:
     * - platform admins never authenticate on tenant domains;
     * - the domain must always belong to the user's tenant;
     * - tenant admins may enter through ANY of the tenant's domains;
     * - everyone else (consultants, later students) only through their
     *   own domain. domain_id NULL = not pinned yet (legacy accounts,
     *   tenant admins) -> any domain of the tenant.
     */
    public function canLoginThrough(?Domain $domain): bool
    {
        if (! $domain || $this->isPlatformAdmin()) {
            return false;
        }

        if ((int) $domain->tenant_id !== (int) $this->tenant_id) {
            return false;
        }

        if ($this->isTenantAdmin()) {
            return true;
        }

        return $this->domain_id === null
            || (int) $this->domain_id === (int) $domain->id;
    }
}
