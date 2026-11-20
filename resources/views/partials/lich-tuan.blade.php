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
    $dongPhut = \App\Support\ThoiGian::phut($san->gio_dong_cua);
    $tyLeCoc = (int) ($san->chuSan->hoSoChuSan?->ty_le_coc ?? 30);
    $nhanPhut = fn ($p) => intdiv($p, 60).'h'.($p % 60 ? sprintf('%02d', $p % 60) : '');
    $laKhachDangNhap = auth()->check() && auth()->user()->laKhach();
    // Kèo công khai đang tìm đối trong tuần này của sân → huy hiệu trên khung giờ
    $keoMo = \App\Models\Keo::with(['datSan', 'khungGio'])->where('trang_thai', 'dang_tim')->where('cong_khai', true)
        ->where(fn ($q) => $q->where('san_id', $san->id)->orWhereHas('datSan', fn ($d) => $d->where('san_id', $san->id)))
        ->with('doiA')->get()->groupBy(fn ($k) => $k->batDauLuc()->format('Y-m-d H:i'));
@endphp
<div class="d-flex align-items-center justify-content-between mb-2">
    @php $noi = str_contains($url, '?') ? '&' : '?'; @endphp
    <a href="{{ $url }}{{ $noi }}tu={{ $truoc->toDateString() }}" class="btn btn-sm btn-outline-secondary {{ $cheDo === 'khach' && $tu->lte(today()) ? 'disabled' : '' }}"><i class="bi bi-chevron-left"></i></a>
    <span class="small fw-semibold">{{ $tu->format('d/m/Y') }} – {{ $tu->copy()->addDays(6)->format('d/m/Y') }}</span>
    <a href="{{ $url }}{{ $noi }}tu={{ $sau->toDateString() }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
</div>

