@extends('layouts.app')
@section('title', $u->name)

@section('content')
@php $huyHieu = fn ($h) => match (true) { $h === 1 => '🥇', $h === 2 => '🥈', $h === 3 => '🥉', default => '🏅' }; @endphp
<div class="mx-auto" style="max-width: 720px">
    <div class="card border-0 shadow-sm mb-3 overflow-hidden">
        <div class="bg-success" style="height: 70px"></div>
        <div class="card-body text-center" style="margin-top: -60px">
            <img src="{{ $u->anhDaiDienUrl() }}" width="110" height="110" class="rounded-circle object-fit-cover border border-4 border-white shadow-sm" alt="{{ $u->name }}">
            <h1 class="h4 mt-2 mb-0">{{ $u->name }}</h1>
            <div class="text-muted">{{ \App\Http\Controllers\CauThuController::VI_TRI[$u->vi_tri_so_truong] ?? 'Chưa chọn vị trí' }}</div>
            <div class="d-flex justify-content-center gap-4 mt-3">
                <div><div class="fs-4 fw-bold">{{ $u->so_tran_tham_gia }}</div><div class="small text-muted">Trận đã đá</div></div>
                <div><div class="fs-4 fw-bold {{ $u->diem_uy_tin >= 80 ? 'text-success' : ($u->diem_uy_tin >= 50 ? 'text-warning' : 'text-danger') }}">{{ $u->diem_uy_tin }}</div><div class="small text-muted">Uy tín</div></div>
                <div><div class="fs-4 fw-bold">{{ $thanhTich->count() }}</div><div class="small text-muted">Giải đã dự</div></div>
            </div>
            <div class="small text-muted mt-1">{{ $u->uyTinNhan() }}</div>
            @if (auth()->id() === $u->id)
                <a href="{{ route('ho-so.edit') }}" class="btn btn-sm btn-outline-success mt-2">Sửa hồ sơ</a>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h2 class="h6">Đội đang chơi</h2>
        <div class="d-flex flex-wrap gap-2">
            @forelse ($u->doiDangChoi as $doi)
                <a href="{{ route('doi.show', $doi) }}" class="d-flex align-items-center gap-2 border rounded-pill pe-3 text-decoration-none text-body">
                    <img src="{{ $doi->logoUrl() }}" width="36" height="36" class="rounded-circle object-fit-cover" alt="">
                    <span class="small fw-semibold">{{ $doi->ten }}</span>
                    @if ($doi->pivot->vai_tro === 'doi_truong') <span class="badge bg-warning text-dark">C</span> @endif
                </a>
            @empty
                <span class="small text-muted">Chưa vào đội nào.</span>
            @endforelse
        </div>
    </div></div>

    <div class="card border-0 shadow-sm"><div class="card-body">
        <h2 class="h6"><i class="bi bi-trophy text-warning"></i> Thành tích</h2>
        @forelse ($thanhTich as $tt)
            <div class="d-flex align-items-center gap-2 border-bottom py-2 small">
                <span class="fs-5">{{ $huyHieu($tt->thu_hang) }}</span>
                <div>
                    <a href="{{ route('giai-dau.show', $tt->giaiDau) }}" class="fw-semibold">{{ $tt->giaiDau->ten }}</a>
                    <div class="text-muted">cùng {{ $tt->doi->ten }} · {{ $tt->thu_hang ? 'Hạng '.$tt->thu_hang : 'Tham dự' }}{{ $tt->danh_hieu ? ' · '.$tt->danh_hieu : '' }}</div>
                </div>
            </div>
        @empty
            <div class="small text-muted">Chưa có thành tích giải đấu.</div>
        @endforelse
    </div></div>
</div>
@endsection
