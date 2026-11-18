@extends('layouts.app')
@section('title', 'Tạo chủ sân')

@section('content')
<div class="the-form mx-auto">
    <h1 class="h4 mb-3">Tạo tài khoản chủ sân</h1>
    <form method="POST" action="{{ route('admin.chu-san.store') }}" class="card card-body border-0 shadow-sm" novalidate>
        @csrf
        <div class="mb-3">
            <label class="form-label" for="ten_co_so">Tên cơ sở / cụm sân</label>
            <input id="ten_co_so" name="ten_co_so" value="{{ old('ten_co_so') }}" required class="form-control @error('ten_co_so') is-invalid @enderror">
            @error('ten_co_so') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        @include('partials.truong-tai-khoan')
        <div class="row g-2 mb-3">
            <div class="col-7">
                <label class="form-label" for="goi_thue_bao_id">Gói</label>
                <select id="goi_thue_bao_id" name="goi_thue_bao_id" class="form-select">
                    <option value="">Dùng thử</option>
                    @foreach ($goiThueBaos as $g)
                        <option value="{{ $g->id }}" @selected(old('goi_thue_bao_id') == $g->id)>{{ $g->ten }} — {{ number_format($g->gia_thang, 0, ',', '.') }}đ/tháng</option>
                    @endforeach
                </select>
            </div>
            <div class="col-5">
                <label class="form-label" for="so_ngay_su_dung">Số ngày</label>
                <input id="so_ngay_su_dung" type="number" name="so_ngay_su_dung" value="{{ old('so_ngay_su_dung', 14) }}" min="1"
                       class="form-control @error('so_ngay_su_dung') is-invalid @enderror">
                @error('so_ngay_su_dung') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <button class="btn btn-success w-100">Tạo chủ sân</button>
    </form>
</div>
@endsection
