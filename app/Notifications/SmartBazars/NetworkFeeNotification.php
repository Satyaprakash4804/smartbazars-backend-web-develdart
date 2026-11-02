<?php

namespace App\Notifications\SmartBazars;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Mail with a joining-fee payment link, or an application-rejected notice. Sent on-demand (no User yet). */
class NetworkFeeNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string  $kind,        // pay_link | rejected
        protected string  $name,
        protected string  $role,
        protected ?string $url = null,
        protected ?float  $amount = null,
        protected ?string $reason = null,
    ) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting('Namaste ' . $this->name . ',');
        if ($this->kind === 'rejected') {
            $mail->subject("Your SmartBazars {$this->role} application was not approved")
                ->line('Unfortunately your application could not be approved.')
                ->line('Reason: ' . ($this->reason ?: 'Not specified'))
                ->line('You can apply again with corrected details.')
                ->action('Apply again', $this->url);
        } else {
            $mail->subject("Complete your SmartBazars {$this->role} joining payment")
                ->line("Your SmartBazars {$this->role} ID is ready to be created. Please pay the joining fee of ₹" . number_format((float)$this->amount, 2) . ' to continue.')
                ->action('Pay now', $this->url);
        }
        return $mail->salutation('Team SmartBazars');
    }
}
