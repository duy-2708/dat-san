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
    @foreach ([['bi-grid-3x3-gap', 'Sân & giá', 'Giai đoạn 2'], ['bi-calendar-week', 'Lịch đặt sân', 'Giai đoạn 3'], ['bi-qr-code', 'Cài đặt thanh toán', 'Giai đoạn 3'], ['bi-people', 'Kèo', 'Giai đoạn 6']] as [$icon, $ten, $gd])
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body">
                    <i class="bi {{ $icon }} fs-2 text-success"></i>
                    <div class="fw-semibold mt-1">{{ $ten }}</div>
                    <div class="small text-muted">{{ $gd }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
