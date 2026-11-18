@extends('layouts.app')
@section('title', 'Đăng nhập')

@section('content')
<div class="the-form mx-auto">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h4 mb-3 text-center">Đăng nhập</h1>

            <a href="{{ route('google.redirect') }}" class="btn btn-outline-dark w-100 mb-3">
                <i class="bi bi-google me-1"></i> Đăng nhập bằng Google
            </a>
            <div class="text-center text-muted small mb-3">hoặc dùng email</div>

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mật khẩu</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required
                           class="form-control @error('password') is-invalid @enderror">
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Ghi nhớ</label>
                    </div>
                    <a href="{{ route('password.request') }}" class="small">Quên mật khẩu?</a>
                </div>
                <button class="btn btn-success w-100">Đăng nhập</button>
            </form>
        </div>
    </div>
    <p class="text-center mt-3">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký</a></p>
</div>
@endsection
