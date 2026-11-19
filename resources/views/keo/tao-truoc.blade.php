@extends('layouts.app')
@section('title', 'Cáp kèo trước')

@section('content')
@php
    $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ';
    $nhanPhut = fn ($p) => intdiv($p, 60).'h'.($p % 60 ? sprintf('%02d', $p % 60) : '');
@endphp
<div class="mx-auto" style="max-width: 560px">
    <h1 class="h4 mb-1">{{ $laSan ? 'Mở ô cáp kèo' : 'Cáp kèo trước, đặt sân sau' }}</h1>
    <p class="small text-muted mb-2">{{ $san->tenHienThi() }} · {{ $ngay->format('d/m/Y') }} · từ {{ substr($khung->gio_bat_dau, 0, 5) }}</p>
    <div class="alert alert-light border small py-2">
        <strong>Cách hoạt động:</strong> đăng kèo → đội khác bấm <em>Xin nhận kèo</em> → {{ $laSan ? 'sân' : 'bạn (hoặc sân)' }} duyệt.
        Đủ hai đội là <strong>chốt</strong>: web tự giữ sân và mỗi đội cọc <strong>15%</strong> trong {{ $san->chuSan->hoSoChuSan->phut_giu_cho }} phút.
        Trước lúc chốt, sân chưa bị giữ và chưa ai mất tiền. Sau khi chốt, đội nào không cọc bị trừ uy tín.
    </div>
    @if ($errors->any()) <div class="alert alert-danger py-2">{{ $errors->first() }}</div> @endif

    <form method="POST" action="{{ route('keo.luu-truoc') }}" class="card card-body border-0 shadow-sm" novalidate>@csrf
        <input type="hidden" name="san_id" value="{{ $san->id }}">
        <input type="hidden" name="ngay" value="{{ $ngay->toDateString() }}">
        <input type="hidden" name="khung_gio_id" value="{{ $khung->id }}">

        @unless ($laSan)
            <label class="form-label">Đội của bạn <span class="text-danger">*</span></label>
            @if ($doiCuaToi->isEmpty())
                <div class="alert alert-warning py-2 small">Bạn chưa có đội. <a href="{{ route('khach.doi.create') }}">Tạo đội</a> trước để đối thủ xem được đội bạn.</div>
            @else
                <select name="doi_id" class="form-select mb-3">
                    @foreach ($doiCuaToi as $doi) <option value="{{ $doi->id }}" @selected((int) old('doi_id') === $doi->id)>{{ $doi->ten }}</option> @endforeach
                </select>
            @endif
        @endunless

        <label class="form-label">Đá bao lâu</label>
        <select name="thoi_luong" class="form-select mb-3">
            @foreach ($thoiLuongs as $p)
                <option value="{{ $p }}" @selected($p === $khung->soPhut())>{{ $nhanPhut($p) }} · tiền sân {{ $tien($khung->giaTheoPhut($p)) }} · mỗi đội cọc {{ $tien(ceil($khung->giaTheoPhut($p) * 0.15 / 1000) * 1000) }}</option>
            @endforeach
        </select>

        <label class="form-label">Đội THUA chịu bao nhiêu tiền sân?</label>
        <div class="d-flex flex-wrap gap-2 mb-1">
            @foreach (\App\Models\Keo::TY_LE as $tl => $nhan)
                <input type="radio" class="btn-check" name="ty_le_thua" value="{{ $tl }}" id="tl{{ $tl }}" @checked((int) old('ty_le_thua', 70) === $tl)>
                <label class="btn btn-outline-secondary btn-sm" for="tl{{ $tl }}">{{ $nhan }}</label>
            @endforeach
        </div>
        <div class="small text-muted mb-3">Hoà = 5/5.</div>

        <label class="form-label">Trình độ</label>
        <select name="trinh_do" class="form-select mb-3">
            @foreach (\App\Models\Keo::TRINH_DO as $ma => $ten) <option value="{{ $ma }}" @selected(old('trinh_do', 'trung_binh') === $ma)>{{ $ten }}</option> @endforeach
        </select>

        <div class="form-check mb-3">
            <input type="hidden" name="cong_khai" value="1">
            <input class="form-check-input" type="checkbox" name="cong_khai" value="0" id="rieng">
            <label class="form-check-label" for="rieng"><strong>Kèo riêng</strong> — không hiện công khai, chỉ ai có link mời mới xem & xin nhận</label>
        </div>

        <label class="form-label">Lời nhắn</label>
        <textarea name="mo_ta" rows="2" class="form-control mb-3" placeholder="VD: đá vui vẻ, áo xanh">{{ old('mo_ta') }}</textarea>
        <button class="btn btn-danger w-100" @disabled(! $laSan && $doiCuaToi->isEmpty())>Đăng kèo</button>
    </form>
</div>
@endsection
