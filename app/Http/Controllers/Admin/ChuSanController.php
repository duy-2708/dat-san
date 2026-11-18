<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoiThueBao;
use App\Models\HoSoChuSan;
use App\Models\User;
use App\Support\ChuanHoa;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Admin quản lý chủ sân (route đã chặn bằng middleware vai_tro:admin). */
class ChuSanController extends Controller
{
    public function index(Request $request): View
    {
        $tuKhoa = trim((string) $request->query('q'));

        $chuSans = User::query()
            ->where('vai_tro', User::CHU_SAN)
            ->with('hoSoChuSan.goiThueBao')
            ->withCount('nhanViens')
            ->when($tuKhoa !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$tuKhoa}%")
                ->orWhere('email', 'like', "%{$tuKhoa}%")
                ->orWhere('so_dien_thoai', 'like', "%{$tuKhoa}%")
                ->orWhereHas('hoSoChuSan', fn ($h) => $h->where('ten_co_so', 'like', "%{$tuKhoa}%"))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.chu-san.index', compact('chuSans', 'tuKhoa'));
    }

    public function create(): View
    {
        return view('admin.chu-san.create', ['goiThueBaos' => GoiThueBao::where('dang_ban', true)->get()]);
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
            'ten_co_so' => ['required', 'string', 'max:150'],
            'goi_thue_bao_id' => ['nullable', 'exists:goi_thue_bao,id'],
            'so_ngay_su_dung' => ['required', 'integer', 'min:1', 'max:3650'],
        ], [
            'email_chuan_hoa.unique' => 'Email này (hoặc biến thể) đã có tài khoản.',
            'so_dien_thoai.regex' => 'Số điện thoại di động không hợp lệ.',
        ]);

        $chuSan = DB::transaction(function () use ($duLieu) {
            $u = new User([
                'name' => $duLieu['name'],
                'email' => $duLieu['email'],
                'so_dien_thoai' => $duLieu['so_dien_thoai'],
                'password' => $duLieu['password'],
            ]);
            $u->vai_tro = User::CHU_SAN;
            $u->save();

            $hoSo = new HoSoChuSan(['ten_co_so' => $duLieu['ten_co_so'], 'sdt_lien_he' => $duLieu['so_dien_thoai']]);
            $hoSo->user_id = $u->id;
            $hoSo->goi_thue_bao_id = $duLieu['goi_thue_bao_id'] ?? null;
            $hoSo->het_han_luc = now()->addDays((int) $duLieu['so_ngay_su_dung']);
            $hoSo->save();

            return $u;
        });

        // Gửi email xác thực cho chủ sân
        event(new Registered($chuSan));

        return redirect()->route('admin.chu-san.index')
            ->with('thanh_cong', "Đã tạo chủ sân {$chuSan->name}. Email xác thực đã được gửi tới {$chuSan->email}.");
    }
}
