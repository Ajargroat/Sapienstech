<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'owner_user_id',
                'hierarchy_type',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /** The tenant's main admin (see the owner_user_id migration). */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** Teachers are sub-levels of the tenant: users with the teacher role. */
    public function teachers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_TEACHER);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function consultants(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_CONSULTANT_STAFF);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
