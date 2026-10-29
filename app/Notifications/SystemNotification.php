<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Notifikasi internal (kanal database saja; tidak ada email/eksternal tanpa konfigurasi). */
class SystemNotification extends Notification
{
    public function __construct(
        public string $kind,
        public string $title,
        public string $message,
        public ?string $url = null,
        public ?string $key = null,
        public string $level = 'info',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'key' => $this->key,
            'level' => $this->level,
        ];
    }
}
