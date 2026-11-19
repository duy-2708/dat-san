@extends('layouts.app')
@section('title', 'Cài đặt hệ thống')

@section('content')
<div class="mx-auto" style="max-width: 560px">
    <h1 class="h4 mb-3">Cài đặt hệ thống</h1>
    <form method="POST" action="{{ route('admin.cai-dat.update') }}" class="card card-body border-0 shadow-sm" novalidate>@csrf @method('PUT')
        @if ($errors->any()) <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div> @endif
        <h2 class="h6">Thời gian miễn phí</h2>
        <label class="form-label small">Miễn phí đến hết ngày (để trống = không miễn phí)</label>
        <input type="date" name="mien_phi_den" value="{{ old('mien_phi_den', $cd['mien_phi_den']) }}" class="form-control mb-1">
        <div class="form-text mb-3">Trong thời gian này mọi chủ sân dùng đầy đủ, <strong>không giới hạn</strong> số sân và nhân viên, không bị khoá vì hết hạn gói.
            Hết ngày này, chủ sân nào chưa có gói còn hạn sẽ bị ẩn sân và chỉ vào được trang Gói dịch vụ.</div>

        <h2 class="h6">Tài khoản nhận tiền thuê bao (VietQR)</h2>
        <label class="form-label small">Ngân hàng</label>
        <select name="tk_bin" class="form-select mb-2">
            <option value="">— Chọn —</option>
            @foreach (\App\Support\VietQr::NGAN_HANG as $bin => $ten)
                <option value="{{ $bin }}" @selected((string) old('tk_bin', $cd['tk_bin']) === (string) $bin)>{{ $ten }}</option>
            @endforeach
        </select>
        <label class="form-label small">Số tài khoản</label>
        <input name="tk_so" value="{{ old('tk_so', $cd['tk_so']) }}" class="form-control mb-2">
        <label class="form-label small">Tên chủ tài khoản</label>
        <input name="tk_ten" value="{{ old('tk_ten', $cd['tk_ten']) }}" class="form-control mb-2">
        <label class="form-label small">SĐT hỗ trợ (hiện cho chủ sân)</label>
        <input name="sdt_ho_tro" value="{{ old('sdt_ho_tro', $cd['sdt_ho_tro']) }}" class="form-control mb-3">
        <button class="btn btn-success">Lưu</button>
    </form>
</div>
@endsection
