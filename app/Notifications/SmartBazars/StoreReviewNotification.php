<?php

namespace App\Notifications\SmartBazars;

use App\Enums\NotificationTypeEnum;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** rejected -> to the vendor (with the reason) · resubmitted -> to admins */
class StoreReviewNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $kind, protected Store $store, protected ?string $reason = null) {}

    public function via($notifiable): array { return ['mail', 'database']; }

    protected function text(): array
    {
        $name = $this->store->name ?? 'your store';
        return $this->kind === 'rejected'
            ? ['Store verification: changes needed', "Your store \"{$name}\" was not approved. Reason: " . ($this->reason ?: 'not specified') . '. Please update the store details and save — it goes back for review automatically.']
            : ['Store resubmitted for review', "Store \"{$name}\" was updated after rejection and is waiting for your review."];
    }

    public function toMail($notifiable): MailMessage
    {
        [$subject, $line] = $this->text();
        return (new MailMessage)->subject($subject)->greeting('Hello ' . ($notifiable->name ?? ''))->line($line)->salutation('Team SmartBazars');
    }

    public function toDatabase($notifiable): array
    {
        [$title, $message] = $this->text();
        return [
            'title' => $title,
            'message' => $message,
            'type' => NotificationTypeEnum::SYSTEM(),
            'sent_to' => $this->kind === 'rejected' ? 'seller' : 'admin',
            'user_id' => $notifiable->id ?? null,
            'store_id' => $this->store->id,
            'metadata' => ['store_id' => $this->store->id, 'review' => $this->kind, 'reason' => $this->reason],
        ];
    }
}
