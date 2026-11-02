<?php

namespace App\Notifications\SmartBazars;

use App\Broadcasting\FirebaseChannel;
use App\Enums\NotificationTypeEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * One notification class for the SmartBazars program events (offline orders, pickup orders, wallet recharges, earnings).
 * Delivered to the in-app notification list (database) and as a push to the user's apps (Firebase).
 */
class ProgramActivityNotification extends Notification
{
    use Queueable;

    /**
     * @param string $type    a NotificationTypeEnum value (order, new_order, order_update, wallet_transaction, payment ...)
     * @param string $sentTo  seller | customer | admin | partner
     * @param array  $meta    extra ids (order_id, offline_order_id, order_slug, ...). Values are stringified for push.
     */
    public function __construct(
        protected string $title,
        protected string $message,
        protected string $type = 'order_update',
        protected string $sentTo = 'customer',
        protected array $meta = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FirebaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'sent_to' => $this->sentTo,
            'user_id' => $notifiable->id ?? null,
            'store_id' => null,
            'order_id' => $this->meta['order_id'] ?? null,
            'metadata' => $this->meta,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toFirebase($notifiable): array
    {
        $data = ['type' => $this->type];
        foreach ($this->meta as $k => $v) {
            if (is_scalar($v)) {
                $data[$k] = (string)$v;       // FCM data payload must be strings
            }
        }
        return ['title' => $this->title, 'body' => $this->message, 'image' => null, 'data' => $data];
    }
}
