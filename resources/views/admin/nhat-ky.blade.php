@extends('layouts.app')
@section('title', 'Nhật ký hệ thống')

@section('content')
<h1 class="h4 mb-2">Nhật ký hệ thống</h1>
<p class="small text-muted">Mọi thao tác ghi (đặt sân, xác nhận cọc, khoá tài khoản, đổi gói...) và đăng nhập. Giữ {{ config('bao_mat.nhat_ky_giu_ngay') }} ngày.
    Dùng để tra soát khi có tranh chấp hoặc nghi bị xâm nhập.</p>
<form class="row g-2 mb-3">
    <div class="col-12 col-md-5"><input name="tim" value="{{ $loc['tim'] ?? '' }}" class="form-control" placeholder="Email, tên, IP hoặc mã (VD: datSan#12)"></div>
    <div class="col-6 col-md-3">
        <select name="hanh_dong" class="form-select">
            <option value="">Mọi hành động</option>
            @foreach (['dang_nhap_sai' => 'Đăng nhập sai', 'bi_chan_dang_nhap' => 'Bị chặn đăng nhập', 'dang_nhap' => 'Đăng nhập', 'admin.' => 'Thao tác admin', 'quan-ly.' => 'Thao tác chủ sân/nhân viên'] as $k => $v)
                <option value="{{ $k }}" @selected(($loc['hanh_dong'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-4 col-md-3"><input type="date" name="ngay" value="{{ $loc['ngay'] ?? '' }}" class="form-control"></div>
    <div class="col-2 col-md-1"><button class="btn btn-success w-100"><i class="bi bi-search"></i></button></div>
</form>
<div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-sm align-middle mb-0 small">
        <thead class="table-light"><tr><th>Thời gian</th><th>Người</th><th>Hành động</th><th>Chi tiết</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($ds as $n)
            <tr class="{{ in_array($n->hanh_dong, ['dang_nhap_sai', 'bi_chan_dang_nhap']) ? 'table-warning' : '' }}">
                <td class="text-nowrap">{{ $n->created_at->format('d/m/Y H:i:s') }}</td>
                <td>{{ $n->user?->name ?? '—' }}<div class="text-muted">{{ $n->user?->email }}</div></td>
                <td>{{ $n->nhan() }}</td>
                <td class="text-break">{{ $n->chi_tiet }}</td>
                <td class="text-nowrap">{{ $n->ip }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-3">Không có bản ghi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $ds->links() }}</div>
@endsection
