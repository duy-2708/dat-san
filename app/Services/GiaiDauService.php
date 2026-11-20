<?php

namespace App\Services;

use App\Models\Doi;
use App\Models\DoiGiaiDau;
use App\Models\GiaiDau;
use App\Models\TranDau;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Giải đấu do chủ sân tổ chức:
 * mở đăng ký → đội đăng ký (chụp danh sách cầu thủ) → chủ sân duyệt → đóng đăng ký (vòng tròn: tự xếp lịch)
 * → nhập kết quả → bảng xếp hạng → kết thúc: chốt thứ hạng & danh hiệu (hiện trên hồ sơ cầu thủ và trang đội).
 */
class GiaiDauService
{
    public function dangKy(User $doiTruong, GiaiDau $g, Doi $doi, array $cauThuIds): DoiGiaiDau
    {
        if ($g->trang_thai !== 'mo_dang_ky') {
            $this->loi('Giải đã đóng đăng ký.');
        }
        if (! $doi->laDoiTruong($doiTruong)) {
            $this->loi('Chỉ đội trưởng đăng ký được.');
        }
        if (DoiGiaiDau::where('giai_dau_id', $g->id)->where('doi_id', $doi->id)->exists()) {
            $this->loi('Đội đã đăng ký giải này.');
        }
        if ($g->doiDaDuyet()->count() >= $g->so_doi_toi_da) {
            $this->loi('Giải đã đủ đội.');
        }
        $hopLe = $doi->dangChoi()->whereIn('user_id', $cauThuIds)->pluck('user_id')->all();
        if (! $hopLe || count($hopLe) !== count(array_unique($cauThuIds))) {
            $this->loi('Chọn cầu thủ là thành viên đang chơi của đội (ít nhất 1 người).');
        }

        return DB::transaction(function () use ($g, $doi, $hopLe) {
            $dk = new DoiGiaiDau;
            $dk->forceFill(['giai_dau_id' => $g->id, 'doi_id' => $doi->id, 'trang_thai' => 'cho_duyet'])->save();
            $dk->cauThus()->sync($hopLe);
            \App\Support\BaoTin::gui(\App\Support\BaoTin::phiaSan($g->chu_san_id), 'Đội đăng ký giải', $doi->ten.' đăng ký giải '.$g->ten.'. Vào duyệt nhé.', route('giai-dau.show', $g), 'trophy');

            return $dk;
        });
    }

    public function duyet(DoiGiaiDau $dk, bool $dongY): void
    {
        $g = $dk->giaiDau;
        if ($g->trang_thai !== 'mo_dang_ky' || $dk->trang_thai !== 'cho_duyet') {
            $this->loi('Không còn duyệt được đăng ký này.');
        }
        if ($dongY && $g->doiDaDuyet()->count() >= $g->so_doi_toi_da) {
            $this->loi('Giải đã đủ '.$g->so_doi_toi_da.' đội.');
        }
        $dk->forceFill(['trang_thai' => $dongY ? 'da_duyet' : 'tu_choi'])->save();
        \App\Support\BaoTin::gui($dk->doi->doiTruong, $dongY ? 'Đội được duyệt vào giải' : 'Đăng ký giải bị từ chối',
            $dk->doi->ten.' — giải '.$g->ten.'.', route('giai-dau.show', $g), $dongY ? 'trophy' : 'x-circle');
    }

    /** Đóng đăng ký, bắt đầu giải. Thể thức vòng tròn: tự sinh lịch mọi cặp gặp nhau 1 lần. */
    public function dongDangKy(GiaiDau $g): int
    {
        if ($g->trang_thai !== 'mo_dang_ky') {
            $this->loi('Giải không ở trạng thái nhận đăng ký.');
        }
        $ids = $g->doiDaDuyet()->pluck('doi_id')->all();
        if (count($ids) < 2) {
            $this->loi('Cần ít nhất 2 đội đã duyệt.');
        }

        return DB::transaction(function () use ($g, $ids) {
            $g->forceFill(['trang_thai' => 'dang_dien_ra'])->save();
            $so = 0;
            if ($g->the_thuc === 'vong_tron') {
                foreach (self::lichVongTron($ids) as [$vong, $nha, $khach]) {
                    $this->taoTran($g, $nha, $khach, 'Vòng '.$vong);
                    $so++;
                }
            }

            return $so;
        });
    }

    /**
     * Xếp lịch vòng tròn bằng "phương pháp xoay vòng": giữ cố định đội đầu, xoay các đội còn lại.
     * n đội → n(n-1)/2 trận, chia thành (n-1) vòng (lẻ đội thì mỗi vòng 1 đội nghỉ).
     *
     * @return array<int, array{0:int,1:int,2:int}> [vòng, đội nhà, đội khách]
     */
    public static function lichVongTron(array $ids): array
    {
        if (count($ids) % 2 === 1) {
            $ids[] = null; // đội "nghỉ"
        }
        $n = count($ids);
        $kq = [];
        for ($vong = 1; $vong < $n; $vong++) {
            for ($i = 0; $i < $n / 2; $i++) {
                [$a, $b] = [$ids[$i], $ids[$n - 1 - $i]];
                if ($a !== null && $b !== null) {
                    $kq[] = $vong % 2 ? [$vong, $a, $b] : [$vong, $b, $a]; // đổi sân nhà/khách cho công bằng
                }
            }
            $coDinh = array_shift($ids);
            array_unshift($ids, $coDinh, array_pop($ids));       // xoay: phần tử cuối lên vị trí 2
        }

        return $kq;
    }

