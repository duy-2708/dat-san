@extends('layouts.app')
@section('title', 'Giải đấu của tôi')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Giải đấu của tôi</h1>
    @if (auth()->user()->laChuSan()) <a href="{{ route('quan-ly.giai-dau.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tạo giải</a> @endif
</div>
<div class="list-group shadow-sm">
    @forelse ($giais as $g)
        <a href="{{ route('quan-ly.giai-dau.show', $g) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
            <span><i class="bi bi-trophy text-warning"></i> {{ $g->ten }} <span class="small text-muted">· {{ $g->doi_tham_gias_count }} đăng ký</span></span>
            <span class="badge bg-secondary">{{ \App\Models\GiaiDau::TRANG_THAI[$g->trang_thai] }}</span>
        </a>
    @empty
        <div class="list-group-item text-muted">Chưa có giải nào.</div>
    @endforelse
</div>
@endsection
