@extends('layouts.app')
@section('title', $san->tenHienThi())

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        @if ($san->anhSans->count() > 1)
            <div id="anhSan" class="carousel slide rounded overflow-hidden shadow-sm" data-bs-ride="carousel">
                <div class="carousel-inner">
                    @foreach ($san->anhSans as $i => $anh)
                        <div class="carousel-item {{ $i === 0 ? 'active' : '' }} ratio ratio-16x9">
                            <img src="{{ $anh->url() }}" class="d-block w-100 object-fit-cover" alt="{{ $san->ten }}">
                        </div>
                    @endforeach
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#anhSan" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                <button class="carousel-control-next" type="button" data-bs-target="#anhSan" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
            </div>
        @else
            <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm">
                <img src="{{ $san->anhDaiDien() }}" class="object-fit-cover" alt="{{ $san->ten }}">
            </div>
        @endif

        <div class="mt-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h1 class="h4 mb-1">@if ($san->coSo && $san->coSo->ten !== $san->ten)<span class="text-muted fw-normal">{{ $san->coSo->ten }} ·</span> @endif{{ $san->tenHienThi() }}</h1>
                <span class="badge bg-success">{{ \App\Models\San::LOAI[$san->loai_san] }}</span>
            </div>
            <div class="text-muted small mb-1"><i class="bi bi-geo-alt"></i> {{ $san->diaChiDayDu() }}</div>
            <div class="small mb-1"><i class="bi bi-shop"></i> {{ $hoSo->ten_co_so }}
                @if ($hoSo->sdt_lien_he) · <a href="tel:{{ $hoSo->sdt_lien_he }}"><i class="bi bi-telephone"></i> {{ $hoSo->sdt_lien_he }}</a> @endif
            </div>
            <div class="small mb-2"><i class="bi bi-clock"></i> Mở cửa {{ substr($san->gio_mo_cua, 0, 5) }}–{{ substr($san->gio_dong_cua, 0, 5) }}</div>
            @if ($san->tien_ich)
                <div class="d-flex flex-wrap gap-1 mb-2">
                    @foreach ($san->tien_ich as $ti)
                        <span class="badge bg-light text-dark border">{{ \App\Models\San::TIEN_ICH[$ti] ?? $ti }}</span>
                    @endforeach
                </div>
            @endif
            @if ($san->mo_ta) <p class="small mb-0" style="white-space: pre-line">{{ $san->mo_ta }}</p> @endif
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @if ($cungCoSo->count() > 1)
                    <div class="mb-2">
                        <div class="small text-muted mb-1"><i class="bi bi-buildings"></i> {{ $san->coSo?->ten }} có {{ $cungCoSo->count() }} sân — chọn sân:</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($cungCoSo->groupBy('loai_san') as $loai => $nhom)
                                @if ($nhom->count() > 1)
                                    @php $dangGop = $gop && (int) $san->loai_san === (int) $loai; @endphp
                                    <a href="{{ route('san.show', $nhom->first()) }}" class="btn btn-sm {{ $dangGop ? 'btn-success' : 'btn-outline-success' }}">
                                        {{ \App\Models\San::LOAI[$loai] }} bất kỳ ({{ $nhom->count() }} sân)</a>
                                @endif
                                @foreach ($nhom as $x)
                                    @php $dangXem = ! $gop && $x->id === $san->id || ($nhom->count() === 1 && $x->id === $san->id); @endphp
                                    <a href="{{ route('san.show', $x) }}{{ $nhom->count() > 1 ? '?gop=0' : '' }}" class="btn btn-sm {{ $dangXem ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $x->ten }}</a>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endif
                <h2 class="h6 mb-1">Lịch {{ $gop ? \App\Models\San::LOAI[$san->loai_san].' — hệ thống tự xếp sân còn trống' : 'sân '.$san->ten }}</h2>
                <p class="small text-muted mb-2">Chọn khung trống để đặt. Cọc {{ $hoSo->ty_le_coc }}% qua mã VietQR, giữ chỗ {{ $hoSo->phut_giu_cho }} phút.
                    Huỷ trước {{ $hoSo->gio_huy_hoan_coc }} giờ được hoàn cọc.</p>
                @error('dat_san') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                @include('partials.lich-tuan', ['cheDo' => 'khach', 'url' => route('san.show', $san).($soCungLoai > 1 && ! $gop ? '?gop=0' : '')])
            </div>
        </div>
    </div>
</div>
@endsection
