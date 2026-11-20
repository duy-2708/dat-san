@extends('layouts.app')
@section('title', 'Thông báo')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-bell"></i> Thông báo</h1>
    <form method="POST" action="{{ route('thong-bao.doc-het') }}">@csrf
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-all"></i> Đánh dấu đọc hết</button></form>
</div>
<div class="list-group shadow-sm">
    @forelse ($ds as $tb)
        <a href="{{ route('thong-bao.mo', $tb->id) }}" class="list-group-item list-group-item-action d-flex gap-3 {{ $tb->read_at ? '' : 'list-group-item-warning' }}">
            <i class="bi bi-{{ $tb->data['icon'] ?? 'bell' }} fs-5 text-success"></i>
            <div class="flex-grow-1">
                <div class="fw-semibold">{{ $tb->data['tieu_de'] ?? '' }} @unless ($tb->read_at) <span class="badge bg-danger">Mới</span> @endunless</div>
                <div class="small">{{ $tb->data['noi_dung'] ?? '' }}</div>
                <div class="small text-muted">{{ $tb->created_at->format('H:i d/m/Y') }}</div>
            </div>
        </a>
    @empty
        <div class="list-group-item text-muted text-center py-4">Chưa có thông báo nào.</div>
    @endforelse
</div>
<div class="mt-3">{{ $ds->links() }}</div>
@endsection
