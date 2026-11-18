<?php

namespace App\Http\Controllers\HoSo;

use App\Http\Controllers\Controller;
use App\Support\ChuanHoa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Bổ sung SĐT cho tài khoản tạo bằng Google. (OTP sẽ gắn vào đây khi bật.) */
class SoDienThoaiController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->so_dien_thoai) {
            return redirect()->route('bang-dieu-khien');
        }

        return view('ho-so.so-dien-thoai');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['so_dien_thoai' => ChuanHoa::soDienThoai($request->input('so_dien_thoai'))]);

        $request->validate([
            'so_dien_thoai' => ['required', 'regex:'.ChuanHoa::REGEX_SDT, 'unique:users,so_dien_thoai'],
        ], [
            'so_dien_thoai.regex' => 'Số điện thoại di động không hợp lệ (VD 0901234567).',
            'so_dien_thoai.unique' => 'Số điện thoại này đã có tài khoản.',
        ]);

        $user = $request->user();
        $user->so_dien_thoai = $request->input('so_dien_thoai');
        $user->save();

        return redirect()->route('bang-dieu-khien')->with('thanh_cong', 'Đã lưu số điện thoại.');
    }
}
