@extends('layouts.app')
@section('title', $san->exists ? 'Sửa sân' : 'Thêm sân')

@section('content')
@php
    $chonGio = fn ($cot) => substr((string) old($cot, $san->$cot ?? ''), 0, 5);
@endphp
<div class="mx-auto" style="max-width: 760px">
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('quan-ly.san.index') }}" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i></a>
        <h1 class="h4 mb-0">{{ $san->exists ? 'Sửa '.$san->ten : 'Thêm sân' }}</h1>
        @if ($san->exists)
            <a href="{{ route('quan-ly.san.khung-gio.index', $san) }}" class="btn btn-sm btn-success ms-auto"><i class="bi bi-clock"></i> Khung giờ & giá</a>
        @endif
    </div>

    <form method="POST" action="{{ $san->exists ? route('quan-ly.san.update', $san) : route('quan-ly.san.store') }}"
          class="card card-body border-0 shadow-sm mb-3" novalidate>
        @csrf
        @if ($san->exists) @method('PUT') @endif

        <div class="row g-3">
            @if (! $san->exists)
                <div class="col-12">
                    <label class="form-label fw-semibold" for="co_so_id"><i class="bi bi-buildings"></i> Thuộc cơ sở</label>
                    <select id="co_so_id" name="co_so_id" class="form-select">
                        <option value="">➕ Cơ sở mới (nhập địa chỉ bên dưới)</option>
                        @foreach ($coSos as $c)
                            <option value="{{ $c->id }}" @selected((int) old('co_so_id', $san->co_so_id) === $c->id)>{{ $c->ten }} — đang có {{ $c->sans_count }} sân</option>
                        @endforeach
                    </select>
                    <div class="form-text">Một cơ sở (VD Sân Mỹ Anh) có nhiều sân: 5A, 5B, 7A... dùng chung địa chỉ, giờ mở cửa, tiện ích; sân cùng loại dùng chung bảng giá.</div>
                </div>
                <div class="col-md-8 phan-moi">
                    <label class="form-label" for="ten_co_so">Tên cơ sở</label>
                    <input id="ten_co_so" name="ten_co_so" value="{{ old('ten_co_so') }}" placeholder="VD: Sân bóng Mỹ Anh" class="form-control">
                    <div class="form-text">Để trống = lấy theo tên sân.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="so_luong">Số sân cùng loại</label>
                    <input id="so_luong" name="so_luong" type="number" min="1" max="10" value="{{ old('so_luong', 1) }}" class="form-control">
                    <div class="form-text">VD tên "Sân 5", số 3 → Sân 5A, 5B, 5C.</div>
                </div>
            @else
                <div class="col-12">
                    <label class="form-label fw-semibold" for="ten_co_so"><i class="bi bi-buildings"></i> Tên cơ sở</label>
                    <input id="ten_co_so" name="ten_co_so" value="{{ old('ten_co_so', $san->coSo?->ten) }}" class="form-control">
                    @php $coSoKhac = $coSos->where('id', '!=', $san->co_so_id); @endphp
                    @if ($coSoKhac->isNotEmpty())
                        <div class="input-group input-group-sm mt-2">
                            <span class="input-group-text">Gộp sân này vào cơ sở</span>
                            <select name="chuyen_co_so_id" class="form-select">
                                <option value="">— giữ nguyên —</option>
                                @foreach ($coSoKhac as $c) <option value="{{ $c->id }}">{{ $c->ten }} ({{ $c->sans_count }} sân)</option> @endforeach
                            </select>
                        </div>
                        <div class="form-text">Gộp xong sân dùng địa chỉ, giờ, tiện ích và bảng giá chung của cơ sở mới.</div>
                    @endif
                    @if ($san->coSo && $san->coSo->sans->count() > 1)
                        <div class="form-text text-warning-emphasis">Cơ sở có {{ $san->coSo->sans->count() }} sân ({{ $san->coSo->sans->pluck('ten')->join(', ') }}).
                            Địa chỉ, giờ mở cửa, tiện ích, mô tả sửa ở đây sẽ áp dụng cho tất cả.</div>
                    @endif
                </div>
            @endif
            <div class="col-md-6">
                <label class="form-label" for="ten">Tên sân</label>
                <input id="ten" name="ten" value="{{ old('ten', $san->ten) }}" required placeholder="VD: Sân 5A"
                       class="form-control @error('ten') is-invalid @enderror">
                @error('ten') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="biet_danh">Biệt danh <span class="text-muted small">(không bắt buộc)</span></label>
                <input id="biet_danh" name="biet_danh" value="{{ old('biet_danh', $san->biet_danh) }}" placeholder="VD: Sân Cỏ Xanh"
                       class="form-control @error('biet_danh') is-invalid @enderror">
            </div>

            <div class="col-12">
                <label class="form-label d-block">Loại sân</label>
                <div class="btn-group w-100" role="group">
                    @foreach (\App\Models\San::LOAI as $so => $ten)
                        <input type="radio" class="btn-check" name="loai_san" id="loai{{ $so }}" value="{{ $so }}" @checked((int) old('loai_san', $san->loai_san) === $so)>
                        <label class="btn btn-outline-success" for="loai{{ $so }}">{{ $ten }}</label>
                    @endforeach
                </div>
                @error('loai_san') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 phan-chung">
                <label class="form-label" for="tinh_thanh_id">Tỉnh / Thành phố</label>
                <select id="tinh_thanh_id" name="tinh_thanh_id" class="form-select" data-xa-phuong="#xa_phuong_id">
                    <option value="">— Chọn —</option>
                    @foreach ($tinhThanhs as $t)
                        <option value="{{ $t->id }}" @selected((int) $tinhDangChon === $t->id)>{{ $t->ten }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 phan-chung">
                <label class="form-label" for="xa_phuong_id">Xã / Phường</label>
                <select id="xa_phuong_id" name="xa_phuong_id" class="form-select @error('xa_phuong_id') is-invalid @enderror">
                    <option value="">— Chọn tỉnh trước —</option>
                    @foreach ($xaPhuongs as $x)
                        <option value="{{ $x->id }}" @selected((int) old('xa_phuong_id', $san->xa_phuong_id) === $x->id)>{{ $x->ten }}</option>
                    @endforeach
                </select>
                @error('xa_phuong_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12 phan-chung">
                <label class="form-label" for="dia_chi_chi_tiet">Số nhà, đường</label>
                <input id="dia_chi_chi_tiet" name="dia_chi_chi_tiet" value="{{ old('dia_chi_chi_tiet', $san->dia_chi_chi_tiet) }}" required
                       placeholder="VD: 123 Nguyễn Văn Trỗi" class="form-control @error('dia_chi_chi_tiet') is-invalid @enderror">
                @error('dia_chi_chi_tiet') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-6 phan-chung">
                <label class="form-label" for="gio_mo_cua">Mở cửa</label>
                @error('gio_mo_cua') <div class="text-danger small">{{ $message }}</div> @enderror
                <input id="gio_mo_cua" name="gio_mo_cua" value="{{ $chonGio('gio_mo_cua') }}" list="ds-gio" placeholder="VD 06:00" class="form-control @error('gio_mo_cua') is-invalid @enderror">
            </div>
            <div class="col-6 phan-chung">
                <label class="form-label" for="gio_dong_cua">Đóng cửa</label>
                <input id="gio_dong_cua" name="gio_dong_cua" value="{{ $chonGio('gio_dong_cua') }}" list="ds-gio" placeholder="VD 06:00" class="form-control @error('gio_dong_cua') is-invalid @enderror">
                @error('gio_dong_cua') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-12 small text-muted mt-0 phan-chung">Gõ tự do hoặc chọn gợi ý (bước 15 phút). Sân mở <strong>xuyên đêm</strong>: Mở cửa 00:00, Đóng cửa 24:00.
            </div>

            <div class="col-12 phan-chung">
                <label class="form-label d-block">Tiện ích</label>
                @php $daChon = old('tien_ich', $san->tien_ich ?? []); @endphp
                <div class="row row-cols-2 row-cols-md-3 g-1">
                    @foreach (\App\Models\San::TIEN_ICH as $ma => $ten)
                        <div class="col"><div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tien_ich[]" value="{{ $ma }}" id="ti_{{ $ma }}" @checked(in_array($ma, $daChon))>
                            <label class="form-check-label" for="ti_{{ $ma }}">{{ $ten }}</label>
                        </div></div>
                    @endforeach
                </div>
            </div>

            <div class="col-12 phan-chung">
                <label class="form-label" for="mo_ta">Mô tả</label>
                <textarea id="mo_ta" name="mo_ta" rows="3" class="form-control" placeholder="Mặt cỏ, đường đi, lưu ý cho khách...">{{ old('mo_ta', $san->mo_ta) }}</textarea>
            </div>

            <div class="col-12">
                <label class="form-label d-block"><i class="bi bi-diagram-3"></i> Sân ghép <span class="text-muted small">— sân này được ghép từ những sân nhỏ nào?</span></label>
                @php $conDaChon = array_map('intval', old('san_con_ids', $san->exists ? $san->sanCons->pluck('id')->all() : [])); @endphp
                @forelse ($sanCoTheGhep as $s)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="san_con_ids[]" value="{{ $s->id }}" id="ghep{{ $s->id }}" @checked(in_array($s->id, $conDaChon))>
                        <label class="form-check-label" for="ghep{{ $s->id }}">{{ $s->ten }} <span class="text-muted small">({{ \App\Models\San::LOAI[$s->loai_san] }})</span></label>
                    </div>
                @empty
                    <div class="text-muted small">{{ $san->co_so_id ? 'Cơ sở chưa có sân khác để ghép.' : 'Sân ghép chỉ chọn được trong cùng cơ sở — tạo cơ sở xong rồi thêm sân lớn vào sau.' }}</div>
                @endforelse
                <div class="form-text">Khi sân lớn được đặt, các sân con bị khoá cùng giờ và ngược lại. Chỉ chọn sân nhỏ hơn sân này.</div>
                @error('san_con_ids') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="gia_rieng" value="1" id="gia_rieng" @checked(old('gia_rieng', $san->gia_rieng))>
            <label class="form-check-label" for="gia_rieng"><strong>Giá riêng</strong> — sân này không dùng bảng giá chung với các sân cùng loại trong cơ sở</label>
        </div>
        <button class="btn btn-success w-100 mt-3">{{ $san->exists ? 'Lưu thay đổi' : 'Tạo sân và đặt giá' }}</button>
    </form>

    @if ($san->exists)
        {{-- ẢNH --}}
        <div class="card card-body border-0 shadow-sm mb-3">
            <h2 class="h6">Ảnh sân <span class="text-muted small">({{ $san->anhSans->count() }}/{{ \App\Http\Controllers\QuanLy\AnhSanController::TOI_DA }}) — ảnh đầu tiên là ảnh đại diện</span></h2>
            <div class="row g-2 mb-3">
                @forelse ($san->anhSans as $i => $anh)
                    <div class="col-6 col-md-4">
                        <div class="position-relative">
                            <img src="{{ $anh->url() }}" class="img-fluid rounded w-100 object-fit-cover" style="aspect-ratio:16/9" alt="Ảnh sân">
                            @if ($i === 0) <span class="badge bg-success position-absolute top-0 start-0 m-1">Đại diện</span> @endif
                            <div class="position-absolute bottom-0 end-0 m-1 d-flex gap-1">
                                @if ($i > 0)
                                    <form method="POST" action="{{ route('quan-ly.anh-san.len-dau', $anh) }}">@csrf @method('PATCH')
                                        <button class="btn btn-sm btn-light" title="Đặt làm ảnh đại diện"><i class="bi bi-star"></i></button></form>
                                @endif
                                <form method="POST" action="{{ route('quan-ly.anh-san.destroy', $anh) }}" onsubmit="return confirm('Xoá ảnh này?')">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger" title="Xoá"><i class="bi bi-trash"></i></button></form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-muted small">Chưa có ảnh — khách sẽ thấy ảnh mặc định.</div>
                @endforelse
            </div>
            <form method="POST" action="{{ route('quan-ly.san.anh.store', $san) }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="file" name="anh[]" accept="image/*" multiple class="form-control @error('anh') is-invalid @enderror @error('anh.*') is-invalid @enderror">
                <button class="btn btn-outline-success text-nowrap"><i class="bi bi-upload"></i> Tải lên</button>
            </form>
            @error('anh') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @if ($errors->has('anh.*')) <div class="text-danger small mt-1">{{ $errors->first('anh.*') }}</div> @endif
        </div>

        {{-- XOÁ --}}
        <form method="POST" action="{{ route('quan-ly.san.destroy', $san) }}" class="text-end" onsubmit="return confirm('Xoá hẳn sân {{ $san->ten }}? Chỉ xoá được khi sân chưa có lượt đặt.')">
            @csrf @method('DELETE')
            <button class="btn btn-link text-danger">Xoá sân này</button>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const chon = document.getElementById('co_so_id');
    if (! chon) return;
    const doi = () => {
        document.querySelectorAll('.phan-chung, .phan-moi').forEach((el) => { el.classList.toggle('d-none', chon.value !== ''); });
    };
    chon.addEventListener('change', () => {
        location.href = '{{ route('quan-ly.san.create') }}' + (chon.value ? '?co_so=' + chon.value : '');
    });
    doi();
})();
// Chọn tỉnh → nạp danh sách xã/phường
document.querySelectorAll('[data-xa-phuong]').forEach(function (chonTinh) {
    chonTinh.addEventListener('change', async function () {
        const chonXa = document.querySelector(chonTinh.dataset.xaPhuong);
        chonXa.innerHTML = '<option value="">Đang tải...</option>';
        if (!chonTinh.value) { chonXa.innerHTML = '<option value="">— Chọn tỉnh trước —</option>'; return; }
        const res = await fetch('{{ route('dia-chi.xa-phuong') }}?tinh_thanh_id=' + chonTinh.value);
        const ds = await res.json();
        chonXa.innerHTML = '<option value="">— Chọn —</option>' +
            ds.map(x => `<option value="${x.id}">${x.ten.replace(/</g, '&lt;')}</option>`).join('');
    });
});
</script>
@endpush
