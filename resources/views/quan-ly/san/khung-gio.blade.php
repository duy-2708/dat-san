@extends('layouts.app')
@section('title', 'Khung giờ & giá — '.$san->ten)

@section('content')
@php
    $laChu = auth()->user()->can('update', $san);
    $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ';
    $gio = fn ($t) => substr($t, 0, 5);
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <a href="{{ route('quan-ly.san.index') }}" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i></a>
    <h1 class="h4 mb-0">Khung giờ & giá — {{ $san->ten }}</h1>
    @if ($laChu) <a href="{{ route('quan-ly.san.edit', $san) }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-pencil"></i> Thông tin sân</a> @endif
</div>
<p class="text-muted small">Mở cửa {{ $gio($san->gio_mo_cua) }}–{{ $gio($san->gio_dong_cua) }}. Khách đặt theo từng khung. Cọc để trống = tự tính {{ $tyLe }}% giá khung, hoặc tự nhập số tiền cọc mong muốn.
    Ngày lễ chưa đặt giá riêng thì dùng giá cuối tuần. Giờ gõ tự do (VD <code>6h30</code>, <code>22:15</code>), bước 15 phút; mở xuyên đêm đặt 00:00–24:00.</p>

@if ($errors->any())
    <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif
@if ($sanNhanGia->isNotEmpty())
    <div class="alert alert-info py-2 small"><i class="bi bi-link-45deg"></i> Bảng giá dùng chung cho {{ \App\Models\San::LOAI[$san->loai_san] }} của cơ sở:
        <strong>{{ $san->ten }}, {{ $sanNhanGia->pluck('ten')->join(', ') }}</strong> — sửa ở đây là áp dụng cho tất cả.
        Sân nào cần giá khác: vào <em>Thông tin sân</em> → bật <strong>Giá riêng</strong>.</div>
@elseif ($san->gia_rieng)
    <div class="alert alert-warning py-2 small">Sân này đang dùng <strong>giá riêng</strong>, không theo bảng giá chung của cơ sở.</div>
@endif

