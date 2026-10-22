<?php

namespace Tests\Feature;

use App\Mail\ConsultationRequestReceived;
use App\Models\ConsultationRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConsultationRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The staff notification behind a new consultation request.
 *
 * The recipient rule: every tenant User row is resolved in-request while the
 * tenant is live, and the queued mailable carries ONLY scalars — name,
 * email, phone, message, tenant PK, tenant display name. A model snapshot
 * can therefore never be serialized into the queue, and the worker never
 * needs a tenant binding. A tenant with no User rows skips silently: the
 * row is the record of truth.
 */
class ConsultationEmailTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sara Ahmadi',
            'email' => 'sara@example.test',
            'phone' => '09121112233',
            'message' => 'می‌خواهم درباره دوره‌ها مشورت کنم.',
        ], $overrides);
    }

    public function test_submit_queues_one_mailable_per_tenant_user_with_a_scalar_payload(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'آکادمی نمونه']);
        $first = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'staff-1@example.test']);
        $second = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'staff-2@example.test']);

        // A user of ANOTHER tenant must never receive this tenant's mail.
        $foreign = Tenant::factory()->create();
        User::factory()->create(['tenant_id' => $foreign->id, 'email' => 'foreign@example.test']);

        Queue::fake();
        Mail::fake();

        $row = ConsultationRequests::submit($tenant, $this->payload());

        Mail::assertQueued(ConsultationRequestReceived::class, 2);

        $covered = [];
        Mail::assertQueued(function (ConsultationRequestReceived $mailable) use (&$covered, $first, $second, $row, $tenant) {
            // Data-only payload: every property is a scalar, exactly the
            // values written to the row — no model snapshot rode along.
            $this->assertSame($row->name, $mailable->name);
            $this->assertSame($row->email, $mailable->email);
            $this->assertSame($row->phone, $mailable->phone);
            $this->assertSame($row->message, $mailable->message);
            $this->assertSame($tenant->id, $mailable->tenantId);
            $this->assertSame('آکادمی نمونه', $mailable->tenantName);
            $this->assertFalse(in_array(SerializesModels::class, class_uses_recursive($mailable), true));

            // The recipient was resolved in-request and pinned as a scalar
            // address on the mailable itself.
            if ($mailable->hasTo($first->email)) {
                $covered[] = 'first';
            }
            if ($mailable->hasTo($second->email)) {
                $covered[] = 'second';
            }

            // The blade actually compiles and renders through the framework's
            // markdown components (default mailer = log; no config/mail.php).
            $this->assertIsString($mailable->render());

            return true;
        });

        sort($covered);
        $this->assertSame(['first', 'second'], $covered);

        // Nothing leaked onto the queue bus: Mail::fake absorbed everything.
        Queue::assertNothingPushed();
    }

    public function test_a_tenant_without_users_still_records_the_row_but_queues_nothing(): void
    {
        $tenant = Tenant::factory()->create();

        Queue::fake();
        Mail::fake();

        $row = ConsultationRequests::submit($tenant, $this->payload());

        $this->assertSame(ConsultationRequest::STATUS_PENDING, $row->status);
        $this->assertNotNull($row->id);
        Mail::assertNothingQueued();
    }

    public function test_a_duplicate_submission_does_not_queue_a_second_round_of_emails(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'staff-1@example.test']);

        Mail::fake();

        ConsultationRequests::submit($tenant, $this->payload());
        ConsultationRequests::submit($tenant, $this->payload(['message' => 'corrected']));

        // The duplicate refreshed the same pending row; its email was
        // already delivered once, and re-sending would spam the staff.
        Mail::assertQueued(ConsultationRequestReceived::class, 1);
    }
}
