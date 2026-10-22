<?php

namespace App\Models;

use App\Models\Concerns\SharedAcrossTenants;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The question bank is a SHARED pool: pooled rows carry no tenant_id and every
 * tenant reads them (see SharedAcrossTenants), while the exam feature keeps
 * writing tenant-stamped test_questions/attempt_answers rows around them.
 */
class Question extends Model
{
    use HasFactory, SharedAcrossTenants;

    protected $fillable = [
        'tenant_id', 'lesson_id', 'chapter_id', 'topic_id', 'question_text', 'question_image_path',
        'solution_text', 'solution_image_path', 'question_number_in_book',
        'difficulty', 'question_type', 'supabase_id', 'question_image_bbox',
        'subject', 'corp', 'chapter_label',
    ];

    /**
     * The lesson this question was written for, when it came out of one.
     *
     * Pooled questions carry no tenant, so this is the ONLY exact link between
     * a lesson and the questions a student answers at the end of it. Null for
     * everything that came from the old bank import.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(BookLesson::class, 'lesson_id');
    }

    protected function casts(): array
    {
        return ['question_number_in_book' => 'integer', 'question_image_bbox' => 'array'];

    }

    /**
     * May a surface draw a picture for this question? Needs the source page
     * AND a usable crop box — a path on its own is provenance, not a picture,
     * so the whole source page is never rendered as if it were the diagram.
     */
    protected function hasCroppableImage(): Attribute
    {
        return Attribute::get(fn (): bool => (bool) ($this->question_image_path
            && is_array($this->question_image_bbox)
            && ($this->question_image_bbox['w'] ?? 0) > 0
            && ($this->question_image_bbox['h'] ?? 0) > 0
            && ($this->question_image_bbox['pw'] ?? 0) > 0));
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
