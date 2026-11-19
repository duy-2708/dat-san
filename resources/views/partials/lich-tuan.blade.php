{{--
    Lịch 7 ngày của 1 sân.
    $lich  : kết quả LichSan::tuan()
    $san   : sân
    $tu    : ngày đầu
    $cheDo : 'khach' (trang công khai, không lộ người đặt) | 'quan-ly' (chủ sân/nhân viên)
    $url   : route hiện tại để chuyển tuần
--}}
@php
    $tien = fn ($so) => number_format((int) $so, 0, ',', '.');
    $thu = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
    $truoc = $tu->copy()->subDays(7);
    $sau = $tu->copy()->addDays(7);
@endphp
<div class="d-flex align-items-center justify-content-between mb-2">
    <a href="{{ $url }}?tu={{ $truoc->toDateString() }}" class="btn btn-sm btn-outline-secondary {{ $cheDo === 'khach' && $tu->lte(today()) ? 'disabled' : '' }}"><i class="bi bi-chevron-left"></i></a>
    <span class="small fw-semibold">{{ $tu->format('d/m') }} – {{ $tu->copy()->addDays(6)->format('d/m/Y') }}</span>
    <a href="{{ $url }}?tu={{ $sau->toDateString() }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
</div>

<ul class="nav nav-pills flex-nowrap overflow-auto gap-1 pb-2 mb-2" role="tablist" style="scrollbar-width: thin">
    @foreach ($lich as $i => $n)
        @php $conTrong = collect($n['khungs'])->where('trang_thai', 'trong')->count(); @endphp
        <li class="nav-item flex-shrink-0">
            <button class="nav-link px-3 py-1 text-center {{ $i === 0 ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#ngay{{ $i }}" type="button" style="min-width: 64px">
                <div class="small">{{ $n['ngay']->isToday() ? 'Hôm nay' : $thu[$n['ngay']->dayOfWeek] }}</div>
                <div class="fw-bold">{{ $n['ngay']->format('d/m') }}</div>
                <div class="small {{ $i === 0 ? '' : 'text-muted' }}" style="font-size:.7rem">{{ $conTrong }} trống</div>
            </button>
        </li>
    @endforeach
</ul>

<div class="tab-content">
@foreach ($lich as $i => $n)
    <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="ngay{{ $i }}">
        @if ($n['loai'] !== 'ngay_thuong')
            <div class="small mb-2"><span class="badge bg-info-subtle text-info-emphasis">{{ $n['loai'] === 'ngay_le' ? 'Giá ngày lễ' : 'Giá cuối tuần' }}</span></div>
        @endif
        <div class="row g-2">
        @forelse ($n['khungs'] as $o)
            @php
                $k = $o['khung'];
                $tt = $o['trang_thai'];
                $don = $o['don'];
                $mau = ['trong' => 'success', 'dang_giu' => 'warning', 'da_dat' => 'danger', 'qua_gio' => 'secondary'][$tt];
                $nhan = ['trong' => 'Trống', 'dang_giu' => 'Đang giữ', 'da_dat' => 'Đã đặt', 'qua_gio' => 'Đã qua'][$tt];
            @endphp
            <div class="col-6 col-md-4 col-lg-3">
                <div class="border rounded p-2 h-100 bg-white {{ $tt === 'qua_gio' ? 'opacity-50' : '' }} border-{{ $mau }}-subtle">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="fw-semibold">{{ $k->nhan() }}</span>
                        @if ($k->la_gio_vang) <i class="bi bi-star-fill text-warning" title="Giờ vàng"></i> @endif
                    </div>
                    <div class="small text-muted">{{ $tien($k->gia) }}đ</div>

                    @if ($cheDo === 'khach')
                        @if ($tt === 'trong')
                            @auth
                                <form method="POST" action="{{ route('khach.dat-san.store') }}" class="mt-1">
                                    @csrf
                                    <input type="hidden" name="san_id" value="{{ $san->id }}">
                                    <input type="hidden" name="ngay" value="{{ $n['ngay']->toDateString() }}">
                                    <input type="hidden" name="khung_gio_id" value="{{ $k->id }}">
                                    <button class="btn btn-sm btn-success w-100">Đặt · cọc {{ $tien($k->tienCoc()) }}</button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-success w-100 mt-1">Đăng nhập để đặt</a>
                            @endauth
                        @else
                            <span class="badge bg-{{ $mau }} w-100 mt-1 py-2">{{ $nhan }}</span>
                        @endif
                    @else
                        @if ($tt === 'trong')
                            <button type="button" class="btn btn-sm btn-outline-success w-100 mt-1" data-bs-toggle="modal" data-bs-target="#modalTaiCho"
                                    data-ngay="{{ $n['ngay']->toDateString() }}" data-khung="{{ $k->id }}"
                                    data-nhan="{{ $n['ngay']->format('d/m') }} {{ $k->nhan() }}">+ Đặt tại chỗ</button>
                        @elseif ($don)
                            <div class="small mt-1">
                                <span class="badge bg-{{ $mau }}">{{ $nhan }}</span>
                                @if ((int) $don->san_id !== $san->id)
                                    <div class="text-muted">Khoá do {{ $don->san->ten }}</div>
                                @else
                                    <div class="text-truncate fw-semibold">{{ $don->tenNguoiDat() }}</div>
                                    <div><a href="tel:{{ $don->sdtNguoiDat() }}">{{ $don->sdtNguoiDat() }}</a></div>
                                    <div class="text-muted">{{ $don->ma_don }} · {{ $don->nguon === 'tai_cho' ? 'Tại chỗ' : 'Online' }}</div>
                                @endif
                            </div>
                        @else
                            <span class="badge bg-{{ $mau }} w-100 mt-1">{{ $nhan }}</span>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <div class="col-12 text-muted small">Sân chưa mở bán khung giờ cho ngày này.</div>
        @endforelse
        </div>
    </div>
@endforeach
</div>
