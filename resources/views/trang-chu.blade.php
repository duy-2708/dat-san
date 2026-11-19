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

<div class="small text-muted mb-2">{{ $sans->total() }} sân · Lịch hôm nay {{ today()->format('d/m') }}:
    <span class="badge bg-success">trống</span> <span class="badge bg-danger">đã đặt</span> <span class="badge bg-warning text-dark">đang giữ</span></div>

<div class="row g-3">
    @forelse ($sans as $s)
        @php
            $khungs = collect($homNay[$s->id] ?? [])->where('trang_thai', '!=', 'qua_gio');
            $soTrong = $khungs->where('trang_thai', 'trong')->count();
        @endphp
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <a href="{{ route('san.show', $s) }}" class="ratio ratio-16x9 d-block">
                    <img src="{{ $s->anhDaiDien() }}" class="card-img-top object-fit-cover" alt="{{ $s->ten }}" loading="lazy">
                </a>
                <div class="card-body pb-2">
                    <div class="d-flex justify-content-between gap-2">
                        <h2 class="h6 mb-1"><a href="{{ route('san.show', $s) }}" class="text-decoration-none text-body">{{ $s->tenHienThi() }}</a></h2>
                        <span class="badge bg-success-subtle text-success-emphasis align-self-start">{{ \App\Models\San::LOAI[$s->loai_san] }}</span>
                    </div>
                    <div class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> {{ $s->diaChiDayDu() }}</div>
                    <div class="small fw-semibold mb-1">Hôm nay: {{ $soTrong }} khung trống</div>
                    <div class="d-flex flex-nowrap overflow-auto gap-1 pb-1" style="scrollbar-width: thin">
                        @forelse ($khungs as $o)
                            @php $mau = ['trong' => 'success', 'dang_giu' => 'warning', 'da_dat' => 'danger'][$o['trang_thai']]; @endphp
                            <span class="badge bg-{{ $mau }}{{ $mau === 'warning' ? ' text-dark' : '' }} flex-shrink-0">{{ substr($o['khung']->gio_bat_dau, 0, 5) }}</span>
                        @empty
                            <span class="small text-muted">Hết giờ hôm nay</span>
                        @endforelse
                    </div>
                </div>
                <div class="card-footer bg-white border-0 pt-0">
                    <a href="{{ route('san.show', $s) }}" class="btn btn-sm btn-success w-100">Xem tuần & đặt sân</a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center text-muted py-5 bg-white rounded">Không tìm thấy sân phù hợp. Thử bỏ bớt bộ lọc.</div>
    @endforelse
</div>
<div class="mt-3">{{ $sans->links() }}</div>
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
