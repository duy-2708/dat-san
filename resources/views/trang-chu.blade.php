@extends('layouts.app')
@section('title', 'Trang chủ')

@section('content')
<div class="text-center py-3">
    <h1 class="h3 fw-bold">Tìm sân, đặt giờ trong 1 phút</h1>
    <p class="text-muted mb-0">Bộ lọc Tỉnh/Thành → Xã/Phường và lịch hôm nay sẽ có ở giai đoạn 4.</p>
</div>
<div class="row g-3">
    @forelse ($sans as $s)
        <div class="col-12 col-md-6 col-lg-4">
            <a href="{{ route('san.show', $s) }}" class="card h-100 border-0 shadow-sm text-decoration-none text-body">
                <div class="ratio ratio-16x9"><img src="{{ $s->anhDaiDien() }}" class="card-img-top object-fit-cover" alt="{{ $s->ten }}" loading="lazy"></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><h2 class="h6 mb-1">{{ $s->tenHienThi() }}</h2>
                        <span class="badge bg-success-subtle text-success-emphasis">{{ \App\Models\San::LOAI[$s->loai_san] }}</span></div>
                    <div class="small text-muted"><i class="bi bi-geo-alt"></i> {{ $s->diaChiDayDu() }}</div>
                </div>
            </a>
        </div>
    @empty
        <div class="col-12 text-center text-muted">Chưa có sân nào.</div>
    @endforelse
</div>
@endsection
