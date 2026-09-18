<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Classroom extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'grade',
        'name',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'classroom_student')
            ->withPivot('tenant_id')
            ->whereColumn('students.tenant_id', 'classroom_student.tenant_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_teacher')
            ->withPivot(['tenant_id', 'subject'])
            ->whereColumn('users.tenant_id', 'classroom_teacher.tenant_id');
    }
}
