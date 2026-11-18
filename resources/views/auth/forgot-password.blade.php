@extends('layouts.app')
@section('title', 'Quên mật khẩu')

@section('content')
<div class="the-form mx-auto">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h4 mb-2 text-center">Quên mật khẩu</h1>
            <p class="text-muted small text-center">Nhập email, chúng tôi gửi link đặt mật khẩu mới.</p>
            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn-success w-100">Gửi link</button>
            </form>
        </div>
    </div>
    <p class="text-center mt-3"><a href="{{ route('login') }}">← Quay lại đăng nhập</a></p>
</div>
@endsection
