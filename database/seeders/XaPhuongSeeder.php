<?php

namespace Database\Seeders;

use App\Models\TinhThanh;
use App\Models\XaPhuong;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Nạp xã/phường.
 *
 * - Nếu có file database/data/xa_phuong.json → nạp TOÀN BỘ (~3.321 đơn vị) từ file.
 *   Định dạng: [{"tinh": "Hà Nội", "ma": "00004", "ten": "Phường Ba Đình", "loai": "phuong"}, ...]
 *   Lấy danh sách chính thức từ Cổng dịch vụ công / Tổng cục Thống kê rồi chuyển sang định dạng trên.
 * - Nếu không có file → nạp một ít phường MẪU để chạy thử.
 */
class XaPhuongSeeder extends Seeder
{
    public function run(): void
    {
        $tinhTheoTen = TinhThanh::pluck('id', 'ten');
        $file = database_path('data/xa_phuong.json');

        $danhSach = File::exists($file)
            ? json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR)
            : $this->duLieuMau();

        foreach (array_chunk($danhSach, 500) as $lo) {
            $dong = [];
            foreach ($lo as $x) {
                $tinhId = $tinhTheoTen[$x['tinh']] ?? null;
                if (! $tinhId) {
                    $this->command?->warn("Bỏ qua {$x['ten']}: không thấy tỉnh {$x['tinh']}");
                    continue;
                }
                $dong[] = [
                    'tinh_thanh_id' => $tinhId,
                    'ma' => $x['ma'] ?? null,
                    'ten' => $x['ten'],
                    'loai' => $x['loai'] ?? 'phuong',
                ];
            }
            // upsert: chạy seeder nhiều lần không bị trùng
            XaPhuong::upsert($dong, ['tinh_thanh_id', 'ten'], ['ma', 'loai']);
        }
    }

    /** Một số phường mẫu (đơn vị sau sắp xếp 2025) — chỉ để chạy thử. */
    private function duLieuMau(): array
    {
        $mau = [
            'Hồ Chí Minh' => ['Phường Sài Gòn', 'Phường Tân Định', 'Phường Bến Thành', 'Phường Cầu Ông Lãnh', 'Phường Gò Vấp', 'Phường Thủ Đức'],
            'Hà Nội' => ['Phường Hoàn Kiếm', 'Phường Ba Đình', 'Phường Cầu Giấy', 'Phường Đống Đa', 'Phường Hai Bà Trưng'],
            'Đà Nẵng' => ['Phường Hải Châu', 'Phường Thanh Khê', 'Phường Sơn Trà', 'Phường Ngũ Hành Sơn'],
            'Cần Thơ' => ['Phường Ninh Kiều', 'Phường Cái Khế'],
        ];

        $kq = [];
        foreach ($mau as $tinh => $ds) {
            foreach ($ds as $ten) {
                $kq[] = ['tinh' => $tinh, 'ten' => $ten, 'loai' => 'phuong'];
            }
        }

        return $kq;
    }
}
