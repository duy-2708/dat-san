@extends('layouts.app')
@section('title', 'Chủ sân')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Chủ sân</h1>
    <a href="{{ route('admin.chu-san.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tạo chủ sân</a>
</div>

<form class="mb-3" method="GET">
    <div class="input-group">
        <input name="q" value="{{ $tuKhoa }}" class="form-control" placeholder="Tìm tên, email, SĐT, tên cơ sở">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr><th>Cơ sở</th><th>Chủ sân</th><th>Gói</th><th>Hết hạn</th><th class="text-center">NV</th><th>Trạng thái</th><th></th></tr>
        </thead>
        <tbody>
        @forelse ($chuSans as $cs)
            @php($hs = $cs->hoSoChuSan)
            <tr>
                <td class="fw-semibold">{{ $hs?->ten_co_so }}</td>
                <td>{{ $cs->name }}<div class="small text-muted">{{ $cs->email }} · {{ $cs->so_dien_thoai }}</div></td>
                <td>{{ $hs?->goiThueBao?->ten ?? 'Dùng thử' }}</td>
                <td class="{{ $hs?->conHan() ? '' : 'text-danger fw-semibold' }}">{{ $hs?->het_han_luc?->format('d/m/Y') ?? '—' }}</td>
                <td class="text-center">{{ $cs->nhan_viens_count }}</td>
                <td>
                    @if ($cs->trang_thai === 'bi_khoa') <span class="badge bg-danger">Bị khoá</span>
                    @else <span class="badge bg-success">Hoạt động</span> @endif
                </td>
                <td class="text-end">@include('admin.nut-khoa', ['u' => $cs])</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Chưa có chủ sân.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $chuSans->links() }}</div>
@endsection
