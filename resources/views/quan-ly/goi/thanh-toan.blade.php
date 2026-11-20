@extends('layouts.app')
@section('title', 'Thanh toán gói')

@section('content')
<div class="mx-auto text-center" style="max-width: 460px">
    <h1 class="h5">Đơn gia hạn #{{ $tb->id }}</h1>
    <div class="small text-muted mb-2">Gói {{ $tb->goiThueBao->ten }} · {{ $tb->so_thang }} tháng</div>
    <div class="fs-3 fw-bold text-success mb-2">{{ number_format($tb->so_tien, 0, ',', '.') }}đ</div>

    @if ($tb->trang_thai !== 'cho_thanh_toan')
        <div class="alert alert-{{ $tb->trang_thai === 'da_thanh_toan' ? 'success' : 'secondary' }}">{{ \App\Models\ThueBao::TRANG_THAI[$tb->trang_thai] }}</div>
    @elseif ($qr)
        <div class="d-inline-block p-2 bg-white border rounded mb-2" style="max-width: 280px">{!! $qr !!}</div>
        <ul class="list-group text-start small mb-3">
            <li class="list-group-item d-flex justify-content-between"><span>Ngân hàng</span><strong>{{ \App\Support\VietQr::tenNganHang($bin) }}</strong></li>
            <li class="list-group-item d-flex justify-content-between"><span>Số tài khoản</span><strong>{{ $so }}</strong></li>
            <li class="list-group-item d-flex justify-content-between"><span>Chủ tài khoản</span><strong>{{ $ten }}</strong></li>
            <li class="list-group-item d-flex justify-content-between"><span>Nội dung</span><strong>{{ $tb->noiDungChuyenKhoan() }}</strong></li>
        </ul>
        <p class="small text-muted">Quét mã bằng app ngân hàng. Quản trị viên xác nhận xong, hạn dùng được cộng ngay.
            @if ($sdtAdmin) Cần gấp gọi {{ $sdtAdmin }}. @endif</p>
    @else
        <div class="alert alert-warning">Quản trị viên chưa cài tài khoản nhận tiền. @if ($sdtAdmin) Vui lòng gọi {{ $sdtAdmin }}. @endif</div>
    @endif

    <div class="d-flex justify-content-center gap-2">
        <a href="{{ route('quan-ly.goi.index') }}" class="btn btn-secondary btn-sm">Quay lại</a>
        @if ($tb->trang_thai === 'cho_thanh_toan')
            <form method="POST" action="{{ route('quan-ly.goi.huy', $tb) }}">@csrf @method('PATCH')<button class="btn btn-danger btn-sm">Huỷ đơn</button></form>
        @endif
    </div>
</div>
@endsection
