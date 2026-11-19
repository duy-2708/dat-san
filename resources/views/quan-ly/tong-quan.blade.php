@extends('layouts.app')
@section('title', 'Quản lý sân')

@section('content')
@php($u = auth()->user())
@php($hoSo = $u->laChuSan() ? $u->hoSoChuSan : $u->chuSan?->hoSoChuSan)
<h1 class="h4 mb-3">{{ $hoSo?->ten_co_so ?? 'Quản lý sân' }}</h1>

@if ($hoSo)
    <div class="alert {{ $hoSo->conHan() ? 'alert-light border' : 'alert-danger' }}">
        Gói: <strong>{{ $hoSo->goiThueBao?->ten ?? 'Dùng thử' }}</strong> —
        @if ($hoSo->conHan())
            còn hạn tới {{ $hoSo->het_han_luc->format('d/m/Y') }}
        @else
            ĐÃ HẾT HẠN. Vui lòng liên hệ quản trị viên để gia hạn.
        @endif
    </div>
@endif

<div class="row g-3">
    @foreach ([
        ['bi-grid-3x3-gap', 'Sân & giá', route('quan-ly.san.index'), 'Sân, ảnh, khung giờ'],
        ['bi-calendar-week', 'Đơn đặt sân', route('quan-ly.dat-san.index'), 'Xác nhận cọc, lịch hôm nay'],
        ['bi-qr-code', 'Cài đặt thanh toán', auth()->user()->laChuSan() ? route('quan-ly.thanh-toan.edit') : null, 'Tài khoản nhận cọc'],
        ['bi-people', 'Kèo', null, 'Giai đoạn 6'],
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