    public function themTran(GiaiDau $g, int $nha, int $khach, string $vong, ?string $thoiGian = null, ?int $sanId = null): TranDau
    {
        if (! in_array($g->trang_thai, ['mo_dang_ky', 'dang_dien_ra'], true)) {
            $this->loi('Giải đã kết thúc.');
        }
        $daDuyet = $g->doiDaDuyet()->pluck('doi_id')->all();
        if ($nha === $khach || ! in_array($nha, $daDuyet) || ! in_array($khach, $daDuyet)) {
            $this->loi('Chọn 2 đội khác nhau, đã được duyệt vào giải.');
        }
        if ($sanId && ! \App\Models\San::where('id', $sanId)->where('chu_san_id', $g->chu_san_id)->exists()) {
            $this->loi('Sân không thuộc chủ giải.');
        }

        return $this->taoTran($g, $nha, $khach, $vong, $thoiGian, $sanId);
    }

    public function nhapKetQua(TranDau $t, int $banNha, int $banKhach): void
    {
        if ($t->giaiDau->trang_thai !== 'dang_dien_ra') {
            $this->loi('Chỉ nhập kết quả khi giải đang diễn ra.');
        }
        $t->forceFill(['ban_thang_nha' => $banNha, 'ban_thang_khach' => $banKhach, 'trang_thai' => 'da_xong'])->save();
    }

    /**
     * Bảng xếp hạng (thắng 3, hoà 1, thua 0; bằng điểm xét hiệu số rồi bàn thắng).
     *
     * @return Collection<int, array>
     */
    public function bangXepHang(GiaiDau $g): Collection
    {
        $bang = [];
        foreach ($g->doiDaDuyet()->with('doi')->get() as $dk) {
            $bang[$dk->doi_id] = ['dk' => $dk, 'doi' => $dk->doi, 'tran' => 0, 'thang' => 0, 'hoa' => 0, 'thua' => 0, 'bt' => 0, 'bb' => 0, 'diem' => 0];
        }
        foreach ($g->tranDaus()->where('trang_thai', 'da_xong')->get() as $t) {
            foreach ([[$t->doi_nha_id, $t->ban_thang_nha, $t->ban_thang_khach], [$t->doi_khach_id, $t->ban_thang_khach, $t->ban_thang_nha]] as [$id, $ghi, $thung]) {
                if (! isset($bang[$id])) {
                    continue;
                }
                $r = &$bang[$id];
                $r['tran']++;
                $r['bt'] += $ghi;
                $r['bb'] += $thung;
                if ($ghi > $thung) { $r['thang']++; $r['diem'] += 3; }
                elseif ($ghi === $thung) { $r['hoa']++; $r['diem'] += 1; }
                else { $r['thua']++; }
                unset($r);
            }
        }

        return collect($bang)->map(fn ($r) => $r + ['hs' => $r['bt'] - $r['bb']])
            ->sort(fn ($x, $y) => [$y['diem'], $y['hs'], $y['bt']] <=> [$x['diem'], $x['hs'], $x['bt']])
            ->values();
    }

    /** @param array<int, array{thu_hang:?int, danh_hieu:?string}> $xepHang khoá = doi_giai_dau.id */
    public function ketThuc(GiaiDau $g, array $xepHang): void
    {
        if ($g->trang_thai !== 'dang_dien_ra') {
            $this->loi('Chỉ kết thúc được giải đang diễn ra.');
        }
        DB::transaction(function () use ($g, $xepHang) {
            foreach ($g->doiDaDuyet()->get() as $dk) {
                $x = $xepHang[$dk->id] ?? [];
                $dk->forceFill(['thu_hang' => $x['thu_hang'] ?? null, 'danh_hieu' => $x['danh_hieu'] ?? null])->save();
            }
            $g->forceFill(['trang_thai' => 'ket_thuc'])->save();
        });
    }

    /** Thành tích của 1 cầu thủ ở các giải ĐÃ KẾT THÚC (theo danh sách chụp lúc đăng ký). */
    public function thanhTichCauThu(User $u): Collection
    {
        return DoiGiaiDau::with(['giaiDau', 'doi'])
            ->where('trang_thai', 'da_duyet')
            ->whereHas('giaiDau', fn ($q) => $q->where('trang_thai', 'ket_thuc'))
            ->whereHas('cauThus', fn ($q) => $q->where('users.id', $u->id))
            ->get()
            ->sortBy(fn ($dk) => $dk->thu_hang ?? 999)
            ->values();
    }

    private function taoTran(GiaiDau $g, int $nha, int $khach, string $vong, ?string $thoiGian = null, ?int $sanId = null): TranDau
    {
        $t = new TranDau;
        $t->forceFill(['giai_dau_id' => $g->id, 'doi_nha_id' => $nha, 'doi_khach_id' => $khach, 'vong' => $vong,
            'thoi_gian' => $thoiGian, 'san_id' => $sanId, 'trang_thai' => 'chua_da'])->save();
        $doi = \App\Models\Doi::with('doiTruong')->whereIn('id', [$nha, $khach])->get();
        if ($thoiGian) {
            \App\Support\BaoTin::gui($doi->pluck('doiTruong'), 'Lịch trận giải '.$g->ten,
                $doi->pluck('ten')->join(' ⚔ ').' · '.$vong.' · '.\Carbon\Carbon::parse($thoiGian)->format('H:i d/m/Y').'.', route('giai-dau.show', $g), 'calendar-event');
        }

        return $t;
    }

    private function loi(string $s): never
    {
        throw ValidationException::withMessages(['giai' => $s]);
    }
}
