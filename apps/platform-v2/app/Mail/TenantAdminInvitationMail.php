<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantAdminInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Organization $organization, public readonly string $invitationUrl, public readonly \DateTimeInterface $expiresAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Undangan admin organisasi — Pesantren.online');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.tenant-admin-invitation');
    }
}
