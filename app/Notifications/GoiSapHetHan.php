<?php

namespace App\Notifications;

use App\Models\HoSoChuSan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoiSapHetHan extends Notification
{
    use Queueable;

    public function __construct(public HoSoChuSan $hoSo)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Gói dịch vụ sắp hết hạn ngày '.$this->hoSo->het_han_luc->format('d/m/Y'))
            ->greeting('Chào '.$notifiable->name.',')
            ->line("Gói dịch vụ của **{$this->hoSo->ten_co_so}** hết hạn lúc ".$this->hoSo->het_han_luc->format('H:i d/m/Y').'.')
            ->line('Sau thời điểm này sân sẽ bị ẩn khỏi trang chủ và không nhận đặt mới cho tới khi gia hạn.')
            ->action('Gia hạn ngay', route('quan-ly.goi.index'))
            ->salutation('Trân trọng, '.config('app.name'));
    }
}
