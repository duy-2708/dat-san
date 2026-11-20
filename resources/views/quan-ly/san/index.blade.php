@extends('layouts.app')
@section('title', 'Sân của tôi')

@section('content')
@php $laChu = auth()->user()->laChuSan(); @endphp
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Sân của tôi</h1>
    @if ($laChu)
        <div class="d-flex gap-2">
            <a href="{{ route('quan-ly.ngay-le.index') }}" class="btn btn-outline-secondary"><i class="bi bi-calendar-heart"></i> Ngày lễ</a>
            <a href="{{ route('quan-ly.san.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm cơ sở / sân</a>
        </div>
    @endif
</div>

@error('goi') <div class="alert alert-warning">{{ $message }}</div> @enderror
@error('san') <div class="alert alert-danger">{{ $message }}</div> @enderror

@forelse ($nhomCoSo as $coSoId => $dsSan)
    @php $cs = $dsSan->first()->coSo; @endphp
    <div class="d-flex flex-wrap align-items-center gap-2 mt-3 mb-2">
        <h2 class="h5 mb-0"><i class="bi bi-buildings text-success"></i> {{ $cs?->ten ?? 'Cơ sở' }}</h2>
        <span class="text-muted small">{{ $dsSan->groupBy('loai_san')->map(fn ($g, $l) => $g->count().' sân '.$l)->join(' · ') }}</span>
        @if ($laChu && $cs)
            <a href="{{ route('quan-ly.san.create', ['co_so' => $cs->id]) }}" class="btn btn-sm btn-outline-success ms-auto"><i class="bi bi-plus-lg"></i> Thêm sân vào cơ sở</a>
        @endif
    </div>
    <div class="row g-3">
    @foreach ($dsSan as $s)
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm {{ $s->trang_thai === 'tam_ngung' ? 'opacity-75' : '' }}">
            <div class="ratio ratio-16x9">
                <img src="{{ $s->anhDaiDien() }}" class="card-img-top object-fit-cover" alt="{{ $s->ten }}" loading="lazy">
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <h2 class="h6 mb-1">{{ $s->tenHienThi() }}</h2>
                    <span class="badge bg-success-subtle text-success-emphasis">{{ \App\Models\San::LOAI[$s->loai_san] }}</span>
                </div>
                <div class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> {{ $s->diaChiDayDu() }}</div>
                @if ($s->sanCons->isNotEmpty())
                    <div class="small mb-1"><i class="bi bi-diagram-3"></i> Ghép từ: {{ $s->sanCons->pluck('ten')->join(', ') }}</div>
                @endif
                <div class="small">
                    @if ($s->khung_gios_count) <span class="text-success"><i class="bi bi-check-circle"></i> {{ $s->khung_gios_count }} khung giờ</span>
                    @else <span class="text-danger"><i class="bi bi-exclamation-circle"></i> Chưa có khung giờ — khách chưa đặt được</span> @endif
                    @if ($s->trang_thai === 'tam_ngung') <span class="badge bg-secondary ms-1">Tạm ngưng</span> @endif
                </div>
            </div>
            <div class="card-footer bg-white border-0 d-flex flex-wrap gap-2">
                <a href="{{ route('quan-ly.san.lich', $s) }}" class="btn btn-sm btn-success flex-grow-1"><i class="bi bi-calendar-week"></i> Lịch</a>
                <a href="{{ route('quan-ly.san.khung-gio.index', $s) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-clock"></i> Giá</a>
                @if ($laChu)
                    <a href="{{ route('quan-ly.san.edit', $s) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Sửa</a>
                    <form method="POST" action="{{ route('quan-ly.san.trang-thai', $s) }}">
                        @csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-{{ $s->trang_thai === 'hoat_dong' ? 'warning' : 'success' }}">
                            {{ $s->trang_thai === 'hoat_dong' ? 'Tạm ngưng' : 'Mở lại' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    @endforeach
    </div>
@empty
    <div>
        <div class="text-center text-muted py-5 bg-white rounded">
            <i class="bi bi-grid-3x3-gap display-5"></i>
            <p class="mt-2 mb-3">Chưa có sân nào.</p>
            @if ($laChu) <a href="{{ route('quan-ly.san.create') }}" class="btn btn-success">Thêm sân đầu tiên</a> @endif
        </div>
    </div>
@endforelse
@endsection
