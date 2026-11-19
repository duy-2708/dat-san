<?php

namespace App\Notifications;

use App\Models\GiaoDich;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KhachBaoDaChuyenCoc extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public GiaoDich $gd)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $g = $this->gd;
        $tien = number_format((int) $g->so_tien, 0, ',', '.').'đ';

        return (new MailMessage)
            ->subject('Báo đã chuyển cọc kèo '.$tien.' — '.$g->noi_dung_ck)
            ->greeting('Chào '.$notifiable->name.',')
            ->line('**'.$g->nguoiTra?->name.'** báo đã chuyển **'.$tien.'** cọc kèo, nội dung **'.$g->noi_dung_ck.'**.')
            ->line('Kiểm tra app ngân hàng rồi bấm **Đã nhận cọc** hoặc **Không thấy tiền** ở mục "Cọc kèo chờ xác nhận". Chỗ được giữ thêm 2 tiếng.')
            ->action('Xem cọc kèo', route('quan-ly.dat-san.index'));
    }
}
