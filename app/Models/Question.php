<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'chapter_id', 'topic_id', 'question_text', 'question_image_path',
        'solution_text', 'solution_image_path', 'question_number_in_book',
        'difficulty', 'question_type', 'supabase_id', 'question_image_bbox'
    ];

    protected function casts(): array
    {
        return ['question_number_in_book' => 'integer', 'question_image_bbox' => 'array'];

    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function correctAnswers(): HasMany
    {
        return $this->answers()->where('is_correct', true);
    }

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'test_questions')->withPivot(['position', 'points']);
    }
}
