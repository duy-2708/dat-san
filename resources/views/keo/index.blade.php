@extends('layouts.app')
@section('title', 'Cáp kèo')

@section('content')
@php
    $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ';
    $q = fn (array $doi) => route('keo.index', array_filter(array_merge($loc, $doi), fn ($v) => $v !== null && $v !== ''));
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h1 class="h4 mb-0">Cáp kèo đang tìm</h1>
    @if ($laPhiaSan)
        <a href="{{ route('keo.san-tao') }}" class="btn btn-danger"><i class="bi bi-plus-lg"></i> Tạo kèo (sân làm giùm)</a>
    @endif
</div>

{{-- Bộ lọc --}}
<form class="bg-white rounded shadow-sm p-2 mb-2 row g-2 align-items-end small" id="locKeo">
    <div class="col-6 col-md-3"><label class="form-label mb-0">Tỉnh/thành</label>
        <select name="tinh" class="form-select form-select-sm" onchange="this.form.xa.value='';this.form.san.value='';this.form.submit()">
            <option value="">Tất cả</option>
            @foreach ($tinhThanhs as $t) <option value="{{ $t->id }}" @selected((int) ($loc['tinh'] ?? 0) === $t->id)>{{ $t->ten }}</option> @endforeach
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label mb-0">Xã/phường</label>
        <select name="xa" class="form-select form-select-sm" onchange="this.form.san.value='';this.form.submit()" @disabled($xaPhuongs->isEmpty())>
            <option value="">Tất cả</option>
            @foreach ($xaPhuongs as $x) <option value="{{ $x->id }}" @selected((int) ($loc['xa'] ?? 0) === $x->id)>{{ $x->ten }}</option> @endforeach
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label mb-0">Sân</label>
        <select name="san" class="form-select form-select-sm" onchange="this.form.submit()" @disabled($sans->isEmpty())>
            <option value="">{{ $sans->isEmpty() ? 'Chọn tỉnh trước' : 'Tất cả sân' }}</option>
            @foreach ($sans as $s) <option value="{{ $s->id }}" @selected((int) ($loc['san'] ?? 0) === $s->id)>{{ $s->ten }}</option> @endforeach
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label mb-0">Loại sân</label>
        <select name="loai_san" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Tất cả</option>
            @foreach ([5, 7, 11] as $l) <option value="{{ $l }}" @selected((int) ($loc['loai_san'] ?? 0) === $l)>Sân {{ $l }}</option> @endforeach
        </select></div>
    <div class="col-12 d-flex flex-wrap gap-1 align-items-center">
        <span class="text-muted me-1">Ngày:</span>
        @foreach (['' => 'Sắp tới', 'hom_nay' => 'Hôm nay', 'ngay_mai' => 'Ngày mai', 'cuoi_tuan' => 'Cuối tuần'] as $ma => $nhan)
            <a href="{{ $q(['ngay' => $ma]) }}" class="btn btn-sm {{ ($loc['ngay'] ?? '') === $ma ? 'btn-success' : 'btn-outline-success' }} py-0">{{ $nhan }}</a>
        @endforeach
        <input type="date" class="form-control form-control-sm" style="width: 150px" value="{{ preg_match('/^\d{4}-/', $loc['ngay'] ?? '') ? $loc['ngay'] : '' }}"
               onchange="location.href='{{ $q(['ngay' => '__']) }}'.replace('__', this.value)">
        <span class="text-muted ms-md-3 me-1">Loại:</span>
        @foreach (['' => 'Tất cả', 'doi_dau' => 'Đội đấu đội', 'ghep_le' => 'Ghép lẻ'] as $ma => $nhan)
            <a href="{{ $q(['loai' => $ma]) }}" class="btn btn-sm {{ ($loc['loai'] ?? '') === $ma ? 'btn-secondary' : 'btn-outline-secondary' }} py-0">{{ $nhan }}</a>
        @endforeach
        <span class="text-muted ms-md-3 me-1">Sân:</span>
        @foreach (['' => 'Tất cả', 'da_dat' => 'Đã đặt sân', 'chua_dat' => 'Chưa đặt sân'] as $ma => $nhan)
            <a href="{{ $q(['kieu' => $ma]) }}" class="btn btn-sm {{ ($loc['kieu'] ?? '') === $ma ? 'btn-secondary' : 'btn-outline-secondary' }} py-0">{{ $nhan }}</a>
        @endforeach
        <input type="hidden" name="ngay" value="{{ $loc['ngay'] ?? '' }}"><input type="hidden" name="loai" value="{{ $loc['loai'] ?? '' }}"><input type="hidden" name="kieu" value="{{ $loc['kieu'] ?? '' }}">
    </div>
    @php
        $dangLoc = array_filter([
            ($loc['tinh'] ?? null) ? $tinhThanhs->firstWhere('id', (int) $loc['tinh'])?->ten : null,
            ($loc['xa'] ?? null) ? $xaPhuongs->firstWhere('id', (int) $loc['xa'])?->ten : null,
            ($loc['san'] ?? null) ? $sans->firstWhere('id', (int) $loc['san'])?->ten : null,
            ($loc['loai_san'] ?? null) ? 'Sân '.$loc['loai_san'] : null,
            ($loc['ngay'] ?? null) ? (['hom_nay' => 'Hôm nay', 'ngay_mai' => 'Ngày mai', 'cuoi_tuan' => 'Cuối tuần'][$loc['ngay']]
                ?? rescue(fn () => \Carbon\Carbon::parse($loc['ngay'])->format('d/m/Y'), $loc['ngay'], false)) : null,
            ($loc['loai'] ?? null) ? ['doi_dau' => 'Đội đấu đội', 'ghep_le' => 'Ghép lẻ'][$loc['loai']] ?? null : null,
            ($loc['kieu'] ?? null) ? ['da_dat' => 'Đã đặt sân', 'chua_dat' => 'Chưa đặt sân'][$loc['kieu']] ?? null : null,
        ]);
    @endphp
    <div class="col-12 d-flex flex-wrap gap-2 align-items-center border-top pt-2">
        <button class="btn btn-sm btn-success"><i class="bi bi-funnel-fill"></i> Lọc</button>
        @if ($dangLoc)
            <a href="{{ route('keo.index') }}" class="btn btn-sm btn-danger"><i class="bi bi-x-lg"></i> Xoá lọc</a>
            <span class="text-muted">Đang lọc:</span>
            @foreach ($dangLoc as $nhan) <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">{{ $nhan }}</span> @endforeach
            <span class="text-muted">— {{ $keos->count() }} kèo</span>
        @else
            <span class="text-muted">Chưa lọc — đang xem tất cả kèo sắp tới ({{ $keos->count() }})</span>
        @endif
    </div>
