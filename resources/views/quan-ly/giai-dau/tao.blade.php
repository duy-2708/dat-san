@extends('layouts.app')
@section('title', 'Tạo giải')

@section('content')
<div class="mx-auto" style="max-width: 560px">
    <h1 class="h4 mb-3">Tạo giải đấu</h1>
    <form method="POST" action="{{ route('quan-ly.giai-dau.store') }}" class="card card-body border-0 shadow-sm" novalidate>
        @csrf
        <div class="mb-3"><label class="form-label">Tên giải</label>
            <input name="ten" value="{{ old('ten') }}" class="form-control @error('ten') is-invalid @enderror" placeholder="VD: Cúp Phú Nhuận mùa đông 2026">
            @error('ten') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
        <div class="row g-2 mb-3">
            <div class="col-4"><label class="form-label small">Loại sân</label>
                <select name="loai_san" class="form-select">@foreach ([5, 7, 11] as $l) <option value="{{ $l }}" @selected((int) old('loai_san', 7) === $l)>Sân {{ $l }}</option> @endforeach</select></div>
            <div class="col-8"><label class="form-label small">Thể thức</label>
                <select name="the_thuc" class="form-select">
                    <option value="vong_tron" @selected(old('the_thuc') !== 'loai_truc_tiep')>Vòng tròn tính điểm (tự xếp lịch)</option>
                    <option value="loai_truc_tiep" @selected(old('the_thuc') === 'loai_truc_tiep')>Loại trực tiếp (tự thêm cặp đấu)</option>
                </select></div>
            <div class="col-6"><label class="form-label small">Từ ngày</label>
                <input type="date" name="tu_ngay" value="{{ old('tu_ngay') }}" class="form-control @error('tu_ngay') is-invalid @enderror"></div>
            <div class="col-6"><label class="form-label small">Đến ngày</label>
                <input type="date" name="den_ngay" value="{{ old('den_ngay') }}" class="form-control @error('den_ngay') is-invalid @enderror"></div>
            <div class="col-6"><label class="form-label small">Số đội tối đa</label>
                <input type="number" name="so_doi_toi_da" min="2" max="64" value="{{ old('so_doi_toi_da', 8) }}" class="form-control"></div>
            <div class="col-6"><label class="form-label small">Lệ phí/đội (đ)</label>
                <input type="number" name="le_phi" min="0" step="10000" value="{{ old('le_phi', 0) }}" class="form-control"></div>
        </div>
        @if ($errors->any()) <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div> @endif
        <label class="form-label">Điều lệ / giới thiệu</label>
        <textarea name="mo_ta" rows="4" class="form-control mb-3">{{ old('mo_ta') }}</textarea>
        <button class="btn btn-success w-100">Tạo giải và mở đăng ký</button>
    </form>
</div>
@endsection
