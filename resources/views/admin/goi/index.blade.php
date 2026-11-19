@extends('layouts.app')
@section('title', 'Gói dịch vụ')

@section('content')
<h1 class="h4 mb-3">Gói dịch vụ</h1>
@if ($errors->any()) <div class="alert alert-danger py-2">{{ $errors->first() }}</div> @endif
<div class="row g-3">
    @foreach ($gois->push(null) as $g)
        <div class="col-12 col-lg-6">
            <form method="POST" action="{{ $g ? route('admin.goi.update', $g) : route('admin.goi.store') }}" class="card border-0 shadow-sm h-100">@csrf
                @if ($g) @method('PUT') @endif
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <strong>{{ $g ? $g->ten : 'Thêm gói mới' }}</strong>
                        @if ($g) <span class="small text-muted">{{ $g->ho_so_chu_sans_count }} chủ sân đang dùng</span> @endif
                    </div>
                    <div class="row g-2 small">
                        <div class="col-6"><label class="form-label mb-0">Tên</label><input name="ten" value="{{ $g?->ten }}" class="form-control form-control-sm" required></div>
                        <div class="col-6"><label class="form-label mb-0">Giá/tháng (đ)</label><input name="gia_thang" inputmode="numeric" value="{{ $g?->gia_thang ?? 199000 }}" class="form-control form-control-sm o-tien"></div>
                        <div class="col-6"><label class="form-label mb-0">Số sân tối đa</label><input name="so_san_toi_da" type="number" min="1" value="{{ $g?->so_san_toi_da ?? 5 }}" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label mb-0">Số nhân viên tối đa</label><input name="so_nhan_vien_toi_da" type="number" min="0" value="{{ $g?->so_nhan_vien_toi_da ?? 2 }}" class="form-control form-control-sm"></div>
                        <div class="col-12"><label class="form-label mb-0">Mô tả</label><input name="mo_ta" value="{{ $g?->mo_ta }}" class="form-control form-control-sm"></div>
                        <div class="col-6"><label><input type="checkbox" name="cho_phep_cong_tu_dong" value="1" @checked($g?->cho_phep_cong_tu_dong)> Cho dùng PayOS</label></div>
                        <div class="col-6"><label><input type="checkbox" name="dang_ban" value="1" @checked($g ? $g->dang_ban : true)> Đang bán</label></div>
                    </div>
                    <button class="btn btn-sm btn-success mt-2">{{ $g ? 'Lưu' : 'Thêm gói' }}</button>
                </div>
            </form>
        </div>
    @endforeach
</div>
<p class="small text-muted mt-3">Gói đã có người dùng không xoá được, bỏ chọn "Đang bán" để ẩn khỏi trang mua. Đổi giá không ảnh hưởng đơn đã tạo.</p>
@endsection
