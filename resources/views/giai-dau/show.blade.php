@extends('layouts.app')
@section('title', $g->ten)

@section('content')
@php $huyHieu = fn ($h) => match (true) { $h === 1 => '🥇', $h === 2 => '🥈', $h === 3 => '🥉', default => '' }; @endphp
<div class="mx-auto" style="max-width: 820px">
    @error('giai') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <span class="badge bg-success">{{ \App\Models\GiaiDau::TRANG_THAI[$g->trang_thai] }}</span>
        <h1 class="h4 mt-2 mb-1"><i class="bi bi-trophy text-warning"></i> {{ $g->ten }}</h1>
        <div class="small text-muted">Tổ chức: {{ $g->chuSan->hoSoChuSan?->ten_co_so }} · Sân {{ $g->loai_san }} · {{ \App\Models\GiaiDau::THE_THUC[$g->the_thuc] }}</div>
        <div class="small">{{ \Carbon\Carbon::parse($g->tu_ngay)->format('d/m') }} – {{ \Carbon\Carbon::parse($g->den_ngay)->format('d/m/Y') }}
            · tối đa {{ $g->so_doi_toi_da }} đội @if ($g->le_phi) · lệ phí {{ number_format($g->le_phi, 0, ',', '.') }}đ/đội @endif</div>
        @if ($g->mo_ta) <p class="small mt-2 mb-0" style="white-space: pre-line">{{ $g->mo_ta }}</p> @endif
        @auth
            @php $u = auth()->user(); $chuCua = $u->laChuSan() ? $u->id : ($u->laNhanVien() ? (int) $u->chu_san_id : null); @endphp
            @if ($chuCua === (int) $g->chu_san_id)
                <a href="{{ route('quan-ly.giai-dau.show', $g) }}" class="btn btn-sm btn-primary mt-2"><i class="bi bi-pencil-square"></i> Quản lý giải · nhập tỉ số</a>
            @endif
        @endauth
    </div></div>

    {{-- Đăng ký (đội trưởng) --}}
    @if ($g->trang_thai === 'mo_dang_ky')
        @auth
            @if ($doiCuaToi->isNotEmpty())
                <div class="card border-0 shadow-sm mb-3"><div class="card-body">
                    <h2 class="h6">Đăng ký đội</h2>
                    @foreach ($doiCuaToi as $doi)
                        @if (! $g->doiThamGias->contains('doi_id', $doi->id))
                            <form method="POST" action="{{ route('khach.giai-dau.dang-ky', $g) }}" class="border rounded p-2 mb-2">@csrf
                                <input type="hidden" name="doi_id" value="{{ $doi->id }}">
                                <div class="fw-semibold small mb-1">{{ $doi->ten }} — chọn cầu thủ dự giải:</div>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    @foreach ($doi->dangChoi as $tv)
                                        <label class="small"><input type="checkbox" name="cau_thu[]" value="{{ $tv->user_id }}" checked> {{ $tv->user->name }}</label>
                                    @endforeach
                                </div>
                                <button class="btn btn-sm btn-success">Đăng ký {{ $doi->ten }}</button>
                            </form>
                        @endif
                    @endforeach
                </div></div>
            @elseif (auth()->user()->laKhach())
                <div class="alert alert-light border small">Đội trưởng mới đăng ký được. <a href="{{ route('khach.doi.create') }}">Tạo đội</a></div>
            @endif
        @endauth
    @endif

    {{-- Đội tham gia --}}
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h2 class="h6">Đội tham gia</h2>
        <div class="row g-2">
            @foreach ($g->doiThamGias->whereIn('trang_thai', ['da_duyet', 'cho_duyet'])->sortBy(fn ($d) => $d->thu_hang ?? 999) as $dk)
                <div class="col-12 col-md-6">
                    <a href="{{ route('doi.show', $dk->doi) }}" class="d-flex align-items-center gap-2 border rounded p-2 text-decoration-none text-body">
                        <img src="{{ $dk->doi->logoUrl() }}" width="36" height="36" class="rounded-circle object-fit-cover" alt="">
                        <div class="small flex-grow-1">
                            <div class="fw-semibold">{{ $huyHieu($dk->thu_hang) }} {{ $dk->doi->ten }}</div>
                            <div class="text-muted">{{ $dk->cauThus->count() }} cầu thủ
                                @if ($dk->thu_hang) · Hạng {{ $dk->thu_hang }} @endif @if ($dk->danh_hieu) · {{ $dk->danh_hieu }} @endif</div>
                        </div>
                        @if ($dk->trang_thai === 'cho_duyet') <span class="badge bg-warning text-dark">chờ duyệt</span> @endif
                    </a>
                </div>
            @endforeach
        </div>
    </div></div>

    @include('giai-dau._bang')
</div>
@endsection
