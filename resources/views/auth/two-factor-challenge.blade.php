@extends('layouts.app')
@section('title', 'Xác minh 2 bước')

@section('content')
<div class="the-form mx-auto">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h4 mb-2 text-center"><i class="bi bi-shield-lock"></i> Xác minh 2 bước</h1>
            <p class="small text-muted text-center">Mở app <strong>Google Authenticator</strong> (hoặc Microsoft Authenticator) và nhập mã 6 số của {{ config('app.name') }}.</p>
            @if ($errors->any()) <div class="alert alert-danger py-2 small">Mã không đúng hoặc đã hết hạn. Thử lại.</div> @endif

            <form method="POST" action="{{ route('two-factor.login.store') }}" id="formMa">
                @csrf
                <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus placeholder="123456"
                       class="form-control form-control-lg text-center mb-3" style="letter-spacing: .5rem">
                <button class="btn btn-success w-100">Xác nhận</button>
            </form>

            <details class="mt-3 small">
                <summary>Mất điện thoại? Dùng mã dự phòng</summary>
                <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-2">
                    @csrf
                    <input name="recovery_code" autocomplete="off" placeholder="xxxxxxxxxx-xxxxxxxxxx" class="form-control mb-2">
                    <button class="btn btn-secondary w-100">Dùng mã dự phòng</button>
                    <div class="form-text">Mỗi mã dự phòng chỉ dùng được 1 lần.</div>
                </form>
            </details>
        </div>
    </div>
</div>
@endsection
