@extends('layouts.app')
@section('title', 'Giải đấu')

@section('content')
<h1 class="h4 mb-3">Giải đấu</h1>
<div class="row g-3">
@forelse ($giais as $g)
    <div class="col-12 col-md-6 col-lg-4">
        <a href="{{ route('giai-dau.show', $g) }}" class="card h-100 border-0 shadow-sm text-decoration-none text-body"><div class="card-body">
            <span class="badge {{ ['mo_dang_ky' => 'bg-success', 'dang_dien_ra' => 'bg-primary', 'ket_thuc' => 'bg-secondary'][$g->trang_thai] ?? 'bg-dark' }}">{{ \App\Models\GiaiDau::TRANG_THAI[$g->trang_thai] }}</span>
            <h2 class="h6 mt-2 mb-1"><i class="bi bi-trophy text-warning"></i> {{ $g->ten }}</h2>
            <div class="small text-muted">{{ $g->chuSan->hoSoChuSan?->ten_co_so }} · Sân {{ $g->loai_san }} · {{ \App\Models\GiaiDau::THE_THUC[$g->the_thuc] }}</div>
            <div class="small">{{ \Carbon\Carbon::parse($g->tu_ngay)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($g->den_ngay)->format('d/m/Y') }}
                · {{ $g->doi_da_duyet_count }}/{{ $g->so_doi_toi_da }} đội
                @if ($g->le_phi) · lệ phí {{ number_format($g->le_phi, 0, ',', '.') }}đ @endif</div>
        </div></a>
    </div>
@empty
    <div class="col-12 text-center text-muted py-5 bg-white rounded">Chưa có giải đấu.</div>
@endforelse
</div>
@endsection
