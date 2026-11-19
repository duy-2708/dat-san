@extends('layouts.app')
@section('title', 'Đội bóng')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Đội bóng</h1>
    @auth @if (auth()->user()->laKhach()) <a href="{{ route('khach.doi.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tạo đội</a> @endif @endauth
</div>
<form class="row g-2 mb-3">
    <div class="col-12 col-md-6"><input name="q" value="{{ $tuKhoa }}" class="form-control" placeholder="Tìm tên đội..."></div>
    <div class="col-8 col-md-4"><select name="tinh" class="form-select"><option value="">Mọi tỉnh/thành</option>
        @foreach ($tinhThanhs as $t) <option value="{{ $t->id }}" @selected($tinh === $t->id)>{{ $t->ten }}</option> @endforeach</select></div>
    <div class="col-4 col-md-2"><button class="btn btn-outline-success w-100">Tìm</button></div>
</form>
<p class="small text-muted">Bấm vào đội → <strong>Xin vào đội</strong> → chờ đội trưởng duyệt. Hoặc nhờ đội trưởng gửi link mời / thêm bạn bằng SĐT.</p>
<div class="row g-2">
    @forelse ($dois as $d)
        <div class="col-12 col-md-6 col-lg-4">
            <a href="{{ route('doi.show', $d) }}" class="d-flex align-items-center gap-2 bg-white border rounded p-2 text-decoration-none text-body h-100">
                <img src="{{ $d->logoUrl() }}" width="48" height="48" class="rounded-circle object-fit-cover" alt="">
                <div class="small">
                    <div class="fw-semibold">{{ $d->ten }}</div>
                    <div class="text-muted">{{ $d->tinhThanh?->ten ?? 'Chưa chọn tỉnh' }} · {{ $d->so_thanh_vien }} thành viên · ĐT {{ $d->doiTruong->name }}</div>
                </div>
            </a>
        </div>
    @empty
        <div class="col-12 text-center text-muted py-5 bg-white rounded">Không tìm thấy đội nào.</div>
    @endforelse
</div>
<div class="mt-3">{{ $dois->links() }}</div>
@endsection
