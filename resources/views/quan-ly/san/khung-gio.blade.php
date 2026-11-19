@extends('layouts.app')
@section('title', 'Khung giờ & giá — '.$san->ten)

@section('content')
@php
    $laChu = auth()->user()->can('update', $san);
    $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ';
    $gio = fn ($t) => substr($t, 0, 5);
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <a href="{{ route('quan-ly.san.index') }}" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i></a>
    <h1 class="h4 mb-0">Khung giờ & giá — {{ $san->ten }}</h1>
    @if ($laChu) <a href="{{ route('quan-ly.san.edit', $san) }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-pencil"></i> Thông tin sân</a> @endif
</div>
<p class="text-muted small">Mở cửa {{ $gio($san->gio_mo_cua) }}–{{ $gio($san->gio_dong_cua) }}. Khách đặt theo từng khung; cọc = 30% giá khung.
    Ngày lễ chưa đặt giá riêng thì dùng giá cuối tuần.</p>

@if ($errors->any())
    <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif

@if ($laChu)
{{-- TẠO NHANH --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white">
        <a class="text-decoration-none fw-semibold" data-bs-toggle="collapse" href="#taoNhanh"><i class="bi bi-magic"></i> Tạo nhanh cả ngày</a>
    </div>
    <div id="taoNhanh" class="collapse {{ $theoLoai->isEmpty() ? 'show' : '' }}">
        <form method="POST" action="{{ route('quan-ly.san.khung-gio.tao-nhanh', $san) }}" class="card-body row g-2">
            @csrf
            <div class="col-12">
                @foreach (\App\Models\KhungGio::LOAI_NGAY as $ma => $ten)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="loai_ngays[]" value="{{ $ma }}" id="tn_{{ $ma }}" @checked($ma !== 'ngay_le')>
                        <label class="form-check-label" for="tn_{{ $ma }}">{{ $ten }}</label>
                    </div>
                @endforeach
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Mỗi trận</label>
                <select name="thoi_luong" class="form-select">
                    <option value="60">60 phút</option><option value="90" selected>90 phút</option><option value="120">120 phút</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Giá thường (đ)</label>
                <input name="gia" type="number" min="0" step="1000" class="form-control" placeholder="300000" required>
            </div>
            <div class="col-4 col-md-2">
                <label class="form-label small">Giờ vàng từ</label>
                <select name="gio_vang_tu" class="form-select"><option value="">—</option>
                    @foreach ($cacMoc as $m) <option @selected($m === '17:00')>{{ $m }}</option> @endforeach</select>
            </div>
            <div class="col-4 col-md-2">
                <label class="form-label small">đến</label>
                <select name="gio_vang_den" class="form-select"><option value="">—</option>
                    @foreach ($cacMoc as $m) <option @selected($m === '21:00')>{{ $m }}</option> @endforeach</select>
            </div>
            <div class="col-4 col-md-2">
                <label class="form-label small">Giá vàng (đ)</label>
                <input name="gia_vang" type="number" min="0" step="1000" class="form-control" placeholder="450000">
            </div>
            <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="ghi_de" value="1" id="ghi_de">
                    <label class="form-check-label small" for="ghi_de">Xoá khung cũ của loại ngày đã chọn rồi tạo lại</label>
                </div>
                <button class="btn btn-success ms-auto">Tạo khung giờ</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- DANH SÁCH THEO LOẠI NGÀY --}}
<ul class="nav nav-pills mb-2 gap-1" role="tablist">
    @foreach (\App\Models\KhungGio::LOAI_NGAY as $ma => $ten)
        <li class="nav-item">
            <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#tab_{{ $ma }}" type="button">
                {{ $ten }} <span class="badge bg-light text-dark">{{ $theoLoai->get($ma)?->count() ?? 0 }}</span>
            </button>
        </li>
    @endforeach
</ul>

