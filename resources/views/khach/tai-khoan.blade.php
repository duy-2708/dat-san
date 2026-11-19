@extends('layouts.app')
@section('title', 'Tài khoản của tôi')

@section('content')
@php
    $u = auth()->user();
    $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ';
    $mauTt = ['cho_thanh_toan' => 'warning', 'da_dat' => 'success', 'da_huy' => 'secondary'];
@endphp
<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <img src="{{ $u->anhDaiDienUrl() }}"
                     class="rounded-circle mb-2" width="96" height="96" alt="Ảnh đại diện" style="object-fit:cover">
                <h1 class="h5 mb-0">{{ $u->name }}</h1>
                <div class="text-muted small">{{ $u->email }} · {{ $u->so_dien_thoai }}</div>
                <hr>
                <div class="d-flex justify-content-around">
                    <div><div class="fw-bold fs-5">{{ $u->so_tran_tham_gia }}</div><div class="small text-muted">Trận</div></div>
                    <div><div class="fw-bold fs-5">{{ $u->diem_uy_tin }}</div><div class="small text-muted">Uy tín</div></div>
                </div>
                <div class="small text-muted mt-1">{{ $u->uyTinNhan() }}</div>
                <div class="d-flex gap-1 justify-content-center mt-2">
                    <a href="{{ route('cau-thu.show', $u) }}" class="btn btn-sm btn-outline-success">Hồ sơ cầu thủ</a>
                    <a href="{{ route('khach.doi.create') }}" class="btn btn-sm btn-outline-primary">Tạo đội</a>
                </div>
                @foreach ($u->doiDangChoi as $doi)
                    <a href="{{ route('doi.show', $doi) }}" class="d-inline-flex align-items-center gap-1 small mt-2 me-2"><img src="{{ $doi->logoUrl() }}" width="22" height="22" class="rounded-circle" alt="">{{ $doi->ten }}</a>
                @endforeach
                @if ($u->biHanChe()) <div class="alert alert-danger small py-1 mt-2 mb-0">Bị hạn chế: {{ $u->ly_do_han_che }}</div> @endif
                @if ($u->khoa_keo_den?->isFuture()) <div class="alert alert-warning small py-1 mt-2 mb-0">Khoá vào kèo tới {{ $u->khoa_keo_den->format('d/m/Y') }}</div> @endif
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <h2 class="h6">Lịch sắp tới</h2>
        @forelse ($sapToi as $d)
            <div class="card border-0 shadow-sm mb-2">
                <div class="card-body py-2 d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <div class="fw-semibold">{{ \Carbon\Carbon::parse($d->ngay)->format('d/m') }} · {{ $d->khungGioNhan() }} — {{ $d->san->ten }}</div>
                        <div class="small text-muted">{{ $d->ma_don }} · {{ $d->san->diaChiDayDu() }}</div>
                        <div class="small">
                            <span class="badge bg-{{ $mauTt[$d->trang_thai] }}">{{ \App\Models\DatSan::TRANG_THAI[$d->trang_thai] }}</span>
                            Cọc {{ $tien($d->tien_coc) }} · còn {{ $tien($d->gia_chot - ($d->xac_nhan_coc_luc ? $d->tien_coc : 0)) }}
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <a href="{{ route('dat-san.thanh-toan', $d) }}" class="btn btn-sm {{ $d->trang_thai === 'cho_thanh_toan' ? 'btn-warning' : 'btn-outline-secondary' }} text-nowrap">
                            {{ $d->trang_thai === 'cho_thanh_toan' ? 'Quét QR cọc' : 'Chi tiết' }}
                        </a>
                        @if ($d->trang_thai === 'da_dat' && $d->batDauLuc()->isFuture())
                            @php $keoCua = \App\Models\Keo::where('dat_san_id', $d->id)->first(); @endphp
                            @if ($keoCua)
                                <a href="{{ route('keo.show', $keoCua) }}" class="btn btn-sm btn-outline-danger text-nowrap">Xem kèo</a>
                            @else
                                <a href="{{ route('khach.keo.create', ['don' => $d->ma_don]) }}" class="btn btn-sm btn-outline-danger text-nowrap">Tạo kèo</a>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-muted small bg-white rounded p-3 mb-2">Chưa có lịch nào. Tìm sân và đặt ngay!</div>
        @endforelse

        @if ($daQua->isNotEmpty())
            <h2 class="h6 mt-3">Đã qua / đã huỷ</h2>
            <div class="list-group shadow-sm">
                @foreach ($daQua as $d)
                    <a href="{{ route('dat-san.thanh-toan', $d) }}" class="list-group-item list-group-item-action small d-flex justify-content-between">
                        <span>{{ \Carbon\Carbon::parse($d->ngay)->format('d/m/Y') }} · {{ $d->khungGioNhan() }} — {{ $d->san->ten }}</span>
                        <span>
                            <span class="badge bg-{{ $mauTt[$d->trang_thai] }}">{{ \App\Models\DatSan::TRANG_THAI[$d->trang_thai] }}</span>
                            @if (\App\Models\DatSan::XU_LY_COC[$d->xu_ly_coc]) <span class="badge bg-light text-dark border">{{ \App\Models\DatSan::XU_LY_COC[$d->xu_ly_coc] }}</span> @endif
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
