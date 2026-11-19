<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\AnhSan;
use App\Models\San;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AnhSanController extends Controller
{
    public const TOI_DA = 10;

    public function store(Request $request, San $san): RedirectResponse
    {
        Gate::authorize('update', $san);

        $conLai = self::TOI_DA - AnhSan::where('san_id', $san->id)->count();
        $request->validate([
            'anh' => ['required', 'array', 'min:1', 'max:'.max($conLai, 0)],
            'anh.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'], // tối đa 4MB/ảnh
        ], [
            'anh.max' => 'Mỗi sân tối đa '.self::TOI_DA." ảnh (còn thêm được {$conLai}).",
            'anh.*.max' => 'Mỗi ảnh tối đa 4MB.',
        ]);

        $thuTu = (int) AnhSan::where('san_id', $san->id)->max('thu_tu');
        foreach ($request->file('anh') as $tep) {
            // Lưu vào storage/app/public/san/{id}/tên-ngẫu-nhiên.jpg (tên do Laravel sinh → không lộ/ghi đè tên file gốc)
            $duongDan = $tep->store("san/{$san->id}", 'public');
            $san->anhSans()->create(['duong_dan' => $duongDan, 'thu_tu' => ++$thuTu]);
        }

        return back()->with('thanh_cong', 'Đã tải ảnh lên.');
    }

    /** Đưa ảnh lên đầu → thành ảnh đại diện. */
    public function lenDau(AnhSan $anh): RedirectResponse
    {
        Gate::authorize('update', $anh->san);

        DB::transaction(function () use ($anh) {
            AnhSan::where('san_id', $anh->san_id)->increment('thu_tu');
            $anh->update(['thu_tu' => 0]);
        });

        return back()->with('thanh_cong', 'Đã đặt làm ảnh đại diện.');
    }

    public function destroy(AnhSan $anh): RedirectResponse
    {
        Gate::authorize('update', $anh->san);

        Storage::disk('public')->delete($anh->duong_dan);
        $anh->delete();

        return back()->with('thanh_cong', 'Đã xoá ảnh.');
    }
}
