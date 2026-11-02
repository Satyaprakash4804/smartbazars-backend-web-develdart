<?php

namespace App\Notifications\SmartBazars;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to a vendor when SmartBazars admin verifies them: authority letter (PDF) + referral QR. */
class VendorVerifiedNotification extends Notification
{
    use Queueable;

    public function __construct(protected array $data, protected string $letterPdf, protected ?string $qrPng = null) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $d = $this->data;
        $mail = (new MailMessage)
            ->subject('SmartBazars — Your vendor account is verified (Authority Letter enclosed)')
            ->greeting('Namaste ' . ($d['owner'] ?: $notifiable->name) . ',')
            ->line('Congratulations! **' . $d['name'] . '** is now a verified SmartBazars vendor.')
            ->line('Your official **Authority Letter** (' . $d['letterNo'] . ') is attached to this email as a PDF.')
            ->line('Your vendor referral code: **' . $d['code'] . '**')
            ->line('Share your QR code (attached) with customers. Every customer who joins with your code is linked to you, and you earn commission on their Welcome Kit and repeat purchases.')
            ->action('Open Vendor Panel', route('seller.login'))
            ->line('Your QR code and letter are also available any time in the Vendor Panel → My QR & Letter.')
            ->line('Verify this vendor online: ' . $d['verifyLink'])
            ->salutation('Team SmartBazars — Your Everyday Shopping, Made Smarter.');

        $mail->attachData($this->letterPdf, 'SmartBazars-Authority-Letter-' . $d['code'] . '.pdf', ['mime' => 'application/pdf']);
        if ($this->qrPng) {
            $mail->attachData($this->qrPng, 'SmartBazars-QR-' . $d['code'] . '.png', ['mime' => 'image/png']);
        }
        return $mail;
    }
}
