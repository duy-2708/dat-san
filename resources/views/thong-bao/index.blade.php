@extends('layouts.app')
@section('title', 'Thông báo')

@section('content')
<div class="mx-auto" style="max-width: 760px">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0"><i class="bi bi-bell"></i> Thông báo</h1>
        <form method="POST" action="{{ route('thong-bao.doc-het') }}">@csrf
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-all"></i> Đánh dấu đọc hết</button></form>
    </div>
    <form class="d-flex flex-wrap gap-1 align-items-center mb-2">
        @foreach (['' => 'Tất cả', 'can' => 'Cần xử lý', 'don' => 'Đơn đặt', 'keo' => 'Kèo', 'khac' => 'Khác'] as $ma => $nhan)
            <a href="{{ route('thong-bao.index', array_filter(['loc' => $ma, 'ngay' => $loc['ngay'] ?? null])) }}"
               class="btn btn-sm rounded-pill {{ ($loc['loc'] ?? '') === $ma ? 'btn-success' : 'btn-outline-secondary' }}">{{ $nhan }}</a>
        @endforeach
        <input type="hidden" name="loc" value="{{ $loc['loc'] ?? '' }}">
        <input type="date" name="ngay" value="{{ $loc['ngay'] ?? '' }}" class="form-control form-control-sm ms-md-auto" style="width: 150px" onchange="this.form.submit()">
        @if ($loc['ngay'] ?? null) <a href="{{ route('thong-bao.index', array_filter(['loc' => $loc['loc'] ?? null])) }}" class="btn btn-sm btn-link">Bỏ lọc ngày</a> @endif
    </form>
    <div class="bg-white rounded shadow-sm overflow-hidden">
        @forelse ($ds as $t)
            @include('thong-bao._muc', ['t' => $t])
        @empty
            <div class="text-muted text-center py-4">Không có thông báo.</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $ds->links() }}</div>
</div>
@endsection
