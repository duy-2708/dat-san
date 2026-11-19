<?php

namespace App\Notifications;

use App\Models\DatSan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KhachBaoDaChuyen extends Notification
{
    use Queueable;

    public function __construct(public DatSan $don, public bool $nhacLai = false)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->don;
        $tien = number_format((int) $d->tien_coc, 0, ',', '.').'đ';

        return (new MailMessage)
            ->subject(($this->nhacLai ? '[NHẮC] ' : '').'Khách báo đã chuyển cọc '.$tien.' — '.$d->ma_don)
            ->greeting('Chào '.$notifiable->name.',')
            ->line('Khách **'.$d->tenNguoiDat().'** báo đã chuyển **'.$tien.'** với nội dung **'.$d->ma_don.'**.')
            ->line('Sân '.$d->san->ten.' · '.\Carbon\Carbon::parse($d->ngay)->format('d/m/Y').' · '.$d->khungGioNhan().'.')
            ->line('Mở app ngân hàng kiểm tra rồi bấm **Đã nhận cọc** hoặc **Không thấy tiền**. Giữ chỗ tới '.$d->giu_cho_den->format('H:i d/m/Y').', quá giờ đơn tự huỷ.')
            ->action('Xem đơn chờ duyệt', route('quan-ly.dat-san.index', ['trang_thai' => 'cho_thanh_toan']));
    }
}
