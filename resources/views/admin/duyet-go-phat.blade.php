@extends('layouts.app')
@section('title', 'Duyệt gỡ phạt')

@section('content')
<h1 class="h4 mb-1">Duyệt đề nghị gỡ phạt</h1>
<p class="small text-muted">Chủ sân không tự cộng lại uy tín hay gỡ hạn chế được nữa — mọi đề nghị phải qua admin để tránh chủ sân gỡ phạt cho người quen.</p>
@error('yeu_cau') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

<h2 class="h6 mt-3">Chờ duyệt ({{ $choDuyet->count() }})</h2>
@forelse ($choDuyet as $y)
    <div class="card border-0 shadow-sm mb-2"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <span class="badge bg-warning text-dark">{{ \App\Models\YeuCauGoPhat::LOAI[$y->loai] }}</span>
                <strong>{{ $y->user->name }}</strong> <span class="text-muted small">{{ $y->user->email }} · uy tín {{ $y->user->diem_uy_tin }}</span>
                <div class="small">Chủ sân đề nghị: <strong>{{ $y->chuSan->hoSoChuSan?->ten_co_so ?? $y->chuSan->name }}</strong> · {{ $y->created_at->format('d/m/Y H:i') }}
                    @if ($y->thamGia?->keo) · <a href="{{ route('keo.show', $y->thamGia->keo) }}" target="_blank">Kèo #{{ $y->thamGia->keo->id }}</a> @endif</div>
                <div class="small text-muted">Lý do: {{ $y->ly_do ?: '(không ghi)' }}</div>
            </div>
            <div class="d-flex gap-1 align-items-start">
                <form method="POST" action="{{ route('admin.duyet-go-phat.xu-ly', $y) }}">@csrf @method('PATCH')
                    <input type="hidden" name="dong_y" value="1"><button class="btn btn-sm btn-success">Duyệt</button></form>
                <form method="POST" action="{{ route('admin.duyet-go-phat.xu-ly', $y) }}" class="d-flex gap-1">@csrf @method('PATCH')
                    <input type="hidden" name="dong_y" value="0">
                    <input name="ghi_chu_admin" class="form-control form-control-sm" placeholder="Lý do từ chối">
                    <button class="btn btn-sm btn-danger">Từ chối</button></form>
            </div>
        </div>
    </div></div>
@empty
    <div class="text-muted small bg-white rounded p-3">Không có đề nghị nào.</div>
@endforelse

<h2 class="h6 mt-4">Đã xử lý gần đây</h2>
<div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-sm small mb-0">
        <thead class="table-light"><tr><th>Lúc</th><th>Loại</th><th>Người được</th><th>Chủ sân</th><th>Kết quả</th></tr></thead>
        <tbody>
        @forelse ($daXuLy as $y)
            <tr><td>{{ $y->xu_ly_luc?->format('d/m/Y H:i') }}</td><td>{{ \App\Models\YeuCauGoPhat::LOAI[$y->loai] }}</td><td>{{ $y->user->name }}</td>
                <td>{{ $y->chuSan->name }}</td>
                <td>{!! $y->trang_thai === 'da_duyet' ? '<span class="text-success">Đã duyệt</span>' : '<span class="text-danger">Từ chối</span>' !!} {{ $y->ghi_chu_admin }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-muted text-center">Chưa có.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
