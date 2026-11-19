@extends('layouts.app')
@section('title', 'Xác nhận mật khẩu')

@section('content')
<div class="the-form mx-auto">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h5 mb-2">Xác nhận mật khẩu</h1>
            <p class="small text-muted">Đây là khu vực nhạy cảm. Nhập lại mật khẩu để tiếp tục (sẽ không hỏi lại trong 3 giờ).</p>
            <form method="POST" action="{{ route('password.confirm.store') }}">
                @csrf
                <input type="password" name="password" autocomplete="current-password" autofocus required
                       class="form-control mb-2 @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                <button class="btn btn-success w-100">Tiếp tục</button>
            </form>
        </div>
    </div>
</div>
@endsection
