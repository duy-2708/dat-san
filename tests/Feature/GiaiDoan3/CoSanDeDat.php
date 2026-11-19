<?php

namespace Tests\Feature\GiaiDoan3;

use App\Models\KhungGio;
use App\Models\San;
use App\Models\User;
use App\Services\BangGia;
use Carbon\Carbon;

/** Dựng sẵn: chủ sân có tài khoản nhận cọc + sân 5A, 5B, sân 7 ghép (5A+5B), khung 60' từ 06:00, giá 300k. */
trait CoSanDeDat
{
    protected User $chu;
    protected San $a;
    protected San $b;
    protected San $bay;

    protected function dungSan(): void
    {
        // Thứ Tư 18/11/2026 08:00 → mọi khung từ 09:00 trở đi còn đặt được
        $this->travelTo(Carbon::parse('2026-11-18 08:00:00'));

        $this->chu = User::factory()->chuSan()->create();
        $hoSo = $this->chu->hoSoChuSan;
        $hoSo->fill(['ngan_hang_bin' => '970436', 'so_tai_khoan' => '0123456789', 'ten_chu_tai_khoan' => 'NGUYEN VAN CHU', 'sdt_lien_he' => '0909000111'])->save();

        $this->a = San::factory()->cua($this->chu)->create(['ten' => 'Sân 5A']);
        $this->b = San::factory()->cua($this->chu)->create(['ten' => 'Sân 5B']);
        $this->bay = San::factory()->cua($this->chu)->loai(7)->create(['ten' => 'Sân 7 Lớn']);
        $this->bay->sanCons()->sync([$this->a->id, $this->b->id]);

        $bg = app(BangGia::class);
        foreach ([$this->a, $this->b, $this->bay] as $s) {
            $bg->taoNhanh($s, ['ngay_thuong', 'cuoi_tuan'], 60, 300000);
        }
    }

    protected function khung(San $san, string $gio = '17:00'): KhungGio
    {
        return $san->khungGios()->where('loai_ngay', 'ngay_thuong')->where('gio_bat_dau', $gio.':00')->firstOrFail();
    }

    protected function datOnline(User $khach, San $san, string $gio = '17:00', string $ngay = '2026-11-18')
    {
        return $this->actingAs($khach)->post('/dat-san', [
            'san_id' => $san->id, 'ngay' => $ngay, 'khung_gio_id' => $this->khung($san, $gio)->id,
        ]);
    }
}
