@extends('layouts.app')
@section('title', 'Tạo kèo')

@section('content')
@php $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ'; @endphp
<div class="mx-auto" style="max-width: 560px">
    <h1 class="h4 mb-1">Tạo kèo</h1>
    <p class="small text-muted">{{ $don->san->tenHienThi() }} · {{ \Carbon\Carbon::parse($don->ngay)->format('d/m/Y') }} {{ $don->khungGioNhan() }} · tiền sân {{ $tien($don->gia_chot) }}</p>
    @error('keo') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

    <form method="POST" action="{{ $action ?? route('khach.keo.store') }}" class="card card-body border-0 shadow-sm" novalidate>
        @csrf
        <input type="hidden" name="ma_don" value="{{ $don->ma_don }}">
        @php $loai = old('loai', 'doi_dau'); @endphp
        <div class="btn-group w-100 mb-3">
            <input type="radio" class="btn-check" name="loai" value="doi_dau" id="lDoi" @checked($loai === 'doi_dau')>
            <label class="btn btn-outline-danger" for="lDoi">Đội đấu đội</label>
            <input type="radio" class="btn-check" name="loai" value="ghep_le" id="lLe" @checked($loai === 'ghep_le')>
            <label class="btn btn-outline-primary" for="lLe">Ghép lẻ (thiếu người)</label>
        </div>

        <div id="oDoi" class="{{ $loai === 'doi_dau' ? '' : 'd-none' }}">
            @if (! empty($doiCuaToi) && $doiCuaToi->isNotEmpty())
                <label class="form-label">Đội của bạn</label>
                <select name="doi_id" class="form-select mb-3">
                    @foreach ($doiCuaToi as $doi) <option value="{{ $doi->id }}">{{ $doi->ten }}</option> @endforeach
                </select>
            @endif
            <label class="form-label">Đội THUA chịu bao nhiêu tiền sân?</label>
            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach (\App\Models\Keo::TY_LE as $tl => $nhan)
                    <input type="radio" class="btn-check" name="ty_le_thua" value="{{ $tl }}" id="tl{{ $tl }}" @checked((int) old('ty_le_thua', 70) === $tl)>
                    <label class="btn btn-outline-secondary" for="tl{{ $tl }}">{{ $nhan }}</label>
                @endforeach
            </div>
            <div class="small text-muted mb-3">Hoà = 5/5. Đội nhận kèo cọc {{ $tien($cocB) }} (15% tiền sân) vào tài khoản chủ sân.
                Sau trận, đại diện hai đội phải báo kết quả trong 60 phút — quá hạn bên nào chưa báo sẽ bị hạn chế đặt sân và vào kèo.</div>
        </div>

        <div id="oLe" class="row g-2 mb-3 {{ $loai === 'ghep_le' ? '' : 'd-none' }}">
            <div class="col-4"><label class="form-label small">Cần mấy người</label>
                <input type="number" name="so_nguoi_can" min="1" max="20" value="{{ old('so_nguoi_can', 2) }}" class="form-control"></div>
            <div class="col-4"><label class="form-label small">Tiền mỗi người</label>
                <input name="tien_moi_nguoi" inputmode="numeric" value="{{ old('tien_moi_nguoi', 50000) }}" class="form-control o-tien"></div>
            <div class="col-4"><label class="form-label small">Cọc mỗi người</label>
                <input name="tien_coc_ca_nhan" inputmode="numeric" value="{{ old('tien_coc_ca_nhan', 0) }}" class="form-control o-tien"></div>
            <div class="col-12 small text-muted">Cọc 0đ: tài khoản mới (dưới 7 ngày hoặc chưa đá trận nào) sẽ không vào được.</div>
        </div>

        <label class="form-label">Trình độ</label>
        <select name="trinh_do" class="form-select mb-3">
            @foreach (\App\Models\Keo::TRINH_DO as $ma => $ten) <option value="{{ $ma }}" @selected(old('trinh_do', 'trung_binh') === $ma)>{{ $ten }}</option> @endforeach
        </select>
        <label class="form-label">Lời nhắn</label>
        <textarea name="mo_ta" rows="2" class="form-control mb-3" placeholder="VD: đá vui vẻ, áo xanh">{{ old('mo_ta') }}</textarea>
        <div class="form-check mb-3">
            <input type="hidden" name="cong_khai" value="1">
            <input class="form-check-input" type="checkbox" name="cong_khai" value="0" id="rieng">
            <label class="form-check-label" for="rieng"><strong>Kèo riêng</strong> — đã hẹn trước với đội khác: không hiện công khai, gửi link mời cho họ</label>
        </div>
        <button class="btn btn-success w-100">Đăng kèo</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[name=loai]').forEach(r => r.addEventListener('change', () => {
    const doi = document.getElementById('lDoi').checked;
    document.getElementById('oDoi').classList.toggle('d-none', !doi);
    document.getElementById('oLe').classList.toggle('d-none', doi);
}));
</script>
@endpush
