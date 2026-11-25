<?php

namespace Tests\Feature\GiaiDoan14;

use App\Models\ChuoiCoDinh;
use App\Models\DatSan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\GiaiDoan3\CoSanDeDat;
use Tests\TestCase;

class CoDinhTest extends TestCase
{
    use CoSanDeDat, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dungSan();
        $this->travelTo(Carbon::parse('2026-11-18 10:00'));
    }

    public function test_tao_lich_co_dinh_den_het_nam_theo_thu(): void
    {
        $khung = $this->khung($this->a, '17:00');

        $this->actingAs($this->chu)->post(route('quan-ly.co-dinh.store'), [
            'khung_gio_id' => $khung->id, 'thu' => 3, 'tu_ngay' => '2026-11-18',
            'ten' => 'Nhóm Anh Ba', 'sdt' => '0911222333',
        ])->assertSessionHasNoErrors();

        // Các thứ Tư từ 18/11 đến 30/12/2026: 18/11, 25/11, 2/12, 9/12, 16/12, 23/12, 30/12
        $chuoi = ChuoiCoDinh::firstOrFail();
        $this->assertSame(7, DatSan::where('chuoi_co_dinh_id', $chuoi->id)->count());
        $this->assertSame(7, DatSan::where('chuoi_co_dinh_id', $chuoi->id)->where('nguon', 'tai_cho')->count());
    }

    public function test_buoi_trung_lich_thi_bo_qua_va_bao_lai(): void
    {
        $khung = $this->khung($this->a, '17:00');
        $this->actingAs($this->chu)->post(route('quan-ly.co-dinh.store'), [
            'khung_gio_id' => $khung->id, 'thu' => 3, 'tu_ngay' => '2026-11-25',
            'ten' => 'Anh Ba', 'sdt' => '0911222333',
        ])->assertSessionHasNoErrors();
        $this->assertSame(6, ChuoiCoDinh::firstOrFail()->dat()->count());

        $this->actingAs($this->chu)->post(route('quan-ly.co-dinh.store'), [
            'khung_gio_id' => $khung->id, 'thu' => 3, 'tu_ngay' => '2026-11-25',
            'ten' => 'Anh Tư', 'sdt' => '0911222444',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, ChuoiCoDinh::where('ten', 'Anh Tư')->firstOrFail()->dat()->count());
        $this->assertSame(6, ChuoiCoDinh::where('ten', 'Anh Ba')->firstOrFail()->dat()->count());
    }

    public function test_bo_mot_tuan_va_bo_nguyen_khoang_giu_buoi_da_thu(): void
    {
        $khung = $this->khung($this->a, '17:00');
        $this->actingAs($this->chu)->post(route('quan-ly.co-dinh.store'), [
            'khung_gio_id' => $khung->id, 'thu' => 3, 'tu_ngay' => '2026-11-25',
            'ten' => 'Anh Ba', 'sdt' => '0911222333',
        ])->assertSessionHasNoErrors();
        $chuoi = ChuoiCoDinh::firstOrFail();

        $this->actingAs($this->chu)->patch(route('quan-ly.co-dinh.huy', $chuoi), ['tu' => '2026-12-02', 'den' => '2026-12-02'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, DatSan::where('chuoi_co_dinh_id', $chuoi->id)->where('trang_thai', 'da_huy')->count());

        $buoiDaThu = DatSan::where('chuoi_co_dinh_id', $chuoi->id)->where('ngay', '2026-12-09')->firstOrFail();
        $buoiDaThu->forceFill(['da_thu_tien_luc' => now(), 'phuong_thuc_thu_tai_san' => 'tien_mat'])->save();

        $this->actingAs($this->chu)->patch(route('quan-ly.co-dinh.huy', $chuoi), ['tu' => '2026-12-01', 'den' => '2026-12-31'])
            ->assertSessionHasNoErrors();
        $this->assertSame('da_dat', $buoiDaThu->fresh()->trang_thai);
        $this->assertSame(0, DatSan::where('chuoi_co_dinh_id', $chuoi->id)->where('ngay', '2026-12-16')->where('trang_thai', 'da_dat')->count());
    }

    public function test_khach_khong_tao_va_huy_duoc_lich_co_dinh(): void
    {
        $khung = $this->khung($this->a, '17:00');
        $khach = User::factory()->create();

        $this->actingAs($khach)->post(route('quan-ly.co-dinh.store'), [
            'khung_gio_id' => $khung->id, 'thu' => 3, 'tu_ngay' => '2026-11-25',
            'ten' => 'Khach La', 'sdt' => '0911222333',
        ])->assertForbidden();

        $this->actingAs($this->chu)->post(route('quan-ly.co-dinh.store'), [
            'khung_gio_id' => $khung->id, 'thu' => 3, 'tu_ngay' => '2026-11-25',
            'ten' => 'Anh Ba', 'sdt' => '0911222333',
        ]);
        $chuoi = ChuoiCoDinh::firstOrFail();
        $this->actingAs($khach)->patch(route('quan-ly.co-dinh.huy', $chuoi), ['tu' => '2026-11-25', 'den' => '2026-12-31'])
            ->assertForbidden();
    }
}
