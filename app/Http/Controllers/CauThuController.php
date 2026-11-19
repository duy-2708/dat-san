<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GiaiDauService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Hồ sơ cầu thủ (công khai) + tự sửa hồ sơ. */
class CauThuController extends Controller
{
    public const VI_TRI = ['thu_mon' => 'Thủ môn', 'hau_ve' => 'Hậu vệ', 'tien_ve' => 'Tiền vệ', 'tien_dao' => 'Tiền đạo'];

    public function show(User $user, GiaiDauService $giai): View
    {
        abort_unless($user->laKhach(), 404);

        return view('cau-thu.show', [
            'u' => $user->load('doiDangChoi'),
            'thanhTich' => $giai->thanhTichCauThu($user),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('cau-thu.sua', ['u' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'vi_tri_so_truong' => ['nullable', Rule::in(array_keys(self::VI_TRI))],
            'anh' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $u = $request->user();
        if ($request->hasFile('anh')) {
            if ($u->anh_dai_dien && ! str_starts_with($u->anh_dai_dien, 'http')) {
                Storage::disk('public')->delete($u->anh_dai_dien);
            }
            $u->anh_dai_dien = $request->file('anh')->store('anh-dai-dien', 'public');
        }
        $u->fill(['name' => $d['name'], 'vi_tri_so_truong' => $d['vi_tri_so_truong'] ?? null])->save();

        return redirect()->route('cau-thu.show', $u)->with('thanh_cong', 'Đã lưu hồ sơ.');
    }
}
