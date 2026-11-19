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
        .navbar-brand { font-weight: 700; letter-spacing: -.3px; }
        .the-form { max-width: 440px; }
        .btn, .form-control, .form-select { min-height: 44px; } /* vùng chạm đủ lớn trên điện thoại */
    </style>
    @stack('head')
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
    <div class="container">
        <a class="navbar-brand" href="{{ route('trang-chu') }}"><i class="bi bi-dribbble"></i> {{ config('app.name') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuChinh" aria-label="Mở menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuChinh">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('keo.index') }}">Cáp kèo</a></li>
                @auth
                    @if (auth()->user()->laAdmin())
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.chu-san.index') }}">Chủ sân</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.tai-khoan.index') }}">Tài khoản</a></li>
                    @elseif (auth()->user()->coVaiTro('chu_san', 'nhan_vien'))
                        <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.tong-quan') }}">Quản lý</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.dat-san.index') }}">Đơn đặt</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.san.index') }}">Sân & lịch</a></li>
                        @if (auth()->user()->laChuSan())
                            <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.thanh-toan.edit') }}">Thanh toán</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('quan-ly.nhan-vien.index') }}">Nhân viên</a></li>
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

<footer class="py-3 text-center text-muted small border-top bg-white">
    © {{ date('Y') }} {{ config('app.name') }}
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