@if ($laChu)
{{-- TẠO NHANH --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white">
        <a class="text-decoration-none fw-semibold" data-bs-toggle="collapse" href="#taoNhanh"><i class="bi bi-magic"></i> Tạo nhanh cả ngày</a>
    </div>
    <div id="taoNhanh" class="collapse {{ $theoLoai->isEmpty() ? 'show' : '' }}">
        <form method="POST" action="{{ route('quan-ly.san.khung-gio.tao-nhanh', $san) }}" class="card-body row g-2">
            @csrf
            <div class="col-12">
                @foreach (\App\Models\KhungGio::LOAI_NGAY as $ma => $ten)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="loai_ngays[]" value="{{ $ma }}" id="tn_{{ $ma }}" @checked($ma !== 'ngay_le')>
                        <label class="form-check-label" for="tn_{{ $ma }}">{{ $ten }}</label>
                    </div>
                @endforeach
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Mỗi trận (phút)</label>
                <input name="thoi_luong" type="number" min="30" max="300" step="15" value="90" list="ds-thoi-luong" class="form-control">
                <datalist id="ds-thoi-luong"><option value="60"><option value="90"><option value="120"></datalist>
            </div>
            <div class="col-12">
                <div class="small fw-semibold mb-1">Giá theo buổi <span class="text-muted fw-normal">(sửa giờ và giá tuỳ ý; ô giá để trống = lấy giá dòng trên — chỉ cần nhập 1 giá là phủ cả ngày; xoá giờ của buổi nào thì không tạo buổi đó)</span></div>
                <div class="form-check form-switch small mb-2">
                    <input class="form-check-input" type="checkbox" name="gia_khac_ngay" value="1" id="giaKhacNgay"
                           onchange="document.querySelectorAll('.cot-gia-khac').forEach(e => e.classList.toggle('d-none', ! this.checked))">
                    <label class="form-check-label" for="giaKhacNgay"><strong>Cuối tuần / ngày lễ giá khác</strong> — tắt: mọi loại ngày cùng một giá; bật: nhập thêm giá T7–CN và giá lễ (để trống = lấy giá ngày thường)</label>
                </div>
                <div class="row g-1 small text-muted d-none d-md-flex">
                    <div class="col-md-2"></div><div class="col-md-1">Từ</div><div class="col-md-1">Đến</div>
                    <div class="col-md-2">Ngày thường</div><div class="col-md-2 cot-gia-khac d-none">T7, CN</div><div class="col-md-2 cot-gia-khac d-none">Ngày lễ</div>
                </div>
                @php
                    $mo = substr($san->gio_mo_cua, 0, 5); $dong = substr($san->gio_dong_cua, 0, 5);
                    $goiY = [['Sáng', $mo, '11:00', false], ['Trưa', '11:00', '15:00', false], ['Chiều', '15:00', '17:00', false], ['Tối (giờ vàng)', '17:00', '21:00', true], ['Khuya', '21:00', $dong, false]];
                @endphp
                @foreach ($goiY as $i => [$ten, $tu, $den, $vang])
                    <div class="row g-1 align-items-center mb-1">
                        <div class="col-12 col-md-2 small">{{ $ten }}</div>
                        <div class="col-3 col-md-1"><input name="muc[{{ $i }}][tu]" value="{{ $tu }}" list="ds-gio" class="form-control form-control-sm" placeholder="từ"></div>
                        <div class="col-3 col-md-1"><input name="muc[{{ $i }}][den]" value="{{ $den }}" list="ds-gio" class="form-control form-control-sm" placeholder="đến"></div>
                        <div class="col-6 col-md-2"><input name="muc[{{ $i }}][gia]" inputmode="numeric" class="form-control form-control-sm o-tien" placeholder="VD 300.000"></div>
                        <div class="col-6 col-md-2 cot-gia-khac d-none"><input name="muc[{{ $i }}][gia_cuoi_tuan]" inputmode="numeric" class="form-control form-control-sm o-tien" placeholder="Giá T7, CN"></div>
                        <div class="col-6 col-md-2 cot-gia-khac d-none"><input name="muc[{{ $i }}][gia_le]" inputmode="numeric" class="form-control form-control-sm o-tien" placeholder="Giá lễ"></div>
                        <div class="col-12 col-md-2 small"><label><input type="checkbox" name="muc[{{ $i }}][vang]" value="1" @checked($vang)> Giờ vàng</label></div>
                    </div>
                @endforeach
            </div>
            <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="ghi_de" value="1" id="ghi_de">
                    <label class="form-check-label small" for="ghi_de">Xoá khung cũ của loại ngày đã chọn rồi tạo lại</label>
                </div>
                <button class="btn btn-success ms-auto">Tạo khung giờ</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- DANH SÁCH THEO LOẠI NGÀY --}}
<ul class="nav nav-pills mb-2 gap-1" role="tablist">
    @foreach (\App\Models\KhungGio::LOAI_NGAY as $ma => $ten)
        <li class="nav-item">
            <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#tab_{{ $ma }}" type="button">
                {{ $ten }} <span class="badge bg-light text-dark">{{ $theoLoai->get($ma)?->count() ?? 0 }}</span>
            </button>
        </li>
    @endforeach
</ul>

