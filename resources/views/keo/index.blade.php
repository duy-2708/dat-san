@extends('layouts.app')
@section('title', 'Cáp kèo')

@section('content')
@php $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ'; @endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Cáp kèo đang tìm</h1>
    <div class="btn-group btn-group-sm">
        <a href="{{ route('keo.index') }}" class="btn btn-outline-success {{ ! $loai ? 'active' : '' }}">Tất cả</a>
        <a href="{{ route('keo.index', ['loai' => 'doi_dau']) }}" class="btn btn-outline-success {{ $loai === 'doi_dau' ? 'active' : '' }}">Đội đấu đội</a>
        <a href="{{ route('keo.index', ['loai' => 'ghep_le']) }}" class="btn btn-outline-success {{ $loai === 'ghep_le' ? 'active' : '' }}">Ghép lẻ</a>
    </div>
</div>
<p class="small text-muted">Muốn đăng kèo? Đặt sân trước, rồi bấm "Tạo kèo" ở lịch của bạn.</p>

<div class="row g-3">
@forelse ($keos as $k)
    @php $d = $k->datSan; @endphp
    <div class="col-12 col-md-6 col-lg-4">
        <a href="{{ route('keo.show', $k) }}" class="card h-100 border-0 shadow-sm text-decoration-none text-body">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-1">
                    <span class="badge {{ $k->laDoiDau() ? 'bg-danger' : 'bg-primary' }}">{{ $k->laDoiDau() ? 'Đội đấu đội' : 'Ghép lẻ' }}</span>
                    <span class="badge bg-light text-dark border">{{ \App\Models\Keo::TRINH_DO[$k->trinh_do] }}</span>
                </div>
                <div class="fw-semibold">{{ \Carbon\Carbon::parse($d->ngay)->format('d/m') }} · {{ $d->khungGioNhan() }}</div>
                <div class="small">{{ $d->san->tenHienThi() }} ({{ \App\Models\San::LOAI[$d->san->loai_san] }})</div>
                <div class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> {{ $d->san->diaChiDayDu() }}</div>
                @if ($k->laDoiDau())
                    <div class="small">Tỉ lệ thua chịu: <strong>{{ \App\Models\Keo::TY_LE[$k->ty_le_thua] }}</strong> · tiền sân {{ $tien($k->tien_san) }}</div>
                    <div class="small">Nhận kèo cọc <strong>{{ $tien($k->coc_moi_ben) }}</strong></div>
                @else
                    <div class="small">Cần <strong>{{ $k->so_nguoi_can }}</strong> người · {{ $tien($k->tien_moi_nguoi) }}/người
                        @if ($k->tien_coc_ca_nhan) · cọc {{ $tien($k->tien_coc_ca_nhan) }} @endif</div>
                @endif
                <div class="small text-muted mt-1">Chủ kèo: {{ $k->nguoiTao->name }} · {{ $k->nguoiTao->uyTinNhan() }}</div>
            </div>
        </a>
    </div>
@empty
    <div class="col-12 text-center text-muted py-5 bg-white rounded">Chưa có kèo nào đang tìm.</div>
@endforelse
</div>
@endsection
