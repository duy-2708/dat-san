@extends('layouts.app')
@section('title', 'Đặt cố định')

@section('content')
@php
    $thuTen = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 0 => 'Chủ nhật'];
@endphp
<h1 class="h4 mb-3"><i class="bi bi-calendar-check"></i> Đặt cố định</h1>
<p class="small text-muted">Đặt một khung giờ cố định mỗi tuần cho nhóm khách quen, chạy đến hết năm. Khách báo bận tuần nào thì bỏ buổi đó; các buổi đã thu tiền sân sẽ được giữ lại.</p>

<div class="card border-0 shadow-sm mb-4"><div class="card-body">
    <h2 class="h6">Tạo lịch cố định</h2>
    <form method="POST" action="{{ route('quan-ly.co-dinh.store') }}" class="row g-2">@csrf
        <div class="col-md-6">
            <label class="form-label small">Sân và khung giờ</label>
            <select name="khung_gio_id" class="form-select" required>
                <option value="">— chọn —</option>
                @foreach ($sans as $san)
                    <optgroup label="{{ $san->ten }}">
                        @foreach ($san->khungGios as $khung)
                            <option value="{{ $khung->id }}" data-san="{{ $san->id }}">{{ $san->ten }} · {{ $khung->nhan() }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Thứ trong tuần</label>
            <select name="thu" class="form-select" required>
                @foreach ($thuTen as $so => $ten)
                    <option value="{{ $so }}">{{ $ten }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Bắt đầu từ ngày</label>
            <input type="date" name="tu_ngay" class="form-control" value="{{ today()->toDateString() }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label small">Tên khách / nhóm</label>
            <input name="ten" class="form-control" maxlength="100" required>
        </div>
        <div class="col-md-6">
            <label class="form-label small">Số điện thoại</label>
            <input name="sdt" class="form-control" inputmode="tel" required>
        </div>
        <div class="col-12"><button class="btn btn-success">Đặt cố định đến hết năm</button></div>
    </form>
</div></div>

<h2 class="h6">Lịch cố định đang có</h2>
@forelse ($chuoi as $c)
    @php
        $khung = $c->san->khungGios->firstWhere('id', $c->khung_gio_id);
        $conLai = $c->dat()->where('trang_thai', '!=', 'da_huy')->where('ngay', '>=', today())->count();
    @endphp
    <div class="card border-0 shadow-sm mb-2"><div class="card-body">
        <div class="fw-semibold">{{ $c->ten }} · {{ $c->sdt }}</div>
        <div class="small text-muted mb-2">{{ $c->san->ten }} · {{ $thuTen[$c->thu] }} · {{ $khung?->nhan() ?? '—' }} · còn {{ $conLai }} buổi từ hôm nay</div>
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
