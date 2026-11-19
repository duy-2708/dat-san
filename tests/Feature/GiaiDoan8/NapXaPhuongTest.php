<?php

namespace Tests\Feature\GiaiDoan8;

use App\Models\TinhThanh;
use App\Models\XaPhuong;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NapXaPhuongTest extends TestCase
{
    use RefreshDatabase;

    public function test_nap_du_lieu_hai_cap_khop_ten_tinh(): void
    {
        $this->seed(\Database\Seeders\TinhThanhSeeder::class);
        $wards = [];
        for ($i = 1; $i <= 3000; $i++) {
            $wards[] = ['name' => "Xã Thử $i", 'code' => 90000 + $i, 'division_type' => 'xã'];
        }
        $wards[] = ['name' => 'Phường Nha Trang', 'code' => 22333, 'division_type' => 'phường'];
        $wards[] = ['name' => 'Đặc khu Trường Sa', 'code' => 22999, 'division_type' => 'đặc khu'];
        $file = tempnam(sys_get_temp_dir(), 'dvhc');
        file_put_contents($file, json_encode([['name' => 'Tỉnh Khánh Hòa', 'code' => 56, 'wards' => $wards]], JSON_UNESCAPED_UNICODE));
        $duPhong = database_path('data/xa_phuong.json');
        $cu = is_file($duPhong) ? file_get_contents($duPhong) : null;

        try {
            $this->artisan('dia-chi:nap', ['file' => $file])->assertSuccessful();
        } finally {
            $cu === null ? @unlink($duPhong) : file_put_contents($duPhong, $cu);
        }

        $kh = TinhThanh::where('ten', 'like', 'Khánh H%')->firstOrFail();
        $this->assertSame(3002, XaPhuong::where('tinh_thanh_id', $kh->id)->count());
        $this->assertSame('dac_khu', XaPhuong::where('ten', 'Đặc khu Trường Sa')->value('loai'));
        $this->assertSame('phuong', XaPhuong::where('ten', 'Phường Nha Trang')->value('loai'));
        $this->getJson('/dia-chi/xa-phuong?tinh_thanh_id='.$kh->id)->assertOk()->assertJsonFragment(['ten' => 'Phường Nha Trang']);
    }
}
