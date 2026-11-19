@extends('layouts.app')
@section('title', 'Cọc kèo '.$gd->noi_dung_ck)

@section('content')
@php $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ'; $k = $tg->keo; @endphp
<div class="mx-auto text-center" style="max-width: 440px">
    <div class="card border-0 shadow-sm"><div class="card-body">
        <h1 class="h5">Cọc vào kèo #{{ $k->id }}</h1>
        <div class="small text-muted mb-3">{{ $k->sanThuc()->ten }} · {{ $k->batDauLuc()->format('d/m') }} {{ $k->nhanGio() }}</div>

        @if ($gd->trang_thai === 'cho_thanh_toan' && $qr)
            <div class="alert alert-warning py-2 small">Giữ chỗ tới <strong>{{ $tg->giu_cho_den?->format('H:i') }}</strong></div>
            <div class="d-inline-block p-2 border rounded mb-2" style="max-width: 280px">{!! $qr !!}</div>
            <ul class="list-group list-group-flush text-start small mb-3">
                <li class="list-group-item d-flex justify-content-between"><span>Số tiền</span><strong class="text-success">{{ $tien($gd->so_tien) }}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>Nội dung CK</span><strong class="font-monospace">{{ $gd->noi_dung_ck }}</strong></li>
                @if ($gd->phuong_thuc !== 'payos')
                    <li class="list-group-item d-flex justify-content-between"><span>Ngân hàng</span><strong>{{ \App\Support\VietQr::tenNganHang($hoSo->ngan_hang_bin) }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Tài khoản</span><strong>{{ $hoSo->so_tai_khoan }} · {{ $hoSo->ten_chu_tai_khoan }}</strong></li>
                @endif
            </ul>
            <p class="small text-muted">Tiền vào tài khoản chủ sân. {{ $gd->phuong_thuc === 'payos' ? 'Chuyển xong tự xác nhận.' : 'Chủ sân xác nhận khi thấy tiền về.' }}</p>
            @if ($gd->phuong_thuc !== 'payos' && (int) $gd->nguoi_tra_id === auth()->id())
                @if ($gd->bao_da_chuyen_luc)
                    <div class="alert alert-info py-2 small">Bạn đã báo chuyển lúc {{ $gd->bao_da_chuyen_luc->format('H:i') }}. Chủ sân đã nhận email và đang kiểm tra.</div>
                @else
                    <form method="POST" action="{{ route('keo.coc.da-chuyen', $gd) }}" class="mb-3"
                          onsubmit="return confirm('Chỉ bấm khi ĐÃ chuyển xong. Báo sai sẽ mất chỗ trong kèo và bị trừ uy tín. Tiếp tục?')">@csrf
                        <button class="btn btn-primary w-100"><i class="bi bi-send-check"></i> Tôi đã chuyển khoản</button>
                        <div class="form-text">Bấm 1 lần: chủ sân nhận email, chỗ được giữ thêm 2 tiếng để kiểm tra.</div>
                    </form>
                @endif
            @endif
        @elseif ($gd->trang_thai === 'thanh_cong')
            <div class="alert alert-success">Đã cọc. Bạn đã ở trong kèo!</div>
        @else
            <div class="alert alert-secondary">Khoản cọc đã hết hạn / huỷ.</div>
        @endif
        <a href="{{ route('keo.show', $k) }}" class="btn btn-outline-secondary w-100">Về trang kèo</a>
    </div></div>
</div>
@endsection
