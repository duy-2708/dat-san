@extends('layouts.app')
@section('title', 'Quản lý sân')

@section('content')
@php($u = auth()->user())
@php($hoSo = $u->laChuSan() ? $u->hoSoChuSan : $u->chuSan?->hoSoChuSan)
<h1 class="h4 mb-3">{{ $hoSo?->ten_co_so ?? 'Quản lý sân' }}</h1>

@error('thue_bao') <div class="alert alert-danger">{{ $message }}</div> @enderror
@if ($hoSo)
    @php($ngay = $hoSo->soNgayConLai())
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
