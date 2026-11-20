@extends('layouts.app')
@section('title', 'Đặt sân bóng')

@section('content')
<div class="text-center py-2">
    <h1 class="h4 fw-bold mb-1">Tìm sân, đặt giờ trong 1 phút</h1>
    <p class="text-muted small mb-3">Xem lịch trống hôm nay, đặt và cọc bằng mã VietQR.</p>
</div>

<form method="GET" action="{{ route('trang-chu') }}" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2">
        <div class="col-12 col-md-3">
            <select name="tinh" id="locTinh" class="form-select" data-xa-phuong="#locXa">
                <option value="">Tất cả tỉnh/thành</option>
                @foreach ($tinhThanhs as $t)
                    <option value="{{ $t->id }}" @selected((int) ($loc['tinh'] ?? 0) === $t->id)>{{ $t->ten }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <select name="xa" id="locXa" class="form-select">
                <option value="">Tất cả xã/phường</option>
                @foreach ($xaPhuongs as $x)
                    <option value="{{ $x->id }}" @selected((int) ($loc['xa'] ?? 0) === $x->id)>{{ $x->ten }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <div class="btn-group w-100" role="group">
                @foreach (['' => 'Tất cả', '5' => 'Sân 5', '7' => 'Sân 7', '11' => 'Sân 11'] as $gt => $ten)
                    <input type="radio" class="btn-check" name="loai" value="{{ $gt }}" id="loai{{ $gt ?: 'all' }}" @checked((string) ($loc['loai'] ?? '') === (string) $gt)>
                    <label class="btn btn-outline-success btn-sm d-flex align-items-center justify-content-center" for="loai{{ $gt ?: 'all' }}">{{ $ten }}</label>
                @endforeach
            </div>
        </div>
        <div class="col-8 col-md-2"><input name="q" value="{{ $loc['q'] ?? '' }}" class="form-control" placeholder="Tên sân"></div>
        <div class="col-4 col-md-1"><button class="btn btn-success w-100"><i class="bi bi-search"></i></button></div>
    </div>
</form>

@if ($keoMoi->isNotEmpty())
    @php $moSan = (bool) ($loc['tinh'] ?? null); @endphp
    <div class="bg-white rounded shadow-sm p-2 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <button class="btn btn-sm btn-danger" type="button" data-bs-toggle="collapse" data-bs-target="#keoMoi" aria-expanded="{{ $moSan ? 'true' : 'false' }}">
                ⚔ {{ $soKeoMo }} kèo đang tìm đối{{ $moSan ? ' trong tỉnh đã chọn' : '' }} <i class="bi bi-chevron-down"></i></button>
            <a href="{{ route('keo.index', array_filter(['tinh' => $loc['tinh'] ?? null])) }}" class="btn btn-sm btn-danger fw-semibold"><i class="bi bi-funnel"></i> Xem tất cả & lọc kèo</a>
        </div>
        <div class="collapse {{ $moSan ? 'show' : '' }}" id="keoMoi">
            <div class="row g-2 mt-1">
                @foreach ($keoMoi as $k)
                    @php $sk = $k->sanThuc(); @endphp
                    <div class="col-12 col-md-6 col-lg-4">
                        <a href="{{ route('keo.show', $k) }}" class="d-flex gap-2 align-items-center border rounded p-2 text-decoration-none text-body h-100 border-start border-4 {{ $k->laKeoTruoc() ? 'border-info' : 'border-success' }}">
                            <img src="{{ $k->doiA?->logoUrl() ?? 'https://ui-avatars.com/api/?background=dc3545&color=fff&name='.urlencode($sk->ten) }}" width="40" height="40" class="rounded-circle object-fit-cover flex-shrink-0" alt="">
                            <span class="small text-break">
                                <strong>{{ $k->laDoiDau() ? ($k->doiA?->ten ?? 'Ô kèo của sân').' ⚔ chờ đối thủ' : 'Ghép lẻ · cần '.$k->so_nguoi_can.' người' }}</strong><br>
                                {{ $k->batDauLuc()->format('d/m/Y') }} {{ $k->nhanGio() }} · <span class="text-muted">{{ $sk->ten }}</span>
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div class="small text-muted mb-2">{{ $coSos->total() }} cơ sở · Lịch hôm nay {{ today()->format('d/m/Y') }}:
    <span class="badge bg-success">còn sân</span> <span class="badge bg-danger">hết sân</span> <span class="badge bg-warning text-dark">đang giữ</span></div>

<div class="row g-3">
    @forelse ($coSos as $c)
        @php $dau = $c->sans->first(); @endphp
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <a href="{{ route('san.show', $dau) }}" class="ratio ratio-16x9 d-block">
                    <img src="{{ $dau->anhDaiDien() }}" class="card-img-top object-fit-cover" alt="{{ $c->ten }}" loading="lazy">
                </a>
                <div class="card-body pb-2">
                    <h2 class="h6 mb-1"><a href="{{ route('san.show', $dau) }}" class="text-decoration-none text-body">{{ $c->ten }}</a></h2>
                    <div class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> {{ $c->diaChiDayDu() }}</div>
                    @foreach ($c->sans->groupBy('loai_san') as $loai => $nhom)
                        @php
                            $khungs = collect($homNay[$c->id][$loai] ?? [])->where('trang_thai', '!=', 'qua_gio');
                            $soTrong = $khungs->where('trang_thai', 'trong')->count();
                        @endphp
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex flex-wrap justify-content-between gap-1 small">
                                <a href="{{ route('san.show', $nhom->first()) }}" class="fw-semibold text-decoration-none">
                                    <span class="badge bg-success-subtle text-success-emphasis">{{ \App\Models\San::LOAI[$loai] }}</span>
                                    {{ $nhom->map(fn ($x) => $x->tenHienThi())->join(', ') }}</a>
                                <span class="text-muted">Hôm nay: {{ $soTrong }} khung trống</span>
                            </div>
                            <div class="d-flex flex-nowrap overflow-auto gap-1 pb-1 mt-1" style="scrollbar-width: thin">
                                @forelse ($khungs as $o)
                                    @php $mau = ['trong' => 'success', 'dang_giu' => 'warning', 'da_dat' => 'danger'][$o['trang_thai']]; @endphp
                                    <span class="badge bg-{{ $mau }}{{ $mau === 'warning' ? ' text-dark' : '' }} flex-shrink-0" title="{{ isset($o['tong']) ? 'Còn '.$o['con_trong'].'/'.$o['tong'].' sân' : '' }}">{{ substr($o['khung']->gio_bat_dau, 0, 5) }}</span>
                                @empty
                                    <span class="small text-muted">Hết giờ hôm nay</span>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="card-footer bg-white border-0 pt-0">
                    <a href="{{ route('san.show', $dau) }}" class="btn btn-sm btn-success w-100">Xem tuần & đặt sân</a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center text-muted py-5 bg-white rounded">Không tìm thấy sân phù hợp. Thử bỏ bớt bộ lọc.</div>
    @endforelse
</div>
<div class="mt-3">{{ $coSos->links() }}</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-xa-phuong]').forEach(function (chonTinh) {
    chonTinh.addEventListener('change', async function () {
        const chonXa = document.querySelector(chonTinh.dataset.xaPhuong);
        chonXa.innerHTML = '<option value="">Tất cả xã/phường</option>';
        if (!chonTinh.value) return;
        const ds = await (await fetch('{{ route('dia-chi.xa-phuong') }}?tinh_thanh_id=' + chonTinh.value)).json();
        chonXa.innerHTML += ds.map(x => `<option value="${x.id}">${x.ten.replace(/</g, '&lt;')}</option>`).join('');
    });
});
</script>
@endpush
