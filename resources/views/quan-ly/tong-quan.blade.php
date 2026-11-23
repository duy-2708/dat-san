@extends('layouts.app')
@section('title', 'Quản lý sân')

@section('content')
@php $u = auth()->user(); @endphp
@php $hoSo = $u->laChuSan() ? $u->hoSoChuSan : $u->chuSan?->hoSoChuSan; @endphp
<h1 class="h4 mb-3">{{ $hoSo?->ten_co_so ?? 'Quản lý sân' }}</h1>

@error('thue_bao') <div class="alert alert-danger">{{ $message }}</div> @enderror
@if ($hoSo)
    @php $ngay = $hoSo->soNgayConLai(); @endphp
    <div class="alert {{ ! $hoSo->conHan() ? 'alert-danger' : ($ngay !== null && $ngay <= 7 && ! \App\Models\CaiDat::dangMienPhi() ? 'alert-warning' : 'alert-light border') }}">
        Gói: <strong>{{ $hoSo->goiThueBao?->ten ?? 'Chưa có' }}</strong> —
        @if (\App\Models\CaiDat::dangMienPhi())
            đang trong thời gian <strong>miễn phí</strong> tới {{ \App\Models\CaiDat::mienPhiDen()->format('d/m/Y') }}.
        @elseif ($hoSo->conHan())
            còn hạn tới {{ $hoSo->het_han_luc->format('d/m/Y') }} ({{ $ngay }} ngày).
        @else
            ĐÃ HẾT HẠN: sân đang bị ẩn và không nhận đặt.
        @endif
        @if ($u->laChuSan()) <a href="{{ route('quan-ly.goi.index') }}" class="alert-link ms-1">Gia hạn / đổi gói</a> @else Báo chủ sân gia hạn. @endif
    </div>
@endif

@if ($tk)
    @php $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ'; @endphp
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h6 mb-0"><i class="bi bi-graph-up-arrow text-success"></i> Doanh thu tháng {{ $thang->format('m/Y') }}</h2>
            <div class="btn-group btn-group-sm">
                <a href="?thang={{ $thang->copy()->subMonth()->format('Y-m') }}" class="btn btn-secondary"><i class="bi bi-chevron-left"></i></a>
                <a href="?thang={{ now()->format('Y-m') }}" class="btn btn-secondary">Tháng này</a>
                <a href="?thang={{ $thang->copy()->addMonth()->format('Y-m') }}" class="btn btn-secondary"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
        <div class="row g-2 text-center">
            @foreach ([
                ['Cọc online', $tk['coc_online'], 'qr-code', 'success', 'Khách chuyển qua mã QR / PayOS'],
                ['Cọc nhận ngoài', $tk['coc_ngoai'], 'cash', 'success', 'Tiền mặt / chuyển riêng'],
                ['Tiền sân tại sân', $tk['tien_san'], 'cash-coin', 'success', 'Bấm "Đã nhận" ở Đơn đặt'],
                ['Kèo (thu − trả lại)', $tk['keo'], 'people', $tk['keo'] < 0 ? 'danger' : 'success', 'Trả thêm tại sân của kèo'],
                ['Đã hoàn cọc', -$tk['hoan'], 'arrow-counterclockwise', 'danger', 'Trả lại khách'],
            ] as [$nhan, $so, $icon, $mau, $ghiChu])
                <div class="col-6 col-md">
                    <div class="border rounded p-2 h-100">
                        <div class="small text-muted"><i class="bi bi-{{ $icon }}"></i> {{ $nhan }}</div>
                        <div class="fs-5 fw-bold text-{{ $so < 0 ? 'danger' : 'body' }}">{{ $so < 0 ? '−'.$tien(-$so) : $tien($so) }}</div>
                        <div class="text-muted" style="font-size:.7rem">{{ $ghiChu }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 p-2 rounded bg-success-subtle">
            <span>Tổng thực thu · {{ $tk['so_don'] }} lượt đặt trong tháng</span>
            <strong class="fs-4 text-success">{{ $tien($tk['tong']) }}</strong>
        </div>
        <div class="small mt-2 d-flex flex-wrap gap-3">
            <span><i class="bi bi-cash"></i> Tiền mặt: <strong>{{ $tien($tk['tien_mat']) }}</strong></span>
            <span><i class="bi bi-qr-code"></i> Online: <strong>{{ $tien($tk['online']) }}</strong></span>
            @if ($tk['tien_san_chua_ro'] > 0)
                <span class="text-warning-emphasis"><i class="bi bi-question-circle"></i> Tiền sân chưa ghi hình thức: {{ $tien($tk['tien_san_chua_ro']) }}</span>
            @endif
        </div>
        @if ($tk['chua_thu']->isNotEmpty())
            <div class="alert alert-warning small py-2 mt-2 mb-0">
                <i class="bi bi-exclamation-triangle"></i> {{ $tk['chua_thu']->count() }} lượt đã đá nhưng chưa bấm "Đã nhận tiền sân"
                ({{ $tien($tk['chua_thu']->sum(fn ($d) => $d->conLaiPhai())) }} chưa vào thống kê).
                <a href="{{ route('quan-ly.dat-san.index') }}" class="alert-link">Vào Đơn đặt để xác nhận</a>
            </div>
        @endif
    </div></div>
@endif

<div class="row g-3">
    @foreach ([
        ['bi-grid-3x3-gap', 'Sân & giá', route('quan-ly.san.index'), 'Sân, ảnh, khung giờ'],
        ['bi-calendar-week', 'Đơn đặt sân', route('quan-ly.dat-san.index'), 'Xác nhận cọc, lịch hôm nay'],
        ['bi-qr-code', 'Cài đặt thanh toán', auth()->user()->laChuSan() ? route('quan-ly.thanh-toan.edit') : null, 'Tài khoản nhận cọc'],
        ['bi-box-seam', 'Gói dịch vụ', auth()->user()->laChuSan() ? route('quan-ly.goi.index') : null, 'Gia hạn, đổi gói'],
    ] as [$icon, $ten, $link, $gd])
        <div class="col-6 col-md-3">
            <a @if ($link) href="{{ $link }}" @endif class="card border-0 shadow-sm h-100 text-center text-decoration-none text-body {{ $link ? '' : 'opacity-50' }}">
                <div class="card-body">
                    <i class="bi {{ $icon }} fs-2 text-success"></i>
                    <div class="fw-semibold mt-1">{{ $ten }}</div>
                    <div class="small text-muted">{{ $gd }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection
