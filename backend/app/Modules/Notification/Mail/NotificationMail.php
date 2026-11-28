<?php

namespace App\Modules\Notification\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyBn,
        public readonly string $bodyEn,
        public readonly string $severity = 'INFO',
        public readonly string $category = 'SYSTEM_ANNOUNCEMENT'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->renderHtmlBody(),
            text: null,
        );
    }

    protected function renderHtmlBody(): string
    {
        $escapedSubject = e($this->subjectLine);
        $escapedBn = nl2br(e($this->bodyBn));
        $escapedEn = nl2br(e($this->bodyEn));
        $escapedSeverity = e($this->severity);
        $escapedCategory = e($this->category);

        return <<<HTML
<!DOCTYPE html>
<html lang="bn">
<head><meta charset="utf-8"><title>{$escapedSubject}</title></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
  <div style="max-width: 640px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 6px; padding: 20px;">
    <h2 style="margin-top: 0; color: #0f5132;">দুর্যোগ ব্যবস্থাপনা ও ত্রাণ মন্ত্রণালয় | MoDMR IDMS</h2>
    <p style="font-size: 12px; color: #6b7280;">Category: {$escapedCategory} | Severity: {$escapedSeverity}</p>
    <hr style="border: none; border-top: 1px solid #e5e7eb;" />
    <p>{$escapedBn}</p>
    <hr style="border: none; border-top: 1px dashed #e5e7eb;" />
    <p style="color: #374151;">{$escapedEn}</p>
    <p style="font-size: 11px; color: #9ca3af; margin-top: 24px;">This is an automated message from Integrated Disaster Management System (IDMS).</p>
  </div>
</body>
</html>
HTML;
    }
}
