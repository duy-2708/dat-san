@extends('layouts.app')
@section('title', 'Đơn đặt sân')

@section('content')
@php
    $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ';
    $laChu = auth()->user()->laChuSan();
@endphp
<h1 class="h4 mb-3">Đơn đặt sân</h1>
@if ($errors->any()) <div class="alert alert-danger py-2">{{ $errors->first() }}</div> @endif

{{-- CHỜ CỌC --}}
<h2 class="h6"><i class="bi bi-hourglass-split text-warning"></i> Chờ xác nhận cọc <span class="badge bg-warning text-dark">{{ $choCoc->count() }}</span></h2>
<p class="small text-muted">Mở app ngân hàng, thấy tiền vào với đúng <strong>nội dung (mã đơn)</strong> và <strong>số tiền</strong> thì bấm "Đã nhận cọc".</p>
@forelse ($choCoc as $d)
    <div class="card border-0 shadow-sm mb-2 border-start border-warning border-4">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <div class="fw-semibold">{{ \Carbon\Carbon::parse($d->ngay)->format('d/m') }} · {{ $d->khungGioNhan() }} — {{ $d->san->ten }}</div>
                    <div class="small">{{ $d->tenNguoiDat() }} · <a href="tel:{{ $d->sdtNguoiDat() }}">{{ $d->sdtNguoiDat() }}</a></div>
                    <div class="small">Nội dung CK: <strong class="font-monospace">{{ $d->ma_don }}</strong> · Cọc <strong>{{ $tien($d->tien_coc) }}</strong>
                        · giữ tới {{ $d->giu_cho_den?->format('H:i') }}</div>
                </div>
                <div class="d-flex flex-wrap gap-1 align-items-center">
                    <form method="POST" action="{{ route('quan-ly.dat-san.xac-nhan-coc', $d) }}">@csrf @method('PATCH')
                        <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Đã nhận cọc</button></form>
                    <form method="POST" action="{{ route('quan-ly.dat-san.gia-han', $d) }}">@csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-secondary">+15 phút</button></form>
                    @if ($laChu)
                        <form method="POST" action="{{ route('quan-ly.dat-san.huy', $d) }}" onsubmit="return confirm('Huỷ giữ chỗ {{ $d->ma_don }}?')">@csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-danger">Huỷ</button></form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="text-muted small bg-white rounded p-3 mb-3">Không có đơn chờ cọc.</div>
@endforelse

{{-- CỌC KÈO --}}
@if ($cocKeo->isNotEmpty())
    <h2 class="h6 mt-4"><i class="bi bi-people text-warning"></i> Cọc kèo chờ xác nhận</h2>
    @foreach ($cocKeo as $g)
        <div class="card border-0 shadow-sm mb-2 border-start border-warning border-4"><div class="card-body py-2 d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div class="small">Nội dung CK <strong class="font-monospace">{{ $g->noi_dung_ck }}</strong> · <strong>{{ $tien($g->so_tien) }}</strong> · {{ $g->nguoiTra?->name }} ({{ $g->nguoiTra?->so_dien_thoai }})</div>
            <form method="POST" action="{{ route('quan-ly.coc-keo.xac-nhan', $g) }}">@csrf @method('PATCH')
                <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Đã nhận cọc</button></form>
        </div></div>
    @endforeach
@endif

{{-- CỌC KÈO CẦN HOÀN --}}
@if ($laChu && $cocKeoHoan->isNotEmpty())
    <h2 class="h6 mt-4"><i class="bi bi-arrow-counterclockwise text-danger"></i> Cọc kèo cần hoàn</h2>
    @foreach ($cocKeoHoan as $g)
        <div class="card border-0 shadow-sm mb-2 border-start border-danger border-4"><div class="card-body py-2 d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div class="small">Hoàn <strong>{{ $tien($g->so_tien) }}</strong> cho {{ $g->nguoiTra?->name }} ({{ $g->nguoiTra?->so_dien_thoai }}) · khoản {{ $g->noi_dung_ck }}</div>
            <form method="POST" action="{{ route('quan-ly.coc-keo.da-hoan', $g) }}">@csrf @method('PATCH')
                <button class="btn btn-sm btn-outline-success">Đã hoàn</button></form>
        </div></div>
    @endforeach
