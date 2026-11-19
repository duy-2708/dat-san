<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoiThueBao;
use App\Models\HoSoChuSan;
use App\Models\User;
use App\Services\ThueBaoService;
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

        return view('admin.chu-san.index', compact('chuSans', 'tuKhoa') + ['gois' => GoiThueBao::orderBy('gia_thang')->get()]);
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

    /** Gia hạn tay: tặng ngày, nhận tiền mặt, đổi gói. */
    public function giaHan(Request $request, User $user, ThueBaoService $sv): RedirectResponse
    {
        abort_unless($user->laChuSan(), 404);
        $d = $request->validate([
            'so_ngay' => ['required', 'integer', 'min:1', 'max:3650'],
            'goi_thue_bao_id' => ['nullable', 'exists:goi_thue_bao,id'],
            'so_tien' => ['nullable', 'integer', 'min:0'],
            'ghi_chu' => ['nullable', 'string', 'max:255'],
        ]);
        $sv->giaHanTay($user, $request->user(), (int) $d['so_ngay'],
            isset($d['goi_thue_bao_id']) ? GoiThueBao::find($d['goi_thue_bao_id']) : null, (int) ($d['so_tien'] ?? 0), $d['ghi_chu'] ?? null);

        return back()->with('thanh_cong', "Đã gia hạn {$d['so_ngay']} ngày cho {$user->name}.");
    }
}
