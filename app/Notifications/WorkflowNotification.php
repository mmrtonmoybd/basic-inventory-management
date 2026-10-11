<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Generic in-app notification for approval workflows (claims, adjustments, returns awaiting inspection).
 */
class WorkflowNotification extends Notification
{
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
