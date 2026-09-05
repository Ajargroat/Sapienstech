<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A mock-exam corporation (قلم‌چی، ماز، …). Tenant-owned; `slug` is the stable
 * key the report-card filter uses and `color` tints the card badge.
 */
class ExamCompany extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'slug', 'color'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(CompanyExamResult::class, 'company_id');
    }
}
