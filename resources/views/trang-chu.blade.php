@extends('layouts.app')
@section('title', 'Trang chủ')

@section('content')
<div class="text-center py-5">
    <h1 class="display-6 fw-bold">Tìm sân, đặt giờ trong 1 phút</h1>
    <p class="lead text-muted">Trang chủ có bộ lọc Tỉnh/Thành → Xã/Phường và lịch hôm nay sẽ có ở giai đoạn 4.</p>
    @guest
        <a href="{{ route('register') }}" class="btn btn-success btn-lg">Tạo tài khoản</a>
    @endguest
</div>
@endsection
