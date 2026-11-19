@extends('layouts.app')
@section('title', 'Cáp kèo')

@section('content')
@php $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ'; @endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h1 class="h4 mb-0">Cáp kèo đang tìm</h1>
    <div class="btn-group btn-group-sm">
        <a href="{{ route('keo.index') }}" class="btn btn-outline-success {{ ! $loai ? 'active' : '' }}">Tất cả</a>
        <a href="{{ route('keo.index', ['loai' => 'doi_dau']) }}" class="btn btn-outline-success {{ $loai === 'doi_dau' ? 'active' : '' }}">Đội đấu đội</a>
        <a href="{{ route('keo.index', ['loai' => 'ghep_le']) }}" class="btn btn-outline-success {{ $loai === 'ghep_le' ? 'active' : '' }}">Ghép lẻ</a>
    </div>
</div>
<p class="small text-muted mb-3">Muốn đăng kèo? Mở trang sân → chọn khung trống → <strong>Cáp kèo giờ này</strong> (chưa cần đặt sân, đủ đội mới cọc).</p>

<div class="row g-2">
@forelse ($keos as $k)
    @php $san = $k->sanThuc(); $doi = $k->doiA; $nguoi = $k->daiDienA ?? $k->nguoiTao; @endphp
    <div class="col-12 col-lg-6">
        <a href="{{ route('keo.show', $k) }}" class="d-flex gap-3 align-items-center bg-white rounded shadow-sm p-3 text-decoration-none text-body h-100">
            @if ($k->laDoiDau())
                <img src="{{ $doi?->logoUrl() ?? 'https://ui-avatars.com/api/?background=dc3545&color=fff&name='.urlencode($nguoi->name) }}" width="52" height="52" class="rounded-circle object-fit-cover flex-shrink-0" alt="">
            @else
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 52px; height: 52px">+{{ $k->so_nguoi_can }}</div>
            @endif
            <div class="flex-grow-1 small" style="min-width: 0">
                <div class="d-flex justify-content-between gap-2">
                    <strong class="text-truncate">
                        @if ($k->laDoiDau()) {{ $doi?->ten ?? ($k->dai_dien_a_id ? $nguoi->name : 'Ô kèo của sân') }} <span class="text-danger">⚔</span> <span class="text-muted fw-normal">chờ đối thủ</span>
                        @else Ghép lẻ · cần {{ $k->so_nguoi_can }} người @endif
                    </strong>
                    <span class="badge bg-light text-dark border flex-shrink-0">{{ \App\Models\Keo::TRINH_DO[$k->trinh_do] }}</span>
                </div>
                <div><i class="bi bi-calendar-event"></i> {{ $k->batDauLuc()->format('d/m') }} · {{ $k->nhanGio() }} · {{ $san->tenHienThi() }} (sân {{ $san->loai_san }})</div>
                <div class="text-muted text-truncate"><i class="bi bi-geo-alt"></i> {{ $san->diaChiDayDu() }}</div>
                <div>
                    @if ($k->laDoiDau())
                        Thua chịu <strong>{{ \App\Models\Keo::TY_LE[$k->ty_le_thua] }}</strong> · mỗi đội cọc <strong>{{ $tien($k->coc_moi_ben) }}</strong>
                    @else
                        {{ $tien($k->tien_moi_nguoi) }}/người @if ($k->tien_coc_ca_nhan) · cọc {{ $tien($k->tien_coc_ca_nhan) }} @endif
                    @endif
                    @if ($k->dai_dien_a_id || ! $k->laKeoTruoc()) <span class="text-muted">· uy tín {{ $nguoi->diem_uy_tin }}</span> @endif
                    @if ($k->laKeoTruoc()) <span class="badge bg-info-subtle text-info-emphasis">chưa đặt sân</span> @endif
                </div>
            </div>
        </a>
    </div>
@empty
    <div class="col-12 text-center text-muted py-5 bg-white rounded">Chưa có kèo nào đang tìm.</div>
@endforelse
</div>
@endsection
