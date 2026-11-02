<?php

namespace App\Notifications\SmartBazars;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Emails the offline customer their SmartBazars User Card (PDF attached) + app login details. */
class OfflineUserCardNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected array $data,
        protected string $pdf,
        protected ?string $password,
        protected string $shareUrl,
    ) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your SmartBazars User Card — ' . $this->data['cardNo'])
            ->greeting('Namaste ' . $notifiable->name . ',')
            ->line('Welcome to SmartBazars — Smart Shopping, Better Life. Your User Card is attached to this email.')
            ->line('User Card No.: ' . $this->data['cardNo'])
            ->line('Valid upto: ' . $this->data['validUpto'])
            ->line('Show the card (or its QR) at any SmartBazars vendor store. You earn cashback coins on every purchase, and no delivery charge applies on store purchases.');

        if ($this->password) {
            $mail->line('App / website login — mobile: ' . $notifiable->mobile . ' · password: ' . $this->password)
                ->line('You can reset the password any time with a phone OTP from the "Forgot password" screen.');
        }

        $mail->attachData($this->pdf, 'SmartBazars-User-Card-' . $this->data['rawCardNo'] . '.pdf', ['mime' => 'application/pdf']);
        return $mail->action('View your card online', $this->shareUrl)
            ->salutation('Team SmartBazars');
    }
}
