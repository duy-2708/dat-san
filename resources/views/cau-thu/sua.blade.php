@extends('layouts.app')
@section('title', 'Sửa hồ sơ')

@section('content')
<div class="mx-auto" style="max-width: 480px">
    <h1 class="h4 mb-3">Hồ sơ cầu thủ</h1>
    <form method="POST" action="{{ route('ho-so.update') }}" enctype="multipart/form-data" class="card card-body border-0 shadow-sm" novalidate>
        @csrf @method('PUT')
        <div class="text-center mb-3"><img src="{{ $u->anhDaiDienUrl() }}" width="96" height="96" class="rounded-circle object-fit-cover" alt=""></div>
        <div class="mb-3">
            <label class="form-label" for="anh">Ảnh đại diện</label>
            <input id="anh" type="file" name="anh" accept="image/*" class="form-control @error('anh') is-invalid @enderror">
            @error('anh') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="name">Tên hiển thị</label>
            <input id="name" name="name" value="{{ old('name', $u->name) }}" class="form-control @error('name') is-invalid @enderror">
        </div>
        <div class="mb-3">
            <label class="form-label">Vị trí sở trường</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach (\App\Http\Controllers\CauThuController::VI_TRI as $ma => $ten)
                    <input type="radio" class="btn-check" name="vi_tri_so_truong" value="{{ $ma }}" id="vt{{ $ma }}" @checked(old('vi_tri_so_truong', $u->vi_tri_so_truong) === $ma)>
                    <label class="btn btn-outline-success btn-sm" for="vt{{ $ma }}">{{ $ten }}</label>
                @endforeach
            </div>
        </div>
        <button class="btn btn-success w-100">Lưu</button>
    </form>
</div>
@endsection
