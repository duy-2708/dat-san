<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Đặt sân bóng') — {{ config('app.name') }}</title>
    {{-- Giai đoạn đầu dùng CDN cho nhanh; khi triển khai sẽ chuyển sang Vite --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f5; }
        .navbar-brand { font-weight: 800; font-size: 1.45rem; letter-spacing: -.3px; display: flex; align-items: center; gap: .4rem; }
        .navbar-brand .bong { font-size: 1.6rem; line-height: 1; display: inline-block; transition: transform .6s; }
        .navbar-brand:hover .bong { transform: rotate(360deg); }
        .the-form { max-width: 440px; }
        .btn, .form-control, .form-select { min-height: 44px; } /* vùng chạm đủ lớn trên điện thoại */
    </style>
    @stack('head')
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
    <div class="container">
        <a class="navbar-brand" href="{{ route('trang-chu') }}"><span class="bong">⚽</span> <span>{{ config('app.name') }}</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuChinh" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuChinh">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('keo.index') }}">Cáp kèo</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('giai-dau.index') }}">Giải đấu</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('doi.index') }}">Đội bóng</a></li>
                @auth
                    @if (auth()->user()->laAdmin())
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.chu-san.index') }}">Chủ sân</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.tai-khoan.index') }}">Tài khoản</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.thue-bao.index') }}">Thuê bao</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.goi.index') }}">Gói</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.cai-dat.edit') }}">Cài đặt</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.nhat-ky') }}">Nhật ký</a></li>
                    @elseif (auth()->user()->coVaiTro('chu_san', 'nhan_vien'))
                        <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.tong-quan') }}">Quản lý</a></li>
                        @php
                            $soBaoChuyen = \App\Models\DatSan::where('trang_thai', 'cho_thanh_toan')->whereNotNull('bao_da_chuyen_luc')
                                ->whereHas('san', fn ($q) => $q->where('chu_san_id', auth()->user()->idChuSanQuanLy()))->count()
                                + \App\Models\GiaoDich::where('chu_san_id', auth()->user()->idChuSanQuanLy())->where('trang_thai', 'cho_thanh_toan')
                                    ->whereNotNull('bao_da_chuyen_luc')->count();
                        @endphp
                        <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.dat-san.index') }}">Đơn đặt
                            @if ($soBaoChuyen) <span class="badge rounded-pill bg-danger" title="Khách báo đã chuyển, chờ kiểm tra">{{ $soBaoChuyen }}</span> @endif</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.san.index') }}">Sân & lịch</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.giai-dau.index') }}">Tổ chức giải</a></li>
                        @if (auth()->user()->laChuSan())
                            <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.thanh-toan.edit') }}">Thanh toán</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.nhan-vien.index') }}">Nhân viên</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.goi.index') }}">Gói dịch vụ</a></li>
                        @endif
                    @endif
                @endauth
            </ul>
            <ul class="navbar-nav">
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                            <span class="badge bg-light text-success ms-1">{{ auth()->user()->tenVaiTro() }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('bang-dieu-khien') }}">Bảng điều khiển</a></li>
                            @if (auth()->user()->laKhach())
                                <li><a class="dropdown-item" href="{{ route('cau-thu.show', auth()->user()) }}">Hồ sơ cầu thủ</a></li>
                                <li><a class="dropdown-item" href="{{ route('khach.doi.create') }}">Tạo đội</a></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('bao-mat') }}"><i class="bi bi-shield-lock"></i> Bảo mật tài khoản
                                @if (! auth()->user()->two_factor_confirmed_at) <span class="badge bg-warning text-dark">nên bật</span> @endif</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item text-danger">Đăng xuất</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Đăng nhập</a></li>
                    <li class="nav-item"><a class="btn btn-light btn-sm ms-lg-2 mt-2 mt-lg-0 d-flex align-items-center" href="{{ route('register') }}">Đăng ký</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    @include('partials.thong-bao')
    @yield('content')
</main>

<footer class="py-3 border-top bg-white small">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="text-muted">© {{ date('Y') }} ⚽ {{ config('app.name') }}</span>
        <span><i class="bi bi-person-badge"></i> Liên hệ hợp tác mở sân: <strong>Duy</strong> · MSSV <strong>66130649</strong></span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Ô tiền: gõ 100000 hiện 100.000; khi gửi form tự bỏ dấu chấm.
(function () {
    const dinhDang = (v) => { const so = String(v).replace(/\D/g, ''); return so ? Number(so).toLocaleString('vi-VN') : ''; };
    document.addEventListener('input', (e) => {
        if (! e.target.matches('input.o-tien')) return;
        const o = e.target, truoc = o.value.length, viTri = o.selectionStart;
        o.value = dinhDang(o.value);
        const p = Math.max(0, viTri + o.value.length - truoc);
        o.setSelectionRange(p, p);
    });
    document.querySelectorAll('input.o-tien').forEach((o) => { o.value = dinhDang(o.value); });
    document.addEventListener('submit', (e) => {
        e.target.querySelectorAll('input.o-tien').forEach((o) => { o.value = o.value.replace(/\D/g, ''); });
    }, true);
})();
</script>
<datalist id="ds-gio">@foreach (\App\Support\ThoiGian::cacMoc(0, 1440) as $m) @if (str_ends_with($m, ':00') || str_ends_with($m, ':30'))<option value="{{ $m }}">@endif @endforeach</datalist>
@stack('scripts')
</body>
</html>
