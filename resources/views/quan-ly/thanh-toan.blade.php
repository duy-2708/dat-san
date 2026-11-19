@extends('layouts.app')
@section('title', 'Cài đặt thanh toán')

@section('content')
<div class="mx-auto" style="max-width: 640px">
    <h1 class="h4 mb-1">Cài đặt nhận cọc</h1>
    <p class="text-muted small">Tiền cọc của khách chuyển <strong>thẳng vào tài khoản của bạn</strong>. Website tạo mã VietQR có sẵn số tiền + mã đơn;
        bạn/nhân viên xem app ngân hàng rồi bấm "Đã nhận cọc".</p>

    <div class="btn-group w-100 mb-3">
        <button class="btn btn-success" disabled><i class="bi bi-qr-code"></i> VietQR tài khoản riêng</button>
        <button class="btn btn-outline-secondary" disabled title="Có ở giai đoạn 5">Cổng tự động (PayOS) — sắp có</button>
    </div>

    <form method="POST" action="{{ route('quan-ly.thanh-toan.update') }}" class="card card-body border-0 shadow-sm" novalidate>
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="ngan_hang_bin">Ngân hàng</label>
                <select id="ngan_hang_bin" name="ngan_hang_bin" class="form-select @error('ngan_hang_bin') is-invalid @enderror">
                    <option value="">— Chọn ngân hàng —</option>
                    @foreach (\App\Support\VietQr::NGAN_HANG as $bin => $ten)
                        <option value="{{ $bin }}" @selected(old('ngan_hang_bin', $hoSo->ngan_hang_bin) === (string) $bin)>{{ $ten }}</option>
                    @endforeach
                </select>
                @error('ngan_hang_bin') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="so_tai_khoan">Số tài khoản</label>
                <input id="so_tai_khoan" name="so_tai_khoan" inputmode="numeric" value="{{ old('so_tai_khoan', $hoSo->so_tai_khoan) }}"
                       class="form-control @error('so_tai_khoan') is-invalid @enderror">
                @error('so_tai_khoan') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="ten_chu_tai_khoan">Tên chủ tài khoản</label>
                <input id="ten_chu_tai_khoan" name="ten_chu_tai_khoan" value="{{ old('ten_chu_tai_khoan', $hoSo->ten_chu_tai_khoan) }}"
                       class="form-control text-uppercase @error('ten_chu_tai_khoan') is-invalid @enderror">
                @error('ten_chu_tai_khoan') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="sdt_lien_he">SĐT liên hệ sân</label>
                <input id="sdt_lien_he" name="sdt_lien_he" value="{{ old('sdt_lien_he', $hoSo->sdt_lien_he) }}" class="form-control">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label" for="phut_giu_cho">Giữ chỗ (phút)</label>
                <input id="phut_giu_cho" type="number" name="phut_giu_cho" min="5" max="60" value="{{ old('phut_giu_cho', $hoSo->phut_giu_cho) }}"
                       class="form-control @error('phut_giu_cho') is-invalid @enderror">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label" for="gio_huy_hoan_coc">Huỷ trước (giờ) được hoàn cọc</label>
                <input id="gio_huy_hoan_coc" type="number" name="gio_huy_hoan_coc" min="0" max="168" value="{{ old('gio_huy_hoan_coc', $hoSo->gio_huy_hoan_coc) }}"
                       class="form-control @error('gio_huy_hoan_coc') is-invalid @enderror">
            </div>
        </div>
        <button class="btn btn-success w-100 mt-3">Lưu</button>
    </form>

    @if ($qrThu)
        <div class="card card-body border-0 shadow-sm mt-3 text-center">
            <h2 class="h6">Kiểm tra mã QR</h2>
            <p class="small text-muted">Quét bằng app ngân hàng: phải hiện đúng tên <strong>{{ $hoSo->ten_chu_tai_khoan }}</strong> và 10.000đ. Không cần chuyển.</p>
            <div class="mx-auto" style="max-width: 200px">{!! $qrThu !!}</div>
        </div>
    @endif
</div>
@endsection
