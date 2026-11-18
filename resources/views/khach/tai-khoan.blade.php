@extends('layouts.app')
@section('title', 'Tài khoản của tôi')

@section('content')
@php($u = auth()->user())
<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <img src="{{ $u->anh_dai_dien ?: 'https://ui-avatars.com/api/?background=198754&color=fff&name='.urlencode($u->name) }}"
                     class="rounded-circle mb-2" width="96" height="96" alt="Ảnh đại diện" style="object-fit:cover">
                <h1 class="h5 mb-0">{{ $u->name }}</h1>
                <div class="text-muted small">{{ $u->email }} · {{ $u->so_dien_thoai }}</div>
                <hr>
                <div class="d-flex justify-content-around">
                    <div><div class="fw-bold fs-5">{{ $u->so_tran_tham_gia }}</div><div class="small text-muted">Trận</div></div>
                    <div><div class="fw-bold fs-5">{{ $u->diem_uy_tin }}</div><div class="small text-muted">Uy tín</div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6">Lịch đặt sân của tôi</h2>
                <p class="text-muted mb-0">Sẽ có ở giai đoạn 3.</p>
            </div>
        </div>
    </div>
</div>
@endsection
