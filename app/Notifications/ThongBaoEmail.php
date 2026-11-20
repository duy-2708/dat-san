<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ThongBaoEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $tieuDe, public string $noiDung, public ?string $url = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)->subject($this->tieuDe.' — '.config('app.name'))->greeting('Chào '.$notifiable->name.',')->line($this->noiDung);

        return $this->url ? $m->action('Xem chi tiết', $this->url) : $m;
    }
}
