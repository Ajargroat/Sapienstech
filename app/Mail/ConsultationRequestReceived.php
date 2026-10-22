<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "A visitor asked for a consultation" notification, to a tenant's staff.
 *
 * The payload is DATA-ONLY scalars — name, email, phone, message, tenant
 * PK, tenant display name — and deliberately WITHOUT SerializesModels:
 * nothing model-shaped can ever reach the queue, so the worker needs no
 * tenant binding and cannot leak another tenant's rows. Recipients are
 * attached in-request by the caller (App\Support\ConsultationRequests),
 * while the tenant is still live.
 */
class ConsultationRequestReceived extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public string $message,
        public int $tenantId,
        public string $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'درخواست مشاوره جدید: '.$this->tenantName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.consultation-request-received',
        );
    }
}
