@extends('layouts.app')
@section('title', 'Chủ sân')

@section('content')
@error('thue_bao') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Chủ sân</h1>
    <a href="{{ route('admin.chu-san.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tạo chủ sân</a>
</div>

<form class="mb-3" method="GET">
    <div class="input-group">
        <input name="q" value="{{ $tuKhoa }}" class="form-control" placeholder="Tìm tên, email, SĐT, tên cơ sở">
        <button class="btn btn-secondary"><i class="bi bi-search"></i></button>
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
                <td class="{{ $hs?->het_han_luc?->isFuture() ? '' : 'text-danger fw-semibold' }}">{{ $hs?->het_han_luc?->format('d/m/Y') ?? '—' }}</td>
                <td class="text-center">{{ $cs->nhan_viens_count }}</td>
                <td>
                    @if ($cs->trang_thai === 'bi_khoa') <span class="badge bg-danger">Bị khoá</span>
                    @else <span class="badge bg-success">Hoạt động</span> @endif
                </td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-primary py-0" data-bs-toggle="collapse" data-bs-target="#gh{{ $cs->id }}">Gia hạn</button>
                    @include('admin.nut-khoa', ['u' => $cs])
                </td>
            </tr>
            <tr class="collapse" id="gh{{ $cs->id }}"><td colspan="7" class="bg-light">
                <form method="POST" action="{{ route('admin.chu-san.gia-han', $cs) }}" class="row g-2 align-items-end small">@csrf @method('PATCH')
                    <div class="col-6 col-md-2"><label class="form-label mb-0">Số ngày</label><input name="so_ngay" type="number" min="1" value="30" class="form-control form-control-sm"></div>
                    <div class="col-6 col-md-3"><label class="form-label mb-0">Gói</label><select name="goi_thue_bao_id" class="form-select form-select-sm">
                        @foreach ($gois as $g) <option value="{{ $g->id }}" @selected($hs?->goi_thue_bao_id === $g->id)>{{ $g->ten }}</option> @endforeach</select></div>
                    <div class="col-6 col-md-2"><label class="form-label mb-0">Đã thu (đ)</label><input name="so_tien" inputmode="numeric" value="0" class="form-control form-control-sm o-tien"></div>
                    <div class="col-6 col-md-3"><label class="form-label mb-0">Ghi chú</label><input name="ghi_chu" class="form-control form-control-sm" placeholder="Tiền mặt / tặng"></div>
                    <div class="col-12 col-md-2"><button class="btn btn-sm btn-primary w-100">Cộng hạn</button></div>
                </form>
            </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Chưa có chủ sân.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $chuSans->links() }}</div>
@endsection
