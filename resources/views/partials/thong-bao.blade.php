@if (session('thanh_cong'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('thanh_cong') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('loi'))
    <div class="alert alert-danger">{{ session('loi') }}</div>
@endif
@if (session('status'))
    {{-- Fortify trả về session('status') sau khi gửi link đặt lại mật khẩu, gửi lại email xác thực... --}}
    <div class="alert alert-info">
        @switch(session('status'))
            @case('verification-link-sent') Đã gửi lại email xác thực. Vui lòng kiểm tra hộp thư. @break
            @case('two-factor-authentication-enabled') Quét mã QR bằng app xác thực rồi nhập mã 6 số để hoàn tất. @break
            @case('two-factor-authentication-confirmed') Đã bật xác minh 2 bước. Hãy lưu mã dự phòng ở nơi an toàn. @break
            @case('two-factor-authentication-disabled') Đã tắt xác minh 2 bước. @break
            @case('recovery-codes-generated') Đã tạo bộ mã dự phòng mới — bộ cũ hết hiệu lực. @break
            @default {{ session('status') }}
        @endswitch
    </div>
@endif
