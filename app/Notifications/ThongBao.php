<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ThongBao extends Notification
{
    public function __construct(public string $tieuDe, public string $noiDung, public ?string $url = null, public string $icon = 'bell')
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['tieu_de' => $this->tieuDe, 'noi_dung' => $this->noiDung, 'url' => $this->url, 'icon' => $this->icon];
    }
}
