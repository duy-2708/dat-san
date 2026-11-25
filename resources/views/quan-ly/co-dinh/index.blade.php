@extends('layouts.app')
@section('title', 'Đặt cố định')

@section('content')
@php
    $thuTen = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 0 => 'Chủ nhật'];
@endphp
<h1 class="h4 mb-3"><i class="bi bi-calendar-check"></i> Đặt cố định</h1>
<p class="small text-muted">Đặt một khoảng giờ cố định mỗi tuần cho nhóm khách quen, chạy đến ngày bạn chọn (mặc định hết năm). Giá theo khung giờ chứa giờ bắt đầu. Khách báo bận tuần nào thì bỏ buổi đó; các buổi đã thu tiền sân sẽ được giữ lại.</p>

<div class="card border-0 shadow-sm mb-4"><div class="card-body">
    <h2 class="h6">Tạo lịch cố định</h2>
    <form method="POST" action="{{ route('quan-ly.co-dinh.store') }}" class="row g-2">@csrf
        <div class="col-md-4">
            <label class="form-label small">Sân</label>
            <select name="san_id" class="form-select" required>
                <option value="">— chọn —</option>
                @foreach ($sans as $san)
                    <option value="{{ $san->id }}">{{ $san->ten }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Thứ trong tuần</label>
            <select name="thu" class="form-select" required>
                @foreach ($thuTen as $so => $ten)
                    <option value="{{ $so }}">{{ $ten }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Giờ bắt đầu</label>
            <input type="time" name="gio_bat_dau" class="form-control" step="900" value="18:00" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Giờ kết thúc</label>
            <input type="time" name="gio_ket_thuc" class="form-control" step="900" value="19:00" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Tên khách / nhóm</label>
            <input name="ten" class="form-control" maxlength="100" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Số điện thoại</label>
            <input name="sdt" class="form-control" inputmode="tel" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Bắt đầu từ ngày</label>
            <input type="date" name="tu_ngay" class="form-control" value="{{ today()->toDateString() }}" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Đến ngày (bỏ trống = hết năm)</label>
            <input type="date" name="den_ngay" class="form-control">
        </div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-success w-100">Đặt cố định</button></div>
    </form>
</div></div>

<h2 class="h6">Lịch cố định đang có</h2>
@forelse ($chuoi as $c)
    @php
        $conLai = $c->dat()->where('trang_thai', '!=', 'da_huy')->where('ngay', '>=', today())->count();
    @endphp
    <div class="card border-0 shadow-sm mb-2"><div class="card-body">
        <div class="fw-semibold">{{ $c->ten }} · {{ $c->sdt }}</div>
        <div class="small text-muted mb-2">{{ $c->san->ten }} · {{ $thuTen[$c->thu] }} · {{ substr($c->gio_bat_dau, 0, 5) }}–{{ substr($c->gio_ket_thuc, 0, 5) }} · đến {{ $c->den_ngay ? \Carbon\Carbon::parse($c->den_ngay)->format('d/m/Y') : 'hết năm' }} · còn {{ $conLai }} buổi từ hôm nay</div>
        <form method="POST" action="{{ route('quan-ly.co-dinh.huy', $c) }}" class="row g-2 align-items-end"
              onsubmit="return confirm('Bỏ các buổi trong khoảng này? Buổi đã thu tiền sân sẽ được giữ lại.')">@csrf @method('PATCH')
            <div class="col-auto"><label class="form-label small mb-0">Bỏ từ ngày</label><input type="date" name="tu" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required></div>
            <div class="col-auto"><label class="form-label small mb-0">đến ngày</label><input type="date" name="den" class="form-control form-control-sm" value="{{ today()->endOfYear()->toDateString() }}" required></div>
            <div class="col-auto"><button class="btn btn-sm btn-danger">Bỏ các buổi này</button></div>
        </form>
    </div></div>
@empty
    <div class="text-muted small bg-white rounded p-3">Chưa có lịch cố định nào.</div>
@endforelse
@endsection
