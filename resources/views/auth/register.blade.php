@extends('layouts.app')
@section('title', 'Đăng ký')

@section('content')
<div class="the-form mx-auto">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h4 mb-1 text-center">Tạo tài khoản</h1>
            <p class="text-muted small text-center mb-3">Mỗi email và mỗi số điện thoại chỉ đăng ký được 1 tài khoản.</p>

            <a href="{{ route('google.redirect') }}" class="btn btn-outline-dark w-100 mb-3">
                <i class="bi bi-google me-1"></i> Đăng ký bằng Google
            </a>

            <form method="POST" action="{{ route('register') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="name">Họ tên</label>
                    <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name"
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                           class="form-control @error('email') is-invalid @enderror @error('email_chuan_hoa') is-invalid @enderror">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @error('email_chuan_hoa') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Chúng tôi sẽ gửi link xác thực tới email này.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="so_dien_thoai">Số điện thoại</label>
                    <input id="so_dien_thoai" type="tel" inputmode="numeric" name="so_dien_thoai" value="{{ old('so_dien_thoai') }}" required autocomplete="tel"
                           placeholder="0901234567" class="form-control @error('so_dien_thoai') is-invalid @enderror">
                    @error('so_dien_thoai') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mật khẩu (tối thiểu 8 ký tự)</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                           class="form-control @error('password') is-invalid @enderror">
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-control">
                </div>
                <button class="btn btn-success w-100">Đăng ký</button>
            </form>
        </div>
    </div>
    <p class="text-center mt-3">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
</div>
@endsection
