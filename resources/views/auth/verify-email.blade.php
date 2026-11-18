@extends('layouts.app')
@section('title', 'Xác thực email')

@section('content')
<div class="the-form mx-auto text-center">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <i class="bi bi-envelope-check display-4 text-success"></i>
            <h1 class="h4 my-3">Kiểm tra hộp thư của bạn</h1>
            <p>Chúng tôi đã gửi link xác thực tới <strong>{{ auth()->user()->email }}</strong>.
               Bấm vào link đó để bắt đầu sử dụng. Không thấy thư? Xem cả mục <em>Spam/Quảng cáo</em>.</p>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn btn-success w-100 mb-2">Gửi lại email xác thực</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-link text-muted">Đăng xuất</button>
            </form>
        </div>
    </div>
</div>
@endsection
