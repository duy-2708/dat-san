@extends('layouts.app')
@section('title', 'Lịch '.$san->ten)

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <a href="{{ route('quan-ly.san.index') }}" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i></a>
    <h1 class="h4 mb-0">Lịch {{ $san->ten }}</h1>
    <a href="{{ route('san.show', $san) }}" target="_blank" class="btn btn-sm btn-secondary ms-auto"><i class="bi bi-eye"></i> Khách thấy gì</a>
</div>
@if ($errors->any()) <div class="alert alert-danger py-2">{{ $errors->first() }}</div> @endif

<div class="card border-0 shadow-sm"><div class="card-body">
    @include('partials.lich-tuan', ['cheDo' => 'quan-ly', 'url' => route('quan-ly.san.lich', $san)])
</div></div>

{{-- Modal đặt tại chỗ --}}
<div class="modal fade" id="modalTaiCho" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('quan-ly.san.dat-tai-cho', $san) }}" class="modal-content" novalidate>
            @csrf
            <div class="modal-header">
                <h2 class="modal-title h6">Đặt tại chỗ — <span id="tcNhan"></span></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="ngay" id="tcNgay" value="{{ old('ngay') }}">
                <input type="hidden" name="khung_gio_id" id="tcKhung" value="{{ old('khung_gio_id') }}">
                <div class="mb-2"><label class="form-label small">Đá bao lâu (tính từ giờ bắt đầu khung)</label>
                    <select name="thoi_luong" id="tcThoiLuong" class="form-select"></select></div>
                <div class="mb-2"><label class="form-label small">Tên khách</label>
                    <input name="ten" value="{{ old('ten') }}" class="form-control" required></div>
                <div class="mb-2"><label class="form-label small">Số điện thoại</label>
                    <input name="sdt" type="tel" inputmode="numeric" value="{{ old('sdt') }}" class="form-control" required></div>
                <div class="mb-2"><label class="form-label small">Ghi chú</label>
                    <input name="ghi_chu" value="{{ old('ghi_chu') }}" class="form-control"></div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="da_thu_coc" value="1" id="tcCoc">
                    <label class="form-check-label" for="tcCoc">Đã thu cọc (tiền mặt / chuyển khoản)</label>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-success w-100">Xác nhận đặt</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('modalTaiCho').addEventListener('show.bs.modal', function (e) {
    const b = e.relatedTarget;
    if (!b) return;
    document.getElementById('tcNgay').value = b.dataset.ngay;
    document.getElementById('tcKhung').value = b.dataset.khung;
    document.getElementById('tcNhan').textContent = b.dataset.nhan;
    const chon = document.getElementById('tcThoiLuong');
    chon.innerHTML = '';
    JSON.parse(b.dataset.thoiLuong || '[]').forEach((t) => chon.add(new Option(t.nhan, t.p, t.chon, t.chon)));
});
</script>
@endpush
