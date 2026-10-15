<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visitor's request to join a tenant's roster, carrying the data they
 * imported plus the payment they declared.
 *
 * This is the staging area between "someone filled in the website's join form"
 * and "a student exists in the roster". Nothing here reaches `students` until a
 * tenant admin/owner approves the request — an unapproved visitor must never
 * appear in a list, a count, or a chat picker.
 *
 * Status is the same three-step vocabulary the deal flow uses for its receipts:
 * pending → approved | rejected, with `reviewed_by`/`reviewed_at` stamping who
 * decided and when.
 */
class StudentJoinRequest extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'grade',
        'gender',
        'major',
        'bio',
        'plan_slug',
        'plan_name',
        'amount',
        'currency',
        'reference',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'student_id',
        'open_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /** The reviewer (tenant admin/owner) who decided this request. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The student row created when this request was approved. */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * The duplicate guard's value for an open request.
     *
     * Scoped by tenant so the same person may hold an open request on two
     * different tenants' sites, and cleared on review so a rejected applicant
     * can apply again later.
     */
    public static function openKey(int $tenantId, string $email): string
    {
        return $tenantId.'|'.mb_strtolower(trim($email));
    }

    /**
     * Requests still awaiting a decision, newest first.
     *
     * Named scope so a caller reads `StudentJoinRequest::pending()` rather than
     * restating the status string and drifting from it.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
