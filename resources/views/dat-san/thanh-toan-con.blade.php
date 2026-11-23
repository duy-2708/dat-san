@extends('layouts.app')
@section('title', 'Trả tại sân '.$don->ma_don)

@section('content')
@php $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ'; @endphp
<div class="mx-auto" style="max-width: 460px">
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
            <div class="small text-muted">{{ $don->ma_don }}</div>
            <h1 class="h5 mb-1">{{ $don->san->tenHienThi() }}</h1>
            <div class="mb-3">{{ $don->batDauLuc()->format('d/m/Y H:i') }}</div>
            <div class="alert alert-warning py-2 small mb-3">Cần trả thêm <strong>{{ $tien($conLai) }}</strong> cho sân.</div>

            @if ($qr)
                <div class="d-inline-block p-2 bg-white border rounded mb-2" style="max-width: 280px">{!! $qr !!}</div>
                <p class="small text-muted mb-3">Mở app ngân hàng hoặc ví → quét QR để chuyển khoản. Số tiền và nội dung đã điền sẵn.</p>
                <ul class="list-group list-group-flush text-start small mb-3">
                    <li class="list-group-item d-flex justify-content-between"><span>Ngân hàng</span><strong>{{ \App\Support\VietQr::tenNganHang($hoSo->ngan_hang_bin) }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Số tài khoản</span><strong>{{ $hoSo->so_tai_khoan }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Chủ tài khoản</span><strong>{{ $hoSo->ten_chu_tai_khoan }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Số tiền</span><strong class="text-success">{{ $tien($conLai) }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Nội dung CK</span><strong class="font-monospace">{{ $don->ma_don }}</strong></li>
                </ul>
            @else
                <p class="small text-muted mb-3">Sân chưa cài tài khoản nhận chuyển khoản. Bạn trả tiền mặt tại sân.</p>
            @endif

            <div class="small text-muted"><i class="bi bi-info-circle"></i> Đã chuyển hoặc đưa tiền mặt cho sân thì sân sẽ ghi nhận vào đơn. Muốn trả bằng tiền mặt thì không cần quét QR.</div>
        </div>
    </div>
</div>
@endsection
