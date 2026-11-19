<?php

namespace App\Console\Commands;

use App\Models\TinhThanh;
use Database\Seeders\XaPhuongSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Chuyển file dữ liệu hành chính 2 cấp (34 tỉnh, 3.321 xã/phường/đặc khu — định dạng
 * provinces.open-api.vn/api/v2/?depth=2) sang database/data/xa_phuong.json rồi nạp vào CSDL.
 * Nạp kiểu upsert theo (tỉnh, tên) nên không đụng tới id các phường đang được sân dùng.
 */
class NapXaPhuong extends Command
{
    protected $signature = 'dia-chi:nap {file : File JSON tải từ provinces.open-api.vn/api/v2/?depth=2}';

    protected $description = 'Nạp đầy đủ xã/phường của 34 tỉnh thành';

    public function handle(): int
    {
        $nguon = json_decode(file_get_contents($this->argument('file')), true, flags: JSON_THROW_ON_ERROR);
        $tinhTheoKhoa = TinhThanh::all()->keyBy(fn ($t) => $this->khoa($t->ten));

        $kq = [];
        $thieuTinh = [];
        foreach ($nguon as $tinh) {
            $t = $tinhTheoKhoa[$this->khoa($tinh['name'] ?? '')] ?? null;
            if (! $t) {
                $thieuTinh[] = $tinh['name'] ?? '?';
                continue;
            }
            foreach ($tinh['wards'] ?? [] as $w) {
                $loai = Str::lower($w['division_type'] ?? '');
                $kq[] = [
                    'tinh' => $t->ten,
                    'ma' => str_pad((string) $w['code'], 5, '0', STR_PAD_LEFT),
                    'ten' => trim($w['name']),
                    'loai' => str_contains($loai, 'đặc khu') ? 'dac_khu' : (str_contains($loai, 'phường') ? 'phuong' : 'xa'),
                ];
            }
        }
        if ($thieuTinh) {
            $this->warn('Không khớp tỉnh: '.implode(', ', $thieuTinh));
        }
        if (count($kq) < 3000) {
            $this->error('Chỉ đọc được '.count($kq).' đơn vị — dữ liệu không đủ, dừng lại.');

            return self::FAILURE;
        }

        @mkdir(database_path('data'), 0777, true);
        file_put_contents(database_path('data/xa_phuong.json'), json_encode($kq, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->call('db:seed', ['--class' => XaPhuongSeeder::class, '--force' => true]);
        $this->info('Đã nạp '.count($kq).' xã/phường/đặc khu.');

        return self::SUCCESS;
    }

    /** "Tỉnh Khánh Hòa" / "Thành phố Hồ Chí Minh" / "Khánh Hoà" → "khanh-hoa" */
    private function khoa(string $ten): string
    {
        $ten = preg_replace('/^(Thành phố|Tỉnh|TP\.?)\s+/u', '', trim($ten));
        $ten = str_replace(['oà', 'oá', 'oả', 'oã', 'oạ', 'uỳ', 'uý', 'uỷ', 'uỹ', 'uỵ'], ['òa', 'óa', 'ỏa', 'õa', 'ọa', 'ùy', 'úy', 'ủy', 'ũy', 'ụy'], $ten);

        return Str::slug($ten);
    }
}
