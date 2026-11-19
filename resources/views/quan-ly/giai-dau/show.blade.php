@extends('layouts.app')
@section('title', 'Quản lý '.$g->ten)

@section('content')
@php $daDuyet = $g->doiThamGias->where('trang_thai', 'da_duyet'); @endphp
<div class="mx-auto" style="max-width: 860px">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <a href="{{ route('quan-ly.giai-dau.index') }}" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i></a>
        <h1 class="h5 mb-0">{{ $g->ten }}</h1>
        <span class="badge bg-secondary">{{ \App\Models\GiaiDau::TRANG_THAI[$g->trang_thai] }}</span>
        <a href="{{ route('giai-dau.show', $g) }}" target="_blank" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-eye"></i> Trang công khai</a>
    </div>
    @if ($errors->any()) <div class="alert alert-danger py-2">{{ $errors->first() }}</div> @endif

    {{-- Đăng ký --}}
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h2 class="h6">Đội đăng ký ({{ $daDuyet->count() }}/{{ $g->so_doi_toi_da }} đã duyệt)</h2>
        @forelse ($g->doiThamGias as $dk)
            <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom py-1 small gap-2">
                <span><img src="{{ $dk->doi->logoUrl() }}" width="24" height="24" class="rounded-circle" alt=""> <strong>{{ $dk->doi->ten }}</strong>
                    · {{ $dk->cauThus->pluck('name')->join(', ') }}</span>
                @if ($dk->trang_thai === 'cho_duyet' && $g->trang_thai === 'mo_dang_ky')
                    <span class="d-flex gap-1">
                        <form method="POST" action="{{ route('quan-ly.giai-dau.duyet', $dk) }}">@csrf @method('PATCH')<input type="hidden" name="dong_y" value="1"><button class="btn btn-sm btn-success py-0">Duyệt</button></form>
                        <form method="POST" action="{{ route('quan-ly.giai-dau.duyet', $dk) }}">@csrf @method('PATCH')<input type="hidden" name="dong_y" value="0"><button class="btn btn-sm btn-outline-secondary py-0">Từ chối</button></form>
                    </span>
                @else
                    <span class="badge bg-{{ $dk->trang_thai === 'da_duyet' ? 'success' : ($dk->trang_thai === 'tu_choi' ? 'secondary' : 'warning text-dark') }}">{{ ['cho_duyet' => 'Chờ duyệt', 'da_duyet' => 'Đã duyệt', 'tu_choi' => 'Từ chối'][$dk->trang_thai] }}</span>
                @endif
            </div>
        @empty
            <div class="small text-muted">Chưa có đội đăng ký. Gửi link trang công khai cho các đội.</div>
        @endforelse
        @if ($g->trang_thai === 'mo_dang_ky')
            <form method="POST" action="{{ route('quan-ly.giai-dau.dong-dang-ky', $g) }}" class="mt-3" onsubmit="return confirm('Đóng đăng ký và bắt đầu giải?')">@csrf @method('PATCH')
                <button class="btn btn-primary btn-sm">Đóng đăng ký & bắt đầu giải {{ $g->the_thuc === 'vong_tron' ? '(tự xếp lịch vòng tròn)' : '' }}</button></form>
        @endif
    </div></div>

    {{-- Thêm trận --}}
    @if ($g->trang_thai === 'dang_dien_ra' && $daDuyet->count() >= 2)
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">Thêm trận {{ $g->the_thuc === 'loai_truc_tiep' ? '(tứ kết, bán kết, chung kết...)' : '' }}</h2>
            <form method="POST" action="{{ route('quan-ly.giai-dau.tran', $g) }}" class="row g-2">@csrf
                <div class="col-6 col-md-3"><select name="doi_nha_id" class="form-select form-select-sm">@foreach ($daDuyet as $dk) <option value="{{ $dk->doi_id }}">{{ $dk->doi->ten }}</option> @endforeach</select></div>
                <div class="col-6 col-md-3"><select name="doi_khach_id" class="form-select form-select-sm">@foreach ($daDuyet as $dk) <option value="{{ $dk->doi_id }}" @selected($loop->index === 1)>{{ $dk->doi->ten }}</option> @endforeach</select></div>
                <div class="col-6 col-md-2"><input name="vong" class="form-control form-control-sm" placeholder="Vòng" value="Chung kết"></div>
                <div class="col-6 col-md-2"><input type="datetime-local" name="thoi_gian" class="form-control form-control-sm"></div>
                <div class="col-8 col-md-1"><select name="san_id" class="form-select form-select-sm"><option value="">Sân</option>@foreach ($sans as $s) <option value="{{ $s->id }}">{{ $s->ten }}</option> @endforeach</select></div>
                <div class="col-4 col-md-1"><button class="btn btn-sm btn-success w-100">+</button></div>
            </form>
        </div></div>
    @endif

    @include('giai-dau._bang', ['choSua' => true])

    {{-- Kết thúc --}}
    @if ($g->trang_thai === 'dang_dien_ra')
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">Kết thúc giải — chốt thứ hạng & danh hiệu</h2>
            <form method="POST" action="{{ route('quan-ly.giai-dau.ket-thuc', $g) }}" onsubmit="return confirm('Kết thúc giải? Thành tích sẽ hiện lên hồ sơ cầu thủ.')">@csrf @method('PATCH')
                @php $goiY = $bxh->pluck('dk.id')->flip(); @endphp
                @foreach ($daDuyet as $dk)
                    @php $hang = isset($goiY[$dk->id]) && $g->the_thuc === 'vong_tron' ? $goiY[$dk->id] + 1 : null; @endphp
                    <div class="row g-1 align-items-center mb-1 small">
                        <div class="col-5">{{ $dk->doi->ten }}</div>
                        <div class="col-3"><input type="number" min="1" name="xep_hang[{{ $dk->id }}][thu_hang]" value="{{ $hang }}" class="form-control form-control-sm" placeholder="Hạng"></div>
                        <div class="col-4"><input name="xep_hang[{{ $dk->id }}][danh_hieu]" value="{{ ['1' => 'Vô địch', '2' => 'Á quân', '3' => 'Hạng ba'][$hang] ?? '' }}" class="form-control form-control-sm" placeholder="Danh hiệu"></div>
                    </div>
                @endforeach
                <button class="btn btn-dark btn-sm mt-2">Kết thúc giải</button>
            </form>
        </div></div>
    @endif
</div>
@endsection
