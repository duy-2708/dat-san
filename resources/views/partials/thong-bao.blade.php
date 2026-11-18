@if (session('thanh_cong'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('thanh_cong') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('status'))
    {{-- Fortify trả về session('status') sau khi gửi link đặt lại mật khẩu, gửi lại email xác thực... --}}
    <div class="alert alert-info">
        @switch(session('status'))
            @case('verification-link-sent') Đã gửi lại email xác thực. Vui lòng kiểm tra hộp thư. @break
            @default {{ session('status') }}
        @endswitch
    </div>
@endif