<div class="tab-content">
@foreach (\App\Models\KhungGio::LOAI_NGAY as $ma => $ten)
    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab_{{ $ma }}">
        <div class="bg-white rounded shadow-sm">
            @forelse ($theoLoai->get($ma, collect()) as $k)
                <div class="border-bottom p-2 {{ $k->dang_ap_dung ? '' : 'opacity-50' }}">
                    @if ($laChu)
                        <form method="POST" action="{{ route('quan-ly.khung-gio.update', $k) }}" class="row g-1 align-items-center">
                            @csrf @method('PUT')
                            <input type="hidden" name="loai_ngay" value="{{ $ma }}">
                            <div class="col-6 col-md-2"><select name="gio_bat_dau" class="form-select form-select-sm">
                                @foreach ($cacMoc as $m) <option @selected($m === $gio($k->gio_bat_dau))>{{ $m }}</option> @endforeach</select></div>
                            <div class="col-6 col-md-2"><select name="gio_ket_thuc" class="form-select form-select-sm">
                                @foreach ($cacMoc as $m) <option @selected($m === $gio($k->gio_ket_thuc))>{{ $m }}</option> @endforeach</select></div>
                            <div class="col-6 col-md-2"><input name="gia" type="number" min="0" step="1000" value="{{ $k->gia }}" class="form-control form-control-sm"></div>
                            <div class="col-6 col-md-3 d-flex gap-2 small">
                                <div class="form-check"><input type="hidden" name="la_gio_vang" value="0">
                                    <input class="form-check-input" type="checkbox" name="la_gio_vang" value="1" id="v{{ $k->id }}" @checked($k->la_gio_vang)>
                                    <label class="form-check-label" for="v{{ $k->id }}">Giờ vàng</label></div>
                                <div class="form-check"><input type="hidden" name="dang_ap_dung" value="0">
                                    <input class="form-check-input" type="checkbox" name="dang_ap_dung" value="1" id="a{{ $k->id }}" @checked($k->dang_ap_dung)>
                                    <label class="form-check-label" for="a{{ $k->id }}">Mở bán</label></div>
                            </div>
                            <div class="col-12 col-md-3 d-flex gap-1 justify-content-end">
                                <span class="small text-muted me-auto align-self-center">Cọc {{ $tien($k->tienCoc()) }}</span>
                                <button class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i></button>
                                <button class="btn btn-sm btn-outline-danger" form="xoa{{ $k->id }}" onclick="return confirm('Xoá khung {{ $k->nhan() }}?')"><i class="bi bi-trash"></i></button>
                            </div>
                        </form>
                        <form id="xoa{{ $k->id }}" method="POST" action="{{ route('quan-ly.khung-gio.destroy', $k) }}">@csrf @method('DELETE')</form>
                    @else
                        <div class="d-flex justify-content-between">
                            <span>{{ $k->nhan() }} @if ($k->la_gio_vang) <span class="badge bg-warning text-dark">Giờ vàng</span> @endif</span>
                            <span class="fw-semibold">{{ $tien($k->gia) }}</span>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-4 text-center text-muted">Chưa có khung giờ cho {{ mb_strtolower($ten) }}.</div>
            @endforelse

            @if ($laChu)
                <form method="POST" action="{{ route('quan-ly.san.khung-gio.store', $san) }}" class="row g-1 align-items-center p-2 bg-light rounded-bottom">
                    @csrf
                    <input type="hidden" name="loai_ngay" value="{{ $ma }}">
                    <div class="col-12 small fw-semibold">Thêm một khung</div>
                    <div class="col-6 col-md-2"><select name="gio_bat_dau" class="form-select form-select-sm">
                        @foreach ($cacMoc as $m) <option>{{ $m }}</option> @endforeach</select></div>
                    <div class="col-6 col-md-2"><select name="gio_ket_thuc" class="form-select form-select-sm">
                        @foreach ($cacMoc as $m) <option @selected($loop->index === 6)>{{ $m }}</option> @endforeach</select></div>
                    <div class="col-6 col-md-2"><input name="gia" type="number" min="0" step="1000" placeholder="Giá" class="form-control form-control-sm" required></div>
                    <div class="col-6 col-md-3"><div class="form-check small">
                        <input class="form-check-input" type="checkbox" name="la_gio_vang" value="1" id="nv_{{ $ma }}">
                        <label class="form-check-label" for="nv_{{ $ma }}">Giờ vàng</label></div></div>
                    <div class="col-12 col-md-3 text-end"><button class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Thêm</button></div>
                </form>
            @endif
        </div>
    </div>
@endforeach
</div>
@endsection
