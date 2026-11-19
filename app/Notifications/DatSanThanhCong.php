<?php

namespace App\Notifications;

use App\Models\DatSan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Gửi khi chủ sân xác nhận đã nhận cọc. (Khi lên server sẽ thêm "implements ShouldQueue" để gửi nền.) */
class DatSanThanhCong extends Notification
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
        $d = $this->don->loadMissing('san.xaPhuong.tinhThanh', 'san.chuSan.hoSoChuSan');
        $tien = fn ($s) => number_format($s, 0, ',', '.').'đ';

        return (new MailMessage)
            ->subject("Đặt sân thành công {$d->ma_don} — {$d->san->ten}")
            ->greeting('Chào '.$notifiable->name.',')
            ->line("Chủ sân đã xác nhận cọc. Lịch của bạn:")
            ->line("**{$d->san->tenHienThi()}** — ".\Carbon\Carbon::parse($d->ngay)->format('d/m/Y').", {$d->khungGioNhan()}")
            ->line('Địa chỉ: '.$d->san->diaChiDayDu())
            ->line('Đã cọc '.$tien($d->tien_coc).', còn lại '.$tien($d->conLaiPhai()).' trả tại sân.')
            ->line('Liên hệ sân: '.($d->san->chuSan->hoSoChuSan?->sdt_lien_he ?? $d->san->chuSan->so_dien_thoai))
            ->action('Xem lịch đặt của tôi', route('khach.tai-khoan'))
            ->salutation('Chúc bạn đá vui, '.config('app.name'));
    }
}
