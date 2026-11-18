@extends('layouts.app')
@section('title', 'Nhân viên')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Nhân viên trực sân</h1>
    <a href="{{ route('quan-ly.nhan-vien.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm</a>
</div>
<p class="text-muted small">Nhân viên chỉ được đánh dấu đặt sân tại chỗ và xác nhận đã nhận cọc.</p>

@forelse ($nhanViens as $nv)
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body d-flex justify-content-between align-items-center gap-2">
            <div class="text-truncate">
                <div class="fw-semibold">{{ $nv->name }}
                    @if ($nv->trang_thai === 'bi_khoa') <span class="badge bg-danger">Đã khoá</span> @endif
                    @unless ($nv->hasVerifiedEmail()) <span class="badge bg-warning text-dark">Chưa xác thực email</span> @endunless
                </div>
                <div class="small text-muted">{{ $nv->email }} · {{ $nv->so_dien_thoai }}</div>
            </div>
            <form method="POST" action="{{ route('quan-ly.nhan-vien.khoa', $nv) }}">
                @csrf @method('PATCH')
                <button class="btn btn-sm {{ $nv->trang_thai === 'bi_khoa' ? 'btn-outline-success' : 'btn-outline-danger' }}">
                    {{ $nv->trang_thai === 'bi_khoa' ? 'Mở khoá' : 'Khoá' }}
                </button>
            </form>
        </div>
    </div>
@empty
    <div class="text-center text-muted py-5 bg-white rounded">Chưa có nhân viên nào.</div>
@endforelse
@endsection
