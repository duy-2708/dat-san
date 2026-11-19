<?php

namespace App\Notifications;

use App\Models\DatSan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NhacLichDa extends Notification
{
    use Queueable;

    public function __construct(public DatSan $don)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->don->loadMissing('san.xaPhuong.tinhThanh');

        return (new MailMessage)
            ->subject("Nhắc lịch: {$d->khungGioNhan()} hôm nay tại {$d->san->ten}")
            ->greeting('Chào '.$notifiable->name.',')
            ->line("Bạn có lịch đá lúc **{$d->khungGioNhan()}** ngày ".\Carbon\Carbon::parse($d->ngay)->format('d/m/Y')." tại **{$d->san->tenHienThi()}**.")
            ->line('Địa chỉ: '.$d->san->diaChiDayDu())
            ->line('Còn lại '.number_format($d->conLaiPhai(), 0, ',', '.').'đ trả tại sân.')
            ->salutation('Hẹn gặp bạn trên sân, '.config('app.name'));
    }
}
