@extends('layouts.app')
@section('title', 'Thuê bao')

@section('content')
<h1 class="h4 mb-3">Thuê bao & doanh thu</h1>
@error('thue_bao') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
<div class="row g-2 mb-3">
    @foreach ([
        ['Doanh thu tháng này', number_format($thongKe['thang_nay'], 0, ',', '.').'đ', 'success'],
        ['Doanh thu năm nay', number_format($thongKe['nam_nay'], 0, ',', '.').'đ', 'success'],
        ['Đơn chờ xác nhận', $thongKe['cho'], 'warning'],
        ['Chủ sân còn hạn', $thongKe['con_han'], 'primary'],
        ['Sắp hết hạn (7 ngày)', $thongKe['sap_het'], 'warning'],
        ['Đã hết hạn', $thongKe['het_han'], 'danger'],
    ] as [$nhan, $so, $mau])
        <div class="col-6 col-md-4 col-lg-2"><div class="card border-0 shadow-sm h-100"><div class="card-body p-2">
            <div class="small text-muted">{{ $nhan }}</div><div class="fw-bold text-{{ $mau }}">{{ $so }}</div></div></div></div>
    @endforeach
</div>

<ul class="nav nav-pills mb-2 small">
    @foreach (['cho_thanh_toan' => 'Chờ xác nhận', 'da_thanh_toan' => 'Đã thanh toán', 'da_huy' => 'Đã huỷ', 'tat_ca' => 'Tất cả'] as $k => $v)
        <li class="nav-item"><a class="nav-link py-1 {{ $loc === $k ? 'active' : '' }}" href="?trang_thai={{ $k }}">{{ $v }}</a></li>
    @endforeach
</ul>
<div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-sm align-middle small mb-0">
        <thead class="table-light"><tr><th>#</th><th>Nội dung CK</th><th>Chủ sân</th><th>Gói</th><th>Thời gian</th><th class="text-end">Số tiền</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        @forelse ($dons as $tb)
            <tr><td>{{ $tb->id }}</td><td><code>{{ $tb->noiDungChuyenKhoan() }}</code></td>
                <td>{{ $tb->chuSan->hoSoChuSan?->ten_co_so }}<div class="text-muted">{{ $tb->chuSan->name }} · {{ $tb->chuSan->so_dien_thoai }}</div></td>
                <td>{{ $tb->goiThueBao->ten }} · {{ $tb->so_thang }} th</td>
                <td>{{ $tb->tu_ngay->format('d/m/Y') }} → {{ $tb->den_ngay->format('d/m/Y') }}</td>
                <td class="text-end fw-semibold">{{ number_format($tb->so_tien, 0, ',', '.') }}đ</td>
                <td>{{ \App\Models\ThueBao::TRANG_THAI[$tb->trang_thai] }}@if ($tb->ghi_chu)<div class="text-muted">{{ $tb->ghi_chu }}</div>@endif</td>
                <td class="text-end text-nowrap">
                    @if ($tb->trang_thai === 'cho_thanh_toan')
                        <form method="POST" action="{{ route('admin.thue-bao.xac-nhan', $tb) }}" class="d-inline" onsubmit="return confirm('Đã nhận {{ number_format($tb->so_tien, 0, ',', '.') }}đ với nội dung {{ $tb->noiDungChuyenKhoan() }}?')">@csrf @method('PATCH')
                            <button class="btn btn-sm btn-success py-0">Đã nhận tiền</button></form>
                        <form method="POST" action="{{ route('admin.thue-bao.huy', $tb) }}" class="d-inline">@csrf @method('PATCH')
                            <button class="btn btn-sm btn-secondary py-0">Huỷ</button></form>
                    @endif
                </td></tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-3">Không có đơn.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $dons->links() }}</div>
@endsection
