@extends('layouts.app')
@section('title', 'Đặt mật khẩu mới')

@section('content')
<div class="the-form mx-auto">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h4 mb-3 text-center">Đặt mật khẩu mới</h1>
            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mật khẩu mới</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                           class="form-control @error('password') is-invalid @enderror">
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required class="form-control">
                </div>
                <button class="btn btn-success w-100">Lưu mật khẩu</button>
            </form>
        </div>
    </div>
</div>
@endsection
