<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Sau khi đăng nhập Fortify chuyển về /bang-dieu-khien; ở đây chia đường theo vai trò. */
class BangDieuKhienController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return match ($request->user()->vai_tro) {
            'admin' => redirect()->route('admin.chu-san.index'),
            'chu_san', 'nhan_vien' => redirect()->route('quan-ly.tong-quan'),
            default => redirect()->route('khach.tai-khoan'),
        };
    }
}
