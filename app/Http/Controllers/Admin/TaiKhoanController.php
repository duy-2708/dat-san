<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaiKhoanController extends Controller
{
    public function index(Request $request): View
    {
        $tuKhoa = trim((string) $request->query('q'));
        $vaiTro = $request->query('vai_tro');

        $users = User::query()
            ->when(array_key_exists((string) $vaiTro, User::TEN_VAI_TRO), fn ($q) => $q->where('vai_tro', $vaiTro))
            ->when($tuKhoa !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$tuKhoa}%")
                ->orWhere('email', 'like', "%{$tuKhoa}%")
                ->orWhere('so_dien_thoai', 'like', "%{$tuKhoa}%")))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.tai-khoan', compact('users', 'tuKhoa', 'vaiTro'));
    }

    public function khoa(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('khoaTaiKhoan', $user);

        $request->validate(['ly_do_khoa' => ['nullable', 'string', 'max:255']]);

        $user->trang_thai = 'bi_khoa';
        $user->ly_do_khoa = $request->input('ly_do_khoa');
        $user->save();

        return back()->with('thanh_cong', "Đã khoá tài khoản {$user->email}.");
    }

    public function moKhoa(User $user): RedirectResponse
    {
        Gate::authorize('khoaTaiKhoan', $user);

        $user->trang_thai = 'hoat_dong';
        $user->ly_do_khoa = null;
        $user->save();

        return back()->with('thanh_cong', "Đã mở khoá tài khoản {$user->email}.");
    }
}