<ul class="nav nav-pills flex-nowrap overflow-auto gap-1 pb-2 mb-2" role="tablist" style="scrollbar-width: thin">
    @foreach ($lich as $i => $n)
        @php $conTrong = collect($n['khungs'])->where('trang_thai', 'trong')->count(); @endphp
        <li class="nav-item flex-shrink-0">
            <button class="nav-link px-3 py-1 text-center {{ $i === 0 ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#ngay{{ $i }}" type="button" style="min-width: 64px">
                <div class="small">{{ $n['ngay']->isToday() ? 'Hôm nay' : $thu[$n['ngay']->dayOfWeek] }}</div>
                <div class="fw-bold">{{ $n['ngay']->format('d/m/Y') }}</div>
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
                $sanO = $o['san'] ?? $san;
                $mau = ['trong' => 'success', 'dang_giu' => 'warning', 'da_dat' => 'danger', 'qua_gio' => 'secondary'][$tt];
                $nhan = ['trong' => 'Trống', 'dang_giu' => 'Đang giữ', 'da_dat' => 'Đã đặt', 'qua_gio' => 'Đã qua'][$tt];
            @endphp
            <div class="col-6 col-md-4 col-lg-3">
                <div class="border rounded p-2 h-100 bg-white {{ $tt === 'qua_gio' ? 'opacity-50' : '' }} border-{{ $mau }}-subtle">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="fw-semibold">{{ $k->nhan() }}</span>
                        @if ($k->la_gio_vang) <i class="bi bi-star-fill text-warning" title="Giờ vàng"></i> @endif
                    </div>
                    @if ($dsKeo = $keoMo->get($n['ngay']->format('Y-m-d').' '.substr($k->gio_bat_dau, 0, 5)))
                        @if ($dsKeo->count() === 1)
                            <a href="{{ route('keo.show', $dsKeo[0]) }}" class="badge bg-danger text-decoration-none mb-1 d-block text-wrap text-start lh-sm">⚔ Có kèo tìm đối{{ $dsKeo[0]->doiA ? ' · '.$dsKeo[0]->doiA->ten : '' }}</a>
                        @else
                            <a href="{{ route('keo.index', ['san' => $san->id, 'ngay' => $n['ngay']->toDateString()]) }}" class="badge bg-danger text-decoration-none mb-1 d-block text-wrap text-start lh-sm">⚔ {{ $dsKeo->count() }} kèo tìm đối — xem</a>
                        @endif
                    @endif
                    <div class="small text-muted">{{ $tien($k->gia) }}đ</div>
                    @if (isset($o['tong']) && $tt !== 'qua_gio')
                        <div class="small {{ $o['con_trong'] ? 'text-success' : 'text-danger' }}" style="font-size:.75rem"
                             @if ($o['con_trong']) title="Còn trống: {{ implode(', ', $o['ten_trong']) }}" @endif>Còn {{ $o['con_trong'] }}/{{ $o['tong'] }} sân @if ($o['con_trong']) <i class="bi bi-info-circle"></i> @endif</div>
                    @endif

                    @if ($cheDo === 'khach')
                        @if ($tt === 'trong')
                            @auth
                                @if ($laKhachDangNhap)
                                    <button type="button" class="btn btn-sm btn-success w-100 mt-1" data-bs-toggle="collapse" data-bs-target="#o{{ $n['ngay']->format('md') }}{{ $k->id }}">Đặt / Cáp kèo <i class="bi bi-chevron-down"></i></button>
                                    <div class="collapse" id="o{{ $n['ngay']->format('md') }}{{ $k->id }}">
                                    <form method="POST" action="{{ route('khach.dat-san.store') }}" class="mt-1">
                                        @csrf
                                        <input type="hidden" name="san_id" value="{{ $sanO->id }}">
                                        @isset($o['tong']) <input type="hidden" name="tu_xep" value="1"> @endisset
                                        <input type="hidden" name="ngay" value="{{ $n['ngay']->toDateString() }}">
                                        <input type="hidden" name="khung_gio_id" value="{{ $k->id }}">
                                        @if ($k->soPhut() > 15)
                                            <select name="bat_dau" class="form-select form-select-sm mb-1" title="Giờ bắt đầu đá">
                                                @for ($p = $k->phutBatDau(); $p < $k->phutKetThuc(); $p += 15)
                                                    @continue($n['ngay']->copy()->addMinutes($p)->lte(now()))
                                                    <option value="{{ \App\Support\ThoiGian::chuoi($p) }}">Bắt đầu {{ \App\Support\ThoiGian::chuoi($p) }}</option>
                                                @endfor
                                            </select>
                                        @endif
                                        <select name="thoi_luong" class="form-select form-select-sm mb-1" title="Đá bao lâu (tính từ {{ substr($k->gio_bat_dau, 0, 5) }})">
                                            @foreach ($k->cacThoiLuong($dongPhut) as $p)
                                                <option value="{{ $p }}" @selected($p === $k->soPhut())>{{ $nhanPhut($p) }} · {{ $tien($k->giaTheoPhut($p)) }}đ</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-success w-100">Đặt · cọc {{ $tien($k->cocTheoPhut($k->soPhut(), $tyLeCoc)) }}</button>
                                    </form>
                                    <a href="{{ route('keo.tao-truoc', ['san' => $sanO->id, 'ngay' => $n['ngay']->toDateString(), 'khung' => $k->id]) }}"
                                       class="btn btn-sm btn-outline-danger w-100 mt-1" title="Chưa cần đặt sân — đủ đội mới cọc">⚔ Cáp kèo giờ này</a>
                                    </div>
                                @else
                                    @can('view', $sanO)
                                        <a href="{{ route('quan-ly.san.lich', ['san' => $sanO, 'tu' => $n['ngay']->toDateString(), 'mo' => $k->id]) }}"
                                           class="btn btn-sm btn-outline-success w-100 mt-1">+ Đặt tại chỗ{{ isset($o['tong']) ? ' · '.$sanO->ten : '' }}</a>
                                    @else
                                        <span class="badge bg-success-subtle text-success-emphasis w-100 mt-1 py-2">Trống</span>
                                    @endcan
                                @endif
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
                                    data-thoi-luong='@json(collect($k->cacThoiLuong($dongPhut))->map(fn ($p) => ['p' => $p, 'nhan' => $nhanPhut($p).' · '.$tien($k->giaTheoPhut($p)).'đ', 'chon' => $p === $k->soPhut()]))'
                                    data-nhan="{{ $n['ngay']->format('d/m/Y') }} {{ $k->nhan() }}">+ Đặt tại chỗ</button>
                            <a href="{{ route('keo.tao-truoc', ['san' => $sanO->id, 'ngay' => $n['ngay']->toDateString(), 'khung' => $k->id]) }}"
                               class="btn btn-sm btn-outline-danger w-100 mt-1">⚔ Mở ô cáp kèo</a>
                        @elseif ($don)
                            <div class="small mt-1">
                                <span class="badge bg-{{ $mau }}">{{ $nhan }}</span>
                                @if ((int) $don->san_id !== $san->id)
                                    <div class="text-muted">Khoá do {{ $don->san->ten }}</div>
                                @else
                                    <div class="text-truncate fw-semibold">{{ $don->tenNguoiDat() }}</div>
                                    <div><a href="tel:{{ $don->sdtNguoiDat() }}">{{ $don->sdtNguoiDat() }}</a></div>
                                    <div class="text-muted">{{ $don->ma_don }} · {{ $don->nguon === 'tai_cho' ? 'Tại chỗ' : 'Online' }}</div>
                                    @if ($don->trang_thai === 'da_dat')
                                        @if ($don->da_thu_tien_luc)
                                            <div class="text-success"><i class="bi bi-cash-coin"></i> Đã thu {{ $tien($don->tien_thu_tai_san) }}</div>
                                        @else
                                            <form method="POST" action="{{ route('quan-ly.dat-san.da-thu', $don) }}" class="input-group input-group-sm mt-1">@csrf @method('PATCH')
                                                <input name="so_tien" value="{{ $don->conLaiPhai() }}" inputmode="numeric" class="form-control o-tien px-1" title="Số tiền sân thực nhận">
                                                <button class="btn btn-success px-2" title="Đã nhận tiền sân"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                        @endif
                                    @endif
                                    @if ($don->nguon === 'tai_cho' && $don->trang_thai === 'da_dat' && $don->batDauLuc()->isFuture())
                                        @php $keoO = \App\Models\Keo::where('dat_san_id', $don->id)->first(); @endphp
                                        <a href="{{ $keoO ? route('keo.show', $keoO) : route('quan-ly.keo.create', $don) }}" class="btn btn-sm btn-outline-danger py-0 mt-1">{{ $keoO ? 'Xem kèo' : 'Tạo kèo hộ' }}</a>
                                    @endif
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
@if ($cheDo !== 'khach' && request('mo'))
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const nut = document.querySelector('[data-bs-target="#modalTaiCho"][data-ngay="{{ $tu->toDateString() }}"][data-khung="{{ (int) request('mo') }}"]');
        if (nut) { nut.scrollIntoView({ block: 'center' }); setTimeout(() => nut.click(), 300); }
    });
    </script>
    @endpush
@endif
