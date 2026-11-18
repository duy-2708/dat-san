@extends('layouts.app')
@section('title', 'Bổ sung số điện thoại')

@section('content')
<div class="the-form mx-auto">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h5 mb-2">Bổ sung số điện thoại</h1>
            <p class="text-muted small">Chủ sân cần số điện thoại để liên hệ khi bạn đặt sân. Mỗi số chỉ dùng cho 1 tài khoản.</p>
            <form method="POST" action="{{ route('ho-so.so-dien-thoai.store') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="so_dien_thoai">Số điện thoại</label>
                    <input id="so_dien_thoai" type="tel" inputmode="numeric" name="so_dien_thoai" value="{{ old('so_dien_thoai') }}" required autofocus
                           placeholder="0901234567" class="form-control @error('so_dien_thoai') is-invalid @enderror">
                    @error('so_dien_thoai') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn-success w-100">Lưu</button>
            </form>
        </div>
    </div>
</div>
@endsection
