<?php

namespace App\Notifications\SmartBazars;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent when an BSO / BJO / Vendor ID is approved — contains the login password. */
class PartnerCredentialsNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string  $password,
        protected string  $role,
        protected ?string $referralCode,
        protected string  $loginUrl,
        protected ?string $idCardPdf = null, // SmartBazars v1.4: ID card attached for agents/workers
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your SmartBazars {$this->role} account is approved")
            ->greeting('Namaste ' . $notifiable->name . ',')
            ->line("Your SmartBazars {$this->role} ID has been approved. You can log in with these details:")
            ->line('Email: ' . $notifiable->email)
            ->line('Password: ' . $this->password);

        if ($this->referralCode) {
            $mail->line('Your referral code: ' . $this->referralCode);
        }

        if ($this->idCardPdf) {
            $mail->line('Your official SmartBazars ID card is attached (PDF). You can also download it any time from your dashboard.');
            $mail->attachData($this->idCardPdf, 'SmartBazars-ID-Card-' . ($this->referralCode ?? 'card') . '.pdf', ['mime' => 'application/pdf']);
        }

        return $mail->action('Login to SmartBazars', $this->loginUrl)
            ->line('Please change your password after the first login.')
            ->salutation('Team SmartBazars — Your Everyday Shopping, Made Smarter.');
    }
}
