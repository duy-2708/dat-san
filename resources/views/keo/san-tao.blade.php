@extends('layouts.app')
@section('title', 'Sân tạo kèo')

@section('content')
@php $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ'; @endphp
<div class="mx-auto" style="max-width: 820px">
    <h1 class="h4 mb-1">Tạo kèo giùm khách</h1>
    <p class="small text-muted">Chọn sân và ngày → bấm khung trống. Ở bước sau có thể chọn luôn đội nhờ làm kèo.</p>
    <form class="row g-2 mb-3">
        <div class="col-7"><select name="san" class="form-select" onchange="this.form.submit()">
            @foreach ($sans as $s) <option value="{{ $s->id }}" @selected($san?->id === $s->id)>{{ $s->ten }} (sân {{ $s->loai_san }})</option> @endforeach</select></div>
        <div class="col-5"><input type="date" name="ngay" value="{{ $ngay->toDateString() }}" min="{{ today()->toDateString() }}" class="form-control" onchange="this.form.submit()"></div>
    </form>
    <div class="row g-2">
        @forelse ($khungs as $o)
            @php $k = $o['khung']; @endphp
            <div class="col-6 col-md-3">
                @if ($o['trang_thai'] === 'trong')
                    <a href="{{ route('keo.tao-truoc', ['san' => $san->id, 'ngay' => $ngay->toDateString(), 'khung' => $k->id]) }}"
                       class="d-block border border-success rounded p-2 bg-white text-decoration-none text-body h-100">
                        <div class="fw-semibold">{{ $k->nhan() }}</div><div class="small text-muted">{{ $tien($k->gia) }}</div>
                        <div class="small text-danger">⚔ Tạo kèo</div></a>
                @else
                    <div class="border rounded p-2 bg-light text-muted h-100 small"><div class="fw-semibold">{{ $k->nhan() }}</div>{{ $o['trang_thai'] === 'qua_gio' ? 'Đã qua' : 'Đã đặt' }}</div>
                @endif
            </div>
        @empty
            <div class="col-12 text-muted small">Sân chưa có khung giờ cho ngày này.</div>
        @endforelse
    </div>
</div>
@endsection
