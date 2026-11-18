<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ChuanHoa;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Chủ sân quản lý nhân viên trực sân của mình. */
class NhanVienController extends Controller
{
    public function index(Request $request): View
    {
        // Chỉ lấy nhân viên CỦA MÌNH — lọc ngay trong truy vấn
        $nhanViens = $request->user()->nhanViens()->latest()->get();

        return view('quan-ly.nhan-vien.index', compact('nhanViens'));
    }

    public function create(): View
    {
        return view('quan-ly.nhan-vien.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'so_dien_thoai' => ChuanHoa::soDienThoai($request->input('so_dien_thoai')),
        ]);
        $request->merge(['email_chuan_hoa' => ChuanHoa::email($request->input('email'))]);

        $duLieu = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'email_chuan_hoa' => ['unique:users,email_chuan_hoa'],
            'so_dien_thoai' => ['required', 'regex:'.ChuanHoa::REGEX_SDT, 'unique:users,so_dien_thoai'],
            'password' => ['required', Password::min(8)],
        ], [
            'email_chuan_hoa.unique' => 'Email này (hoặc biến thể) đã có tài khoản.',
            'so_dien_thoai.regex' => 'Số điện thoại di động không hợp lệ.',
        ]);

        $nv = new User([
            'name' => $duLieu['name'],
            'email' => $duLieu['email'],
            'so_dien_thoai' => $duLieu['so_dien_thoai'],
            'password' => $duLieu['password'],
        ]);
        $nv->vai_tro = User::NHAN_VIEN;
        $nv->chu_san_id = $request->user()->id; // gắn cứng với chủ sân đang đăng nhập, không lấy từ form
        $nv->save();

        event(new Registered($nv));

        return redirect()->route('quan-ly.nhan-vien.index')->with('thanh_cong', "Đã tạo nhân viên {$nv->name}.");
    }

    public function khoa(User $nhanVien): RedirectResponse
    {
        Gate::authorize('quanLyNhanVien', $nhanVien);

        $nhanVien->trang_thai = $nhanVien->trang_thai === 'bi_khoa' ? 'hoat_dong' : 'bi_khoa';
        $nhanVien->save();

        return back()->with('thanh_cong', $nhanVien->trang_thai === 'bi_khoa'
            ? "Đã khoá nhân viên {$nhanVien->name}."
            : "Đã mở khoá nhân viên {$nhanVien->name}.");
    }
}
