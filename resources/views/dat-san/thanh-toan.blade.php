@extends('layouts.app')
@section('title', 'Cọc '.$don->ma_don)

@section('content')
@php $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ'; @endphp
<div class="mx-auto" style="max-width: 460px">
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
            <div class="small text-muted">{{ $don->ma_don }}</div>
            <h1 class="h5 mb-1">{{ $don->san->tenHienThi() }}</h1>
            <div class="mb-3">{{ \Carbon\Carbon::parse($don->ngay)->format('d/m/Y') }} · <strong>{{ $don->khungGioNhan() }}</strong></div>

            @if ($don->trang_thai === 'cho_thanh_toan' && $qr)
                <div id="khungQr">
                    <div class="alert alert-warning py-2 small mb-3">
                        Giữ chỗ tới <strong>{{ $don->giu_cho_den->format('H:i') }}</strong> — còn <strong id="demNguoc">--:--</strong>
                    </div>
                    <div class="d-inline-block p-2 bg-white border rounded mb-2" style="max-width: 280px">{!! $qr !!}</div>
                    <p class="small text-muted mb-3">Mở app ngân hàng hoặc ví (MoMo, ZaloPay...) → Quét QR. Số tiền và nội dung đã điền sẵn.</p>

                    @if ($gd?->phuong_thuc === 'payos')
                        <div class="alert alert-success py-2 small"><i class="bi bi-lightning-charge-fill"></i> Sân dùng cổng tự động: chuyển xong là <strong>tự xác nhận</strong>, không cần chờ chủ sân.</div>
                        @if (! empty($gd->du_lieu_cong['checkout_url']))
                            <a href="{{ $gd->du_lieu_cong['checkout_url'] }}" class="btn btn-outline-success btn-sm mb-3" rel="noopener">Mở trang thanh toán PayOS</a>
                        @endif
                    @endif
                    <ul class="list-group list-group-flush text-start small mb-3">
                        @if ($gd?->phuong_thuc !== 'payos')
                        <li class="list-group-item d-flex justify-content-between"><span>Ngân hàng</span><strong>{{ \App\Support\VietQr::tenNganHang($hoSo->ngan_hang_bin) }}</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Số tài khoản</span><strong>{{ $hoSo->so_tai_khoan }}</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Chủ tài khoản</span><strong>{{ $hoSo->ten_chu_tai_khoan }}</strong></li>
                        @endif
                        <li class="list-group-item d-flex justify-content-between"><span>Số tiền cọc</span><strong class="text-success">{{ $tien($don->tien_coc) }}</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Nội dung CK</span><strong class="font-monospace">{{ $don->ma_don }}</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Còn lại trả tại sân</span><span>{{ $tien($don->gia_chot - $don->tien_coc) }}</span></li>
                    </ul>
                    @if ($gd?->phuong_thuc !== 'payos' && $don->khach_id === auth()->id())
                        @if ($don->bao_da_chuyen_luc)
                            <div class="alert alert-info py-2 small"><i class="bi bi-envelope-check"></i> Bạn đã báo chuyển lúc {{ $don->bao_da_chuyen_luc->format('H:i') }}. Chủ sân đã nhận email và đang kiểm tra.</div>
                        @else
                            <form method="POST" action="{{ route('khach.dat-san.da-chuyen', $don) }}" class="mb-3"
                                  onsubmit="return confirm('Chỉ bấm khi ĐÃ chuyển xong. Báo sai sẽ bị huỷ đơn và trừ uy tín. Tiếp tục?')">@csrf
                                <button class="btn btn-primary w-100"><i class="bi bi-send-check"></i> Tôi đã chuyển khoản</button>
                                <div class="form-text">Bấm 1 lần: chủ sân nhận email, chỗ được giữ thêm 2 tiếng để chủ sân kiểm tra.</div>
                            </form>
                        @endif
                    @endif
                    <div class="small text-muted mb-3"><i class="bi bi-info-circle"></i> Tiền chuyển thẳng vào tài khoản chủ sân. Trang tự cập nhật khi chủ sân xác nhận đã nhận.
                        Đã chuyển mà sắp hết giờ? Gọi sân: <a href="tel:{{ $hoSo->sdt_lien_he }}">{{ $hoSo->sdt_lien_he }}</a></div>
                </div>
            @endif

            <div id="ketQua">
                @if ($don->trang_thai === 'da_dat')
                    <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Đặt sân thành công! Chủ sân đã nhận cọc.</div>
                @elseif ($don->trang_thai === 'da_huy')
                    <div class="alert alert-secondary">Đơn đã huỷ{{ $don->ly_do_huy ? ': '.$don->ly_do_huy : '' }}.</div>
                @endif
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('san.show', $don->san) }}" class="btn btn-outline-secondary flex-grow-1">Lịch sân</a>
                @can('huy', $don)
                    @if ($don->trang_thai !== 'da_huy')
                        <form method="POST" action="{{ route('dat-san.huy', $don) }}" onsubmit="return confirm(@js('Huỷ đơn '.$don->ma_don."\n".$don->moTaNgan().'?'))">
                            @csrf
                            <button class="btn btn-outline-danger">Huỷ</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection

@if ($don->trang_thai === 'cho_thanh_toan')
@push('scripts')
<script>
(function () {
    const het = new Date('{{ $don->giu_cho_den->toIso8601String() }}');
    const o = document.getElementById('demNguoc');
    setInterval(function () {
        const s = Math.max(0, Math.floor((het - new Date()) / 1000));
        if (o) o.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    }, 1000);
    // Hỏi trạng thái mỗi 5 giây: chủ sân xác nhận → tự tải lại
    setInterval(async function () {
        try {
            const r = await fetch('{{ route('dat-san.trang-thai', $don) }}', { headers: { 'Accept': 'application/json' } });
            const d = await r.json();
            if (d.trang_thai !== 'cho_thanh_toan') location.reload();
        } catch (e) {}
    }, 5000);
})();
</script>
@endpush
@endif
