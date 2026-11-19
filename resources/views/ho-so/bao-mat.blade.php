@extends('layouts.app')
@section('title', 'Bảo mật tài khoản')

@section('content')
<div class="mx-auto" style="max-width: 640px">
    <h1 class="h4 mb-3"><i class="bi bi-shield-lock"></i> Bảo mật tài khoản</h1>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6 d-flex justify-content-between align-items-center">Xác minh 2 bước
                @if ($u->two_factor_confirmed_at) <span class="badge bg-success">Đang bật</span>
                @else <span class="badge bg-secondary">Chưa bật</span> @endif
            </h2>
            <p class="small text-muted mb-3">Khi bật, đăng nhập cần thêm mã 6 số đổi mỗi 30 giây trên điện thoại.
                Kẻ gian lấy được mật khẩu cũng không vào được. <strong>Rất nên bật</strong> với tài khoản admin và chủ sân.</p>

            @if (! $u->password)
                <div class="alert alert-info small mb-0">Bạn đăng nhập bằng Google — hãy bật xác minh 2 bước trong tài khoản Google của bạn
                    (myaccount.google.com → Bảo mật). Tài khoản này được Google bảo vệ.</div>
            @elseif (! $u->two_factor_secret)
                <ol class="small mb-3">
                    <li>Cài app <strong>Google Authenticator</strong> (hoặc Microsoft Authenticator) trên điện thoại — miễn phí.</li>
                    <li>Bấm <em>Bật xác minh 2 bước</em>, quét mã QR hiện ra, nhập mã 6 số để xác nhận.</li>
                    <li>Chép <strong>mã dự phòng</strong> cất nơi an toàn (dùng khi mất điện thoại).</li>
                </ol>
                <form method="POST" action="{{ route('two-factor.enable') }}">@csrf
                    <button class="btn btn-success"><i class="bi bi-shield-plus"></i> Bật xác minh 2 bước</button>
                </form>
            @elseif (! $u->two_factor_confirmed_at)
                <p class="small mb-2"><strong>Bước 2:</strong> mở app xác thực → dấu <strong>+</strong> → <em>Quét mã QR</em>:</p>
                <div class="bg-white p-2 border rounded d-inline-block mb-2">{!! $u->twoFactorQrCodeSvg() !!}</div>
                <p class="small text-muted">Không quét được? Nhập khoá thủ công: <code class="user-select-all">{{ decrypt($u->two_factor_secret) }}</code></p>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="d-flex gap-2" style="max-width: 320px">@csrf
                    <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="Mã 6 số" required
                           class="form-control @error('code', 'confirmTwoFactorAuthentication') is-invalid @enderror">
                    <button class="btn btn-success text-nowrap">Xác nhận</button>
                </form>
                @error('code', 'confirmTwoFactorAuthentication') <div class="text-danger small mt-1">Mã không đúng, thử lại (đồng hồ điện thoại phải đúng giờ).</div> @enderror
                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-2">@csrf @method('DELETE')
                    <button class="btn btn-link btn-sm p-0 text-muted">Huỷ</button>
                </form>
            @else
                <p class="small mb-1"><strong>Mã dự phòng</strong> — mỗi mã dùng 1 lần khi mất điện thoại. Chép ra giấy hoặc trình quản lý mật khẩu:</p>
                <div class="bg-light border rounded p-2 mb-2 font-monospace small row row-cols-2 g-1 mx-0">
                    @foreach ($u->recoveryCodes() as $ma) <div class="col user-select-all">{{ $ma }}</div> @endforeach
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">@csrf
                        <button class="btn btn-sm btn-outline-secondary">Tạo bộ mã dự phòng mới</button>
                    </form>
                    @unless ($u->laAdmin() && config('bao_mat.admin_2fa'))
                        <form method="POST" action="{{ route('two-factor.disable') }}" onsubmit="return confirm('Tắt xác minh 2 bước? Tài khoản sẽ kém an toàn hơn.')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Tắt xác minh 2 bước</button>
                        </form>
                    @endunless
                </div>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h6">Hoạt động đăng nhập gần đây</h2>
            <p class="small text-muted">Thấy lần đăng nhập lạ? Đổi mật khẩu ngay và bật xác minh 2 bước.</p>
            <ul class="list-group list-group-flush small">
                @forelse ($lichSu as $n)
                    <li class="list-group-item px-0 d-flex justify-content-between gap-2">
                        <span class="{{ $n->hanh_dong === 'dang_nhap' ? '' : 'text-danger' }}">{{ $n->nhan() }}</span>
                        <span class="text-muted text-end">{{ $n->created_at->format('d/m/Y H:i') }} · IP {{ $n->ip }}</span>
                    </li>
                @empty
                    <li class="list-group-item px-0 text-muted">Chưa có dữ liệu.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
