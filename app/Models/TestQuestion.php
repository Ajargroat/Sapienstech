<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestQuestion extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'test_questions';

    protected $fillable = [
        'tenant_id', 'test_id', 'question_id', 'position', 'points',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer', 'points' => 'decimal:2'];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
