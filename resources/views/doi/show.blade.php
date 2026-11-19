@extends('layouts.app')
@section('title', $doi->ten)

@section('content')
@php
    $u = auth()->user();
    $laDT = $doi->laDoiTruong($u);
    $dangChoi = $doi->thanhViens->where('trang_thai', 'dang_choi');
    $choDuyet = $doi->thanhViens->where('trang_thai', 'cho_duyet');
    $toi = $u ? $doi->thanhViens->firstWhere('user_id', $u->id) : null;
    $huyHieu = fn ($h) => match (true) { $h === 1 => '🥇', $h === 2 => '🥈', $h === 3 => '🥉', default => '' };
@endphp
<div class="mx-auto" style="max-width: 820px">
    @error('doi') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

    <div class="card border-0 shadow-sm mb-3"><div class="card-body d-flex gap-3 align-items-center">
        <img src="{{ $doi->logoUrl() }}" width="88" height="88" class="rounded-circle object-fit-cover border" alt="Logo {{ $doi->ten }}">
        <div class="flex-grow-1">
            <h1 class="h4 mb-1">{{ $doi->ten }}</h1>
            <div class="small text-muted">{{ $doi->tinhThanh?->ten }} · {{ $dangChoi->count() }} thành viên · Đội trưởng
                <a href="{{ route('cau-thu.show', $doi->doiTruong) }}">{{ $doi->doiTruong->name }}</a></div>
            @if ($doi->mo_ta) <p class="small mb-0 mt-1">{{ $doi->mo_ta }}</p> @endif
        </div>
        @auth
            @if (! $toi || $toi->trang_thai === 'da_roi')
                @if ($u->laKhach())
                    <form method="POST" action="{{ route('khach.doi.xin-vao', $doi) }}">@csrf<button class="btn btn-success">Xin vào đội</button></form>
                @endif
            @elseif ($toi->trang_thai === 'cho_duyet')
                <span class="badge bg-warning text-dark">Đang chờ duyệt</span>
            @endif
        @endauth
    </div></div>

    {{-- Thành tích --}}
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h2 class="h6"><i class="bi bi-trophy text-warning"></i> Thành tích</h2>
        @forelse ($thanhTich as $tt)
            <div class="small border-bottom py-1">{{ $huyHieu($tt->thu_hang) }}
                <a href="{{ route('giai-dau.show', $tt->giaiDau) }}">{{ $tt->giaiDau->ten }}</a>
                — {{ $tt->thu_hang ? 'Hạng '.$tt->thu_hang : 'Tham dự' }}{{ $tt->danh_hieu ? ' · '.$tt->danh_hieu : '' }}</div>
        @empty
            <div class="small text-muted">Chưa có giải đấu nào kết thúc.</div>
        @endforelse
    </div></div>

    {{-- Thành viên --}}
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h2 class="h6">Thành viên</h2>
        <div class="row g-2">
            @foreach ($dangChoi as $tv)
                <div class="col-12 col-md-6">
                    <div class="d-flex align-items-center gap-2 border rounded p-2">
                        <img src="{{ $tv->user->anhDaiDienUrl() }}" width="40" height="40" class="rounded-circle object-fit-cover" alt="">
                        <div class="flex-grow-1 small">
                            <a href="{{ route('cau-thu.show', $tv->user) }}" class="fw-semibold">{{ $tv->user->name }}</a>
                            @if ($tv->vai_tro === 'doi_truong') <span class="badge bg-warning text-dark">C</span> @endif
                            @if ($tv->so_ao !== null) <span class="badge bg-light text-dark border">#{{ $tv->so_ao }}</span> @endif
                            <div class="text-muted">{{ \App\Http\Controllers\CauThuController::VI_TRI[$tv->user->vi_tri_so_truong] ?? '' }} · {{ $tv->user->uyTinNhan() }}</div>
                        </div>
                        @if (($laDT && $tv->vai_tro !== 'doi_truong') || ($u && $tv->user_id === $u->id && $tv->vai_tro !== 'doi_truong'))
                            <form method="POST" action="{{ route('khach.doi.roi', $tv) }}" onsubmit="return confirm('{{ $laDT && $tv->user_id !== $u->id ? 'Mời '.$tv->user->name.' ra khỏi đội?' : 'Rời đội?' }}')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger py-0">{{ $laDT && $tv->user_id !== $u->id ? 'Mời ra' : 'Rời đội' }}</button></form>
                        @endif
                        @if ($laDT && $tv->vai_tro !== 'doi_truong')
                            <form method="POST" action="{{ route('khach.doi.doi-truong', $doi) }}" onsubmit="return confirm('Chuyển đội trưởng cho {{ $tv->user->name }}?')">@csrf
                                <input type="hidden" name="user_id" value="{{ $tv->user_id }}"><button class="btn btn-sm btn-outline-secondary py-0" title="Chuyển đội trưởng">C</button></form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($laDT)
            @if ($choDuyet->isNotEmpty())
                <h3 class="h6 mt-3">Xin vào đội</h3>
                @foreach ($choDuyet as $tv)
                    <div class="d-flex justify-content-between align-items-center small border-bottom py-1">
                        <a href="{{ route('cau-thu.show', $tv->user) }}">{{ $tv->user->name }} · {{ $tv->user->uyTinNhan() }}</a>
                        <span class="d-flex gap-1">
                            <form method="POST" action="{{ route('khach.doi.duyet', $tv) }}">@csrf @method('PATCH')<input type="hidden" name="dong_y" value="1"><button class="btn btn-sm btn-success py-0">Nhận</button></form>
                            <form method="POST" action="{{ route('khach.doi.duyet', $tv) }}">@csrf @method('PATCH')<input type="hidden" name="dong_y" value="0"><button class="btn btn-sm btn-outline-secondary py-0">Từ chối</button></form>
                        </span>
                    </div>
                @endforeach
            @endif
            <form method="POST" action="{{ route('khach.doi.thanh-vien', $doi) }}" class="d-flex gap-1 mt-3">@csrf
                <input name="tim" class="form-control form-control-sm" placeholder="Thêm thành viên: SĐT hoặc email">
                <input name="so_ao" type="number" min="0" max="99" class="form-control form-control-sm" style="max-width: 80px" placeholder="Số áo">
                <button class="btn btn-sm btn-success text-nowrap">Thêm</button>
            </form>
        @endif
    </div></div>

    {{-- Trận gần đây --}}
    @if ($tranGanDay->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">Trận gần đây</h2>
            @foreach ($tranGanDay as $t)
                <div class="small border-bottom py-1">{{ $t->doiNha->ten }} <strong>{{ $t->ban_thang_nha }} – {{ $t->ban_thang_khach }}</strong> {{ $t->doiKhach->ten }}
                    <span class="text-muted">· {{ $t->giaiDau->ten }} ({{ $t->vong }})</span></div>
            @endforeach
        </div></div>
    @endif

    @if ($laDT)
        <details class="card border-0 shadow-sm"><summary class="card-body">Sửa thông tin đội</summary>
            <form method="POST" action="{{ route('khach.doi.update', $doi) }}" enctype="multipart/form-data" class="card-body pt-0" novalidate>
                @csrf @method('PUT')
                @include('doi._form', ['doi' => $doi])
                <button class="btn btn-success">Lưu</button>
            </form>
        </details>
    @endif
</div>
@endsection
