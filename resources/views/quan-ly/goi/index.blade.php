@extends('layouts.app')
@section('title', 'Gói dịch vụ')

@section('content')
<div class="mx-auto" style="max-width: 900px">
    <h1 class="h4 mb-3">Gói dịch vụ</h1>
    @error('thue_bao') <div class="alert alert-danger">{{ $message }}</div> @enderror

    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        @if ($mienPhiDen)
            <div class="alert alert-success mb-2"><i class="bi bi-gift"></i> Hệ thống đang <strong>miễn phí</strong> tới hết ngày {{ $mienPhiDen->format('d/m/Y') }}:
                dùng đầy đủ, không giới hạn số sân và nhân viên.</div>
        @endif
        <div class="row small g-2">
            <div class="col-6 col-md-3"><div class="text-muted">Gói hiện tại</div><strong>{{ $hoSo->goiThueBao?->ten ?? 'Chưa có' }}</strong></div>
            <div class="col-6 col-md-3"><div class="text-muted">Hạn dùng</div>
                @if ($hoSo->het_han_luc?->isFuture())
                    <strong>{{ $hoSo->het_han_luc->format('d/m/Y') }}</strong> <span class="text-muted">(còn {{ $hoSo->soNgayConLai() }} ngày)</span>
                @else <strong class="text-danger">{{ $hoSo->het_han_luc ? 'Hết hạn '.$hoSo->het_han_luc->format('d/m/Y') : 'Chưa kích hoạt' }}</strong> @endif
            </div>
            <div class="col-6 col-md-3"><div class="text-muted">Sân</div><strong>{{ $soSan }}</strong>@if (! $mienPhiDen && $hoSo->goiThueBao) / {{ $hoSo->goiThueBao->so_san_toi_da }} @endif</div>
            <div class="col-6 col-md-3"><div class="text-muted">Nhân viên</div><strong>{{ $soNhanVien }}</strong>@if (! $mienPhiDen && $hoSo->goiThueBao) / {{ $hoSo->goiThueBao->so_nhan_vien_toi_da }} @endif</div>
        </div>
    </div></div>

    <h2 class="h6">Mua / gia hạn</h2>
    <p class="small text-muted">Gia hạn khi còn hạn thì thời gian được <strong>cộng nối tiếp</strong>, không mất ngày. Chuyển khoản xong, quản trị viên xác nhận trong giờ hành chính.</p>
    <div class="row g-3 mb-4">
        @foreach ($gois as $g)
            <div class="col-12 col-md-4">
                <form method="POST" action="{{ route('quan-ly.goi.store') }}" class="card h-100 border-0 shadow-sm {{ $hoSo->goi_thue_bao_id === $g->id ? 'border border-success' : '' }}">@csrf
                    <input type="hidden" name="goi_thue_bao_id" value="{{ $g->id }}">
                    <div class="card-body d-flex flex-column">
                        <div class="fw-semibold">{{ $g->ten }} @if ($hoSo->goi_thue_bao_id === $g->id) <span class="badge bg-success">đang dùng</span> @endif</div>
                        <div class="fs-4 fw-bold text-success">{{ number_format($g->gia_thang, 0, ',', '.') }}đ<span class="fs-6 text-muted fw-normal">/tháng</span></div>
                        <ul class="small ps-3 mb-2">
                            <li>Tối đa {{ $g->so_san_toi_da }} sân</li>
                            <li>Tối đa {{ $g->so_nhan_vien_toi_da }} nhân viên</li>
                            <li>{{ $g->cho_phep_cong_tu_dong ? 'Xác nhận cọc tự động (PayOS)' : 'Xác nhận cọc thủ công (VietQR)' }}</li>
                        </ul>
                        @if ($g->mo_ta) <div class="small text-muted mb-2">{{ $g->mo_ta }}</div> @endif
                        <div class="mt-auto d-flex gap-2">
                            <select name="so_thang" class="form-select form-select-sm">
                                @foreach (\App\Services\ThueBaoService::SO_THANG as $t)
                                    <option value="{{ $t }}">{{ $t }} tháng — {{ number_format($g->gia_thang * $t, 0, ',', '.') }}đ</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-success text-nowrap">Thanh toán</button>
                        </div>
                    </div>
                </form>
            </div>
        @endforeach
    </div>

    <h2 class="h6">Lịch sử</h2>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-sm align-middle small mb-0">
            <thead class="table-light"><tr><th>#</th><th>Gói</th><th>Thời gian</th><th class="text-end">Số tiền</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
            @forelse ($lichSu as $tb)
                <tr><td>{{ $tb->id }}</td><td>{{ $tb->goiThueBao->ten }} · {{ $tb->so_thang }} th</td>
                    <td>{{ $tb->tu_ngay->format('d/m/Y') }} → {{ $tb->den_ngay->format('d/m/Y') }}</td>
                    <td class="text-end">{{ number_format($tb->so_tien, 0, ',', '.') }}đ</td>
                    <td>{{ \App\Models\ThueBao::TRANG_THAI[$tb->trang_thai] }}</td>
                    <td>@if ($tb->trang_thai === 'cho_thanh_toan') <a href="{{ route('quan-ly.goi.show', $tb) }}">Mã QR</a> @endif</td></tr>
            @empty
                <tr><td colspan="6" class="text-muted text-center py-3">Chưa có giao dịch.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
