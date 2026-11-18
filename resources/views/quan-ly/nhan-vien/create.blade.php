@extends('layouts.app')
@section('title', 'Thêm nhân viên')

@section('content')
<div class="the-form mx-auto">
    <h1 class="h4 mb-3">Thêm nhân viên</h1>
    <form method="POST" action="{{ route('quan-ly.nhan-vien.store') }}" class="card card-body border-0 shadow-sm" novalidate>
        @csrf
        @include('partials.truong-tai-khoan')
        <button class="btn btn-success w-100">Tạo nhân viên</button>
        <div class="form-text mt-2">Nhân viên sẽ nhận email xác thực, sau đó đăng nhập bằng mật khẩu bạn đặt (nên đổi lại).</div>
    </form>
</div>
@endsection