@endif

{{-- KÈO CẦN XỬ LÝ --}}
@if ($keoXuLy->isNotEmpty())
    <h2 class="h6 mt-4"><i class="bi bi-flag text-danger"></i> Kèo cần điểm danh / chốt kết quả</h2>
    <div class="list-group shadow-sm mb-3">
        @foreach ($keoXuLy as $k)
            <a href="{{ route('keo.show', $k) }}" class="list-group-item list-group-item-action small d-flex justify-content-between">
                <span>Kèo #{{ $k->id }} · {{ \Carbon\Carbon::parse($k->datSan->ngay)->format('d/m') }} {{ $k->datSan->khungGioNhan() }} — {{ $k->datSan->san->ten }}</span>
                <span class="badge bg-{{ $k->trang_thai === 'tranh_chap' ? 'danger' : 'warning text-dark' }}">{{ \App\Models\Keo::TRANG_THAI[$k->trang_thai] }}</span>
            </a>
        @endforeach
    </div>
@endif

{{-- CHỜ HOÀN CỌC --}}
@if ($laChu && $choHoan->isNotEmpty())
    <h2 class="h6 mt-4"><i class="bi bi-arrow-counterclockwise text-danger"></i> Cần hoàn cọc cho khách</h2>
    @foreach ($choHoan as $d)
        <div class="card border-0 shadow-sm mb-2 border-start border-danger border-4">
            <div class="card-body py-2 d-flex flex-wrap justify-content-between gap-2 align-items-center">
                <div class="small">
                    <strong>{{ $d->ma_don }}</strong> · {{ \Carbon\Carbon::parse($d->ngay)->format('d/m') }} {{ $d->khungGioNhan() }} — {{ $d->san->ten }}<br>
                    Hoàn <strong>{{ $tien($d->tien_coc) }}</strong> cho {{ $d->tenNguoiDat() }} ({{ $d->sdtNguoiDat() }}) · Lý do: {{ $d->ly_do_huy }}
                </div>
                <form method="POST" action="{{ route('quan-ly.dat-san.da-hoan', $d) }}">@csrf @method('PATCH')
                    <button class="btn btn-sm btn-outline-success">Đã hoàn</button></form>
            </div>
        </div>
    @endforeach
@endif

{{-- TRONG NGÀY --}}
<div class="d-flex flex-wrap align-items-center gap-2 mt-4 mb-2">
    <h2 class="h6 mb-0">Lịch đã đặt ngày</h2>
    <form method="GET" class="d-flex gap-1">
        <input type="date" name="ngay" value="{{ $ngay->toDateString() }}" class="form-control form-control-sm" onchange="this.form.submit()">
    </form>
</div>
<div class="list-group shadow-sm">
    @forelse ($trongNgay as $d)
        <div class="list-group-item d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div class="small">
                <strong>{{ $d->khungGioNhan() }}</strong> — {{ $d->san->ten }} ·
                {{ $d->tenNguoiDat() }} (<a href="tel:{{ $d->sdtNguoiDat() }}">{{ $d->sdtNguoiDat() }}</a>)
                <span class="badge bg-light text-dark border">{{ $d->nguon === 'tai_cho' ? 'Tại chỗ' : 'Online' }}</span><br>
                Giá {{ $tien($d->gia_chot) }} · đã cọc {{ $tien($d->xac_nhan_coc_luc ? $d->tien_coc : 0) }} · <strong>thu thêm {{ $tien($d->conLaiPhai()) }}</strong>
            </div>
            @if ($laChu)
                <form method="POST" action="{{ route('quan-ly.dat-san.huy', $d) }}" onsubmit="return confirm('Huỷ đơn {{ $d->ma_don }}? Nếu khách đã cọc, bạn cần hoàn cọc.')">@csrf @method('PATCH')
                    <button class="btn btn-sm btn-outline-danger">Huỷ</button></form>
            @endif
        </div>
    @empty
        <div class="list-group-item text-muted small">Chưa có lịch.</div>
    @endforelse
</div>
@endsection
