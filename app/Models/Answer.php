<?php

namespace App\Models;

use App\Models\Concerns\SharedAcrossTenants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Answers follow their question into the shared pool (see Question). */
class Answer extends Model
{
    use HasFactory, SharedAcrossTenants;

    protected $fillable = [
        'tenant_id', 'question_id', 'answer_text', 'answer_image_path', 'is_correct',
    ];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