</form>

{{-- Chú thích --}}
<div class="small text-muted mb-3 d-flex flex-wrap gap-3">
    <span><span class="badge bg-success">Đã đặt sân</span> sân chắc chắn có — nhận kèo là đá</span>
    <span><span class="badge bg-info text-dark">Chưa đặt sân</span> đủ hai đội mới đặt, lúc đó mỗi đội cọc 15%</span>
    <span>Đăng kèo: mở trang sân → khung trống → <strong>⚔ Cáp kèo giờ này</strong></span>
</div>

<div class="row g-2">
@forelse ($keos as $k)
    @php $san = $k->sanThuc(); $doi = $k->doiA; $nguoi = $k->dai_dien_a_id ? $k->daiDienA : ($k->laKeoTruoc() ? null : $k->nguoiTao); @endphp
    <div class="col-12 col-lg-6">
        <a href="{{ route('keo.show', $k) }}" class="d-flex gap-3 bg-white rounded shadow-sm p-3 text-decoration-none text-body h-100 border-start border-4 {{ $k->laKeoTruoc() ? 'border-info' : 'border-success' }}">
            @if ($k->laDoiDau())
                <img src="{{ $doi?->logoUrl() ?? 'https://ui-avatars.com/api/?background=6c757d&color=fff&name='.urlencode($san->ten) }}" width="56" height="56" class="rounded-circle object-fit-cover flex-shrink-0" alt="">
            @else
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 56px; height: 56px">+{{ $k->so_nguoi_can }}</div>
            @endif
            <div class="flex-grow-1 small" style="min-width: 0">
                <div class="d-flex justify-content-between gap-2 mb-1">
                    <span class="fw-bold fs-6 text-break">
                        @if ($k->laDoiDau()) {{ $doi?->ten ?? 'Ô kèo của sân' }} <span class="text-danger">⚔</span> <span class="fw-normal text-muted">chờ đối thủ</span>
                        @else Ghép lẻ · cần {{ $k->so_nguoi_can }} người @endif
                    </span>
                    <span class="d-flex gap-1 flex-shrink-0">
                        <span class="badge {{ $k->laKeoTruoc() ? 'bg-info text-dark' : 'bg-success' }}">{{ $k->laKeoTruoc() ? 'Chưa đặt sân' : 'Đã đặt sân' }}</span>
                        <span class="badge bg-light text-dark border">{{ \App\Models\Keo::TRINH_DO[$k->trinh_do] }}</span>
                    </span>
                </div>
                <div class="text-muted mb-1">
                    @if ($nguoi) <i class="bi bi-person"></i> Người ra kèo: <strong class="text-body">{{ $nguoi->name }}</strong>{{ $doi && $doi->laDoiTruong($nguoi) ? ' (đội trưởng)' : '' }} · uy tín {{ $nguoi->diem_uy_tin }}
                    @else <i class="bi bi-shop"></i> Sân tạo, chờ đội vào @endif
                </div>
                <div><i class="bi bi-calendar-event"></i> <strong>{{ $k->batDauLuc()->format('d/m/Y') }} · {{ $k->nhanGio() }}</strong> · {{ $san->tenHienThi() }} · sân {{ $san->loai_san }}</div>
                <div class="text-muted"><i class="bi bi-geo-alt"></i> {{ $san->diaChiDayDu() }}</div>
                <div class="mt-1">
                    @if ($k->laDoiDau())
                        Thua chịu <strong>{{ \App\Models\Keo::TY_LE[$k->ty_le_thua] }}</strong> ·
                        @if ($k->mien_coc) <strong class="text-success">miễn cọc (sân bảo lãnh)</strong>
                        @else {{ $k->laKeoTruoc() ? 'mỗi đội cọc' : 'nhận kèo cọc' }} <strong>{{ $tien($k->coc_moi_ben) }}</strong> @endif
                    @else
                        {{ $tien($k->tien_moi_nguoi) }}/người @if ($k->tien_coc_ca_nhan) · cọc {{ $tien($k->tien_coc_ca_nhan) }} @endif
                    @endif
                </div>
            </div>
        </a>
    </div>
@empty
    <div class="col-12 text-center text-muted py-5 bg-white rounded">Không có kèo nào khớp bộ lọc.</div>
@endforelse
</div>
@endsection
