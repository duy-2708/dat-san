@extends('layouts.app')
@section('title', 'Ngày lễ')

@section('content')
<div class="mx-auto" style="max-width: 640px">
    <div class="d-flex align-items-center gap-2 mb-2">
        <a href="{{ route('quan-ly.san.index') }}" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i></a>
        <h1 class="h4 mb-0">Ngày áp giá lễ</h1>
    </div>
    <p class="text-muted small">Những ngày này dùng bảng giá "Ngày lễ" của sân (chưa đặt thì dùng giá cuối tuần).</p>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Ngày riêng của bạn</div>
        <ul class="list-group list-group-flush">
            @forelse ($ngayRieng as $le)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>{{ \Carbon\Carbon::parse($le->ngay)->format('d/m/Y') }} — {{ $le->ten }}</span>
                    <form method="POST" action="{{ route('quan-ly.ngay-le.destroy', $le) }}">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </li>
            @empty
                <li class="list-group-item text-muted small">Chưa có.</li>
            @endforelse
        </ul>
        <form method="POST" action="{{ route('quan-ly.ngay-le.store') }}" class="card-body row g-2 border-top" novalidate>
            @csrf
            <div class="col-5"><input type="date" name="ngay" value="{{ old('ngay') }}" class="form-control @error('ngay') is-invalid @enderror">
                @error('ngay') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
            <div class="col-7 col-md-5"><input name="ten" value="{{ old('ten') }}" placeholder="VD: Khai trương" class="form-control @error('ten') is-invalid @enderror"></div>
            <div class="col-12 col-md-2"><button class="btn btn-success w-100">Thêm</button></div>
        </form>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Ngày lễ chung (hệ thống)</div>
        <ul class="list-group list-group-flush">
            @forelse ($ngayChung as $le)
                <li class="list-group-item">{{ \Carbon\Carbon::parse($le->ngay)->format('d/m/Y') }} — {{ $le->ten }}</li>
            @empty
                <li class="list-group-item text-muted small">Chưa có.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
