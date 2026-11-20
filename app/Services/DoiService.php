<?php

namespace App\Services;

use App\Models\Doi;
use App\Models\ThanhVienDoi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Đội bóng: tạo đội, xin vào, đội trưởng duyệt / thêm / xoá thành viên, rời đội. */
class DoiService
{
    public const TOI_DA_THANH_VIEN = 30;

    public function taoDoi(User $u, array $d, ?string $logo): Doi
    {
        if (! $u->laKhach()) {
            $this->loi('Chỉ tài khoản khách mới tạo đội.');
        }

        return DB::transaction(function () use ($u, $d, $logo) {
            $doi = new Doi;
            $doi->forceFill([
                'ten' => $d['ten'], 'slug' => Str::slug($d['ten']).'-'.Str::lower(Str::random(5)),
                'logo' => $logo, 'doi_truong_id' => $u->id,
                'tinh_thanh_id' => $d['tinh_thanh_id'] ?? null, 'mo_ta' => $d['mo_ta'] ?? null,
            ])->save();
            $this->ghi($doi, $u, 'doi_truong', 'dang_choi');

            return $doi;
        });
    }

    public function xinVao(User $u, Doi $doi): ThanhVienDoi
    {
        if (! $u->laKhach()) {
            $this->loi('Chỉ tài khoản khách mới vào đội.');
        }
        $cu = ThanhVienDoi::where('doi_id', $doi->id)->where('user_id', $u->id)->first();
        if ($cu && $cu->trang_thai !== 'da_roi') {
            $this->loi($cu->trang_thai === 'cho_duyet' ? 'Bạn đã gửi yêu cầu, chờ đội trưởng duyệt.' : 'Bạn đã ở trong đội.');
        }

        \App\Support\BaoTin::gui($doi->doiTruong, 'Có người xin vào đội', $u->name.' xin vào '.$doi->ten.'.', route('doi.show', $doi), 'person-plus');

        return $this->ghi($doi, $u, 'thanh_vien', 'cho_duyet');
    }

    /** Đội trưởng thêm thẳng người đã có tài khoản (không cần chờ duyệt). */
    public function themThanhVien(User $doiTruong, Doi $doi, User $tv, ?int $soAo = null): ThanhVienDoi
    {
        $this->chiDoiTruong($doiTruong, $doi);
        if (! $tv->laKhach()) {
            $this->loi('Chỉ thêm được tài khoản khách.');
        }
        if ($doi->dangChoi()->where('user_id', $tv->id)->exists()) {
            $this->loi($tv->name.' đã ở trong đội.');
        }
        $this->kiemTraSiSo($doi);

        \App\Support\BaoTin::gui($tv, 'Bạn được thêm vào đội', $doiTruong->name.' đã thêm bạn vào '.$doi->ten.'.', route('doi.show', $doi), 'people');

        return $this->ghi($doi, $tv, 'thanh_vien', 'dang_choi', $soAo);
    }

    public function duyet(User $doiTruong, ThanhVienDoi $tv, bool $dongY): void
    {
        $this->chiDoiTruong($doiTruong, $tv->doi);
        if ($tv->trang_thai !== 'cho_duyet') {
            $this->loi('Yêu cầu này đã được xử lý.');
        }
        if ($dongY) {
            $this->kiemTraSiSo($tv->doi);
        }
        $tv->forceFill(['trang_thai' => $dongY ? 'dang_choi' : 'da_roi'])->save();
        \App\Support\BaoTin::gui($tv->user_id, $dongY ? 'Được duyệt vào đội' : 'Yêu cầu vào đội bị từ chối', $tv->doi->ten.'.', route('doi.show', $tv->doi), $dongY ? 'people' : 'x-circle');
    }

    /** Thành viên tự rời, hoặc đội trưởng mời ra. Đội trưởng không tự rời (phải chuyển quyền trước). */
    public function roiDoi(User $nguoi, ThanhVienDoi $tv): void
    {
        $doi = $tv->doi;
        $laChinhMinh = (int) $tv->user_id === $nguoi->id;
        if (! $laChinhMinh && ! $doi->laDoiTruong($nguoi)) {
            $this->loi('Bạn không có quyền.');
        }
        if ((int) $tv->user_id === (int) $doi->doi_truong_id) {
            $this->loi('Đội trưởng cần chuyển quyền đội trưởng cho người khác trước khi rời đội.');
        }
        $tv->forceFill(['trang_thai' => 'da_roi'])->save();
    }

    public function chuyenDoiTruong(User $doiTruong, Doi $doi, User $moi): void
    {
        $this->chiDoiTruong($doiTruong, $doi);
        if (! $doi->laThanhVien($moi)) {
            $this->loi('Đội trưởng mới phải là thành viên đội.');
        }
        DB::transaction(function () use ($doi, $doiTruong, $moi) {
            ThanhVienDoi::where('doi_id', $doi->id)->where('user_id', $doiTruong->id)->update(['vai_tro' => 'thanh_vien']);
            ThanhVienDoi::where('doi_id', $doi->id)->where('user_id', $moi->id)->update(['vai_tro' => 'doi_truong']);
            $doi->forceFill(['doi_truong_id' => $moi->id])->save();
        });
    }

    private function ghi(Doi $doi, User $u, string $vaiTro, string $trangThai, ?int $soAo = null): ThanhVienDoi
    {
        $tv = ThanhVienDoi::where('doi_id', $doi->id)->where('user_id', $u->id)->first() ?? new ThanhVienDoi;
        $tv->forceFill(['doi_id' => $doi->id, 'user_id' => $u->id, 'vai_tro' => $vaiTro, 'trang_thai' => $trangThai, 'so_ao' => $soAo])->save();

        return $tv;
    }

    private function kiemTraSiSo(Doi $doi): void
    {
        if ($doi->dangChoi()->count() >= self::TOI_DA_THANH_VIEN) {
            $this->loi('Đội đã đủ '.self::TOI_DA_THANH_VIEN.' thành viên.');
        }
    }

    private function chiDoiTruong(User $u, Doi $doi): void
    {
        if (! $doi->laDoiTruong($u)) {
            $this->loi('Chỉ đội trưởng làm được việc này.');
        }
    }

    private function loi(string $s): never
    {
        throw ValidationException::withMessages(['doi' => $s]);
    }
}