<div class="tab-content">
@foreach (\App\Models\KhungGio::LOAI_NGAY as $ma => $ten)
    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab_{{ $ma }}">
        <div class="bg-white rounded shadow-sm">
            @php $ds = $theoLoai->get($ma, collect()); @endphp
            @if ($laChu && $ds->isNotEmpty())
                <form method="POST" action="{{ route('quan-ly.san.khung-gio.luu-tat-ca', $san) }}" class="form-khung">
                    @csrf @method('PUT')
                    <input type="hidden" name="loai_ngay" value="{{ $ma }}">
                    <div class="row g-1 px-2 pt-2 small text-muted d-none d-md-flex">
                        <div class="col-md-2">Bắt đầu</div><div class="col-md-2">Kết thúc</div><div class="col-md-2">Giá (đ)</div>
                        <div class="col-md-2">Cọc (đ) <i class="bi bi-info-circle" title="Để trống = tự tính {{ $tyLe }}%"></i></div><div class="col-md-3"></div>
                    </div>
                    @foreach ($ds as $k)
                        <div class="row g-1 align-items-center border-bottom p-2 {{ $k->dang_ap_dung ? '' : 'opacity-50' }}">
                            <div class="col-6 col-md-2"><input name="khung[{{ $k->id }}][gio_bat_dau]" value="{{ $gio($k->gio_bat_dau) }}" list="ds-gio" class="form-control form-control-sm"></div>
                            <div class="col-6 col-md-2"><input name="khung[{{ $k->id }}][gio_ket_thuc]" value="{{ $gio($k->gio_ket_thuc) }}" list="ds-gio" class="form-control form-control-sm"></div>
                            <div class="col-6 col-md-2"><input name="khung[{{ $k->id }}][gia]" value="{{ $k->gia }}" inputmode="numeric" class="form-control form-control-sm o-tien o-gia"></div>
                            <div class="col-6 col-md-2"><input name="khung[{{ $k->id }}][tien_coc]" value="{{ $k->tien_coc }}" inputmode="numeric" class="form-control form-control-sm o-tien o-coc" placeholder="{{ \App\Support\Tien::chuoi($k->tienCoc($tyLe)) }}"></div>
                            <div class="col-9 col-md-3 d-flex gap-2 small">
                                <label><input type="checkbox" name="khung[{{ $k->id }}][la_gio_vang]" value="1" @checked($k->la_gio_vang)> Giờ vàng</label>
                                <label><input type="checkbox" name="khung[{{ $k->id }}][dang_ap_dung]" value="1" @checked($k->dang_ap_dung)> Mở bán</label>
                            </div>
                            <div class="col-3 col-md-1 text-end">
                                <button class="btn btn-sm btn-outline-danger" form="xoa{{ $k->id }}" onclick="return confirm('Xoá khung {{ $k->nhan() }}?')"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    @endforeach
                    <div class="p-2 text-end"><button class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu tất cả thay đổi</button></div>
                </form>
                @foreach ($ds as $k)
                    <form id="xoa{{ $k->id }}" method="POST" action="{{ route('quan-ly.khung-gio.destroy', $k) }}">@csrf @method('DELETE')</form>
                @endforeach
            @else
                @forelse ($ds as $k)
                    <div class="d-flex justify-content-between border-bottom p-2">
                        <span>{{ $k->nhan() }} @if ($k->la_gio_vang) <span class="badge bg-warning text-dark">Giờ vàng</span> @endif</span>
                        <span><span class="fw-semibold">{{ $tien($k->gia) }}</span> <span class="small text-muted">· cọc {{ $tien($k->tienCoc($tyLe)) }}</span></span>
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">Chưa có khung giờ cho {{ mb_strtolower($ten) }}.</div>
                @endforelse
            @endif

            @if ($laChu)
                <form method="POST" action="{{ route('quan-ly.san.khung-gio.store', $san) }}" class="row g-1 align-items-center p-2 bg-light rounded-bottom">
                    @csrf
                    <input type="hidden" name="loai_ngay" value="{{ $ma }}">
                    <div class="col-12 small fw-semibold">Thêm một khung</div>
                    <div class="col-6 col-md-2"><input name="gio_bat_dau" list="ds-gio" placeholder="Bắt đầu" class="form-control form-control-sm" required></div>
                    <div class="col-6 col-md-2"><input name="gio_ket_thuc" list="ds-gio" placeholder="Kết thúc" class="form-control form-control-sm" required></div>
                    <div class="col-6 col-md-2"><input name="gia" inputmode="numeric" placeholder="Giá" class="form-control form-control-sm o-tien" required></div>
                    <div class="col-6 col-md-2"><input name="tien_coc" inputmode="numeric" placeholder="Cọc (tự tính)" class="form-control form-control-sm o-tien"></div>
                    <div class="col-6 col-md-2"><div class="form-check small">
                        <input class="form-check-input" type="checkbox" name="la_gio_vang" value="1" id="nv_{{ $ma }}">
                        <label class="form-check-label" for="nv_{{ $ma }}">Giờ vàng</label></div></div>
                    <div class="col-6 col-md-2 text-end"><button class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Thêm</button></div>
                </form>
            @endif
        </div>
    </div>
@endforeach
</div>
@endsection

@push('scripts')
<script>
// Gõ giá → cập nhật gợi ý cọc {{ $tyLe }}% ngay (khi ô cọc để trống)
document.querySelectorAll('.form-khung .o-gia').forEach((o) => o.addEventListener('input', () => {
    const coc = o.closest('.row').querySelector('.o-coc');
    const gia = Number(o.value.replace(/\D/g, '')) || 0;
    coc.placeholder = (Math.ceil(gia * {{ $tyLe }} / 100 / 1000) * 1000).toLocaleString('vi-VN');
}));
</script>
@endpush
