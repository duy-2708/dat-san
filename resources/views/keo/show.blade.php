@extends('layouts.app')
@section('title', 'Kèo #'.$keo->id)

@section('content')
@php
    $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ';
    $u = auth()->user();
    $d = $keo->datSan;
    $daToiGio = $d->batDauLuc()->lte(now());
    $laSan = $u && $u->idChuSanQuanLy() !== null && (int) $d->san->chu_san_id === $u->idChuSanQuanLy();
    $laChuSan = $u && $u->laChuSan() && (int) $d->san->chu_san_id === $u->id;
    $benToi = $u ? $keo->benCua($u) : null;
    $laDaiDien = $u && $keo->laDaiDien($u);
    $toiTg = $u ? $keo->thamGias->firstWhere('user_id', $u->id) : null;
    $cocCho = $toiTg && $toiTg->trang_thai === 'cho_coc'
        ? \App\Models\GiaoDich::where('tham_gia_keo_id', $toiTg->id)->where('trang_thai', 'cho_thanh_toan')->latest('id')->first() : null;
    $mauTt = ['dang_tim' => 'primary', 'da_chot' => 'success', 'cho_xac_nhan' => 'warning', 'tranh_chap' => 'danger', 'hoan_tat' => 'secondary', 'da_huy' => 'dark'];
    $mauDd = ['chua' => 'light text-dark border', 'co_mat' => 'success', 'vang' => 'danger'];
    $nhanDd = ['chua' => 'Chưa điểm danh', 'co_mat' => 'Có mặt', 'vang' => 'Vắng'];
@endphp

<div class="mx-auto" style="max-width: 760px">
    @error('keo') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start">
            <div>
                <span class="badge {{ $keo->laDoiDau() ? 'bg-danger' : 'bg-primary' }}">{{ $keo->laDoiDau() ? 'Đội đấu đội' : 'Ghép lẻ' }}</span>
                <span class="badge bg-{{ $mauTt[$keo->trang_thai] }}">{{ \App\Models\Keo::TRANG_THAI[$keo->trang_thai] }}</span>
                <h1 class="h5 mt-2 mb-0">{{ \Carbon\Carbon::parse($d->ngay)->format('d/m/Y') }} · {{ $d->khungGioNhan() }}</h1>
                <div class="small"><a href="{{ route('san.show', $d->san) }}">{{ $d->san->tenHienThi() }}</a> · {{ $d->san->diaChiDayDu() }}</div>
            </div>
            <div class="text-end small">
                Trình độ: <strong>{{ \App\Models\Keo::TRINH_DO[$keo->trinh_do] }}</strong><br>
                @if ($keo->laDoiDau())
                    Thua chịu: <strong>{{ \App\Models\Keo::TY_LE[$keo->ty_le_thua] }}</strong> · tiền sân {{ $tien($keo->tien_san) }}<br>
                    Bên B cọc {{ $tien($keo->coc_moi_ben) }}
                @else
                    Cần {{ $keo->so_nguoi_can }} người · {{ $tien($keo->tien_moi_nguoi) }}/người
                    @if ($keo->tien_coc_ca_nhan) <br>Cọc {{ $tien($keo->tien_coc_ca_nhan) }} @endif
                @endif
            </div>
        </div>
        @if ($keo->mo_ta) <p class="small mt-2 mb-0" style="white-space: pre-line">{{ $keo->mo_ta }}</p> @endif

        {{-- Hành động tham gia --}}
        <div class="mt-3">
            @guest
                <a href="{{ route('login') }}" class="btn btn-success w-100">Đăng nhập để tham gia</a>
            @else
                @if ($cocCho)
                    <a href="{{ route('keo.coc', $cocCho) }}" class="btn btn-warning w-100">Quét QR cọc {{ $tien($cocCho->so_tien) }} để giữ chỗ</a>
                @elseif (! $benToi && $keo->trang_thai === 'dang_tim' && ! $daToiGio && $u->laKhach())
                    @if ($keo->laDoiDau() && ! $keo->dai_dien_b_id)
                        <form method="POST" action="{{ route('khach.keo.nhan', $keo) }}">@csrf
                            <button class="btn btn-danger w-100">Nhận kèo · cọc {{ $tien($keo->coc_moi_ben) }}</button></form>
                    @elseif (! $keo->laDoiDau())
                        <form method="POST" action="{{ route('khach.keo.tham-gia', $keo) }}">@csrf
                            <button class="btn btn-primary w-100">Tham gia{{ $keo->tien_coc_ca_nhan ? ' · cọc '.$tien($keo->tien_coc_ca_nhan) : '' }}</button></form>
                    @endif
                @endif
            @endguest
        </div>
    </div></div>

    {{-- Thành viên --}}
    <div class="row g-3 mb-3">
        @foreach ($keo->laDoiDau() ? ['a' => 'Bên A (chủ kèo)', 'b' => 'Bên B'] : ['a' => 'Người chơi'] as $ben => $tenBen)
            @php $ds = $keo->thamGias->where('ben', $ben)->whereIn('trang_thai', ['cho_coc', 'da_xac_nhan']); @endphp
            <div class="col-md-{{ $keo->laDoiDau() ? 6 : 12 }}">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0">{{ $tenBen }}</h2>
                        @if ($keo->laDoiDau() && $keo->{$ben.'_bao_ket_qua'})
                            <span class="small">Báo: <strong>{{ \App\Models\Keo::KET_QUA[$keo->{$ben.'_bao_ket_qua'}] }}</strong></span>
                        @endif
                    </div>
                    @forelse ($ds as $tg)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-1 small">
                            <span>
                                {{ $tg->user->name }} @if ($tg->vai_tro === 'dai_dien') <i class="bi bi-star-fill text-warning" title="Đại diện"></i> @endif
                                <span class="text-muted">· {{ $tg->user->uyTinNhan() }} · {{ $tg->user->diem_uy_tin }}đ</span>
                                @if ($tg->trang_thai === 'cho_coc') <span class="badge bg-warning text-dark">chờ cọc</span> @endif
                                @if ($tg->user->biHanChe()) <span class="badge bg-danger">bị hạn chế</span> @endif
                            </span>
                            <span class="d-flex gap-1 align-items-center">
                                <span class="badge bg-{{ $mauDd[$tg->diem_danh] }}">{{ $nhanDd[$tg->diem_danh] }}{{ $tg->mien_phat ? ' (miễn phạt)' : '' }}</span>
                                @if (! $keo->laDoiDau() && $daToiGio && ($laSan || $keo->laDaiDien($u ?? new \App\Models\User, 'a')) && $tg->vai_tro === 'nguoi_le' && $tg->trang_thai === 'da_xac_nhan')
                                    <form method="POST" action="{{ route('keo.diem-danh', $keo) }}">@csrf
                                        <input type="hidden" name="tham_gia_id" value="{{ $tg->id }}"><input type="hidden" name="co_mat" value="1">
                                        <button class="btn btn-sm btn-outline-success py-0">✓</button></form>
                                    <form method="POST" action="{{ route('keo.diem-danh', $keo) }}">@csrf
                                        <input type="hidden" name="tham_gia_id" value="{{ $tg->id }}"><input type="hidden" name="co_mat" value="0">
                                        <button class="btn btn-sm btn-outline-danger py-0">✗</button></form>
                                @endif
                                @if ($laChuSan && $tg->diem_danh === 'vang' && ! $tg->mien_phat)
                                    <form method="POST" action="{{ route('quan-ly.keo.mien-phat', $tg) }}">@csrf @method('PATCH')
                                        <button class="btn btn-sm btn-outline-secondary py-0" title="Người này thực tế có đến">Miễn phạt</button></form>
                                @endif
                                @if ($laChuSan && $tg->user->biHanChe())
                                    <form method="POST" action="{{ route('quan-ly.han-che.go', $tg->user) }}">@csrf @method('PATCH')
                                        <button class="btn btn-sm btn-outline-primary py-0">Gỡ hạn chế</button></form>
                                @endif
                            </span>
                        </div>
                    @empty
                        <div class="small text-muted">{{ $ben === 'b' ? 'Đang chờ đội nhận kèo...' : 'Chưa có ai.' }}</div>
                    @endforelse

                    @if ($keo->laDoiDau() && $u && $keo->laDaiDien($u, $ben) && in_array($keo->trang_thai, ['dang_tim', 'da_chot'], true) && ! $daToiGio)
                        <form method="POST" action="{{ route('khach.keo.thanh-vien', $keo) }}" class="d-flex gap-1 mt-2">@csrf
                            <input name="tim" class="form-control form-control-sm" placeholder="Thêm thành viên: SĐT hoặc email">
                            <button class="btn btn-sm btn-outline-success text-nowrap">Thêm</button></form>
                    @endif

                    @if ($keo->laDoiDau() && $daToiGio && ($laSan || $keo->laDaiDien($u ?? new \App\Models\User, 'a')) && $ds->isNotEmpty() && $keo->trang_thai !== 'da_huy')
                        <div class="d-flex gap-1 mt-2">
                            <form method="POST" action="{{ route('keo.diem-danh', $keo) }}">@csrf
                                <input type="hidden" name="ben" value="{{ $ben }}"><input type="hidden" name="co_mat" value="1">
                                <button class="btn btn-sm btn-outline-success">Cả bên có mặt</button></form>
                            <form method="POST" action="{{ route('keo.diem-danh', $keo) }}" onsubmit="return confirm('Đánh vắng cả {{ $tenBen }}? Mọi thành viên bị trừ uy tín.')">@csrf
                                <input type="hidden" name="ben" value="{{ $ben }}"><input type="hidden" name="co_mat" value="0">
                                <button class="btn btn-sm btn-outline-danger">Cả bên vắng</button></form>
                        </div>
                    @endif
                </div></div>
            </div>
        @endforeach
    </div>

    {{-- Báo / chốt kết quả --}}
    @if ($keo->laDoiDau() && $daToiGio && in_array($keo->trang_thai, ['da_chot', 'cho_xac_nhan', 'tranh_chap'], true))
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">Kết quả trận</h2>
            <p class="small text-muted mb-2">Hạn xác nhận: <strong>{{ $keo->han_xac_nhan_luc->format('H:i d/m') }}</strong>. Hai bên báo khớp nhau thì kèo hoàn tất; bấm nhầm thì báo lại.</p>
            @if ($laDaiDien)
                <form method="POST" action="{{ route('khach.keo.ket-qua', $keo) }}" class="d-flex flex-wrap gap-2">@csrf
                    @foreach (\App\Models\Keo::KET_QUA as $ma => $nhan)
                        <button name="ket_qua" value="{{ $ma }}" class="btn btn-outline-dark btn-sm">{{ $nhan }}</button>
                    @endforeach
                </form>
            @endif
            @if ($laSan)
                <form method="POST" action="{{ route('quan-ly.keo.chot', $keo) }}" class="d-flex flex-wrap gap-2 mt-2 align-items-center">@csrf @method('PATCH')
                    <span class="small fw-semibold">Chủ sân chốt:</span>
                    @foreach (\App\Models\Keo::KET_QUA as $ma => $nhan)
                        <button name="ket_qua" value="{{ $ma }}" class="btn btn-sm btn-dark" onclick="return confirm('Chốt: {{ $nhan }}?')">{{ $nhan }}</button>
                    @endforeach
                </form>
            @endif
        </div></div>
    @endif

    {{-- Quyết toán --}}
    @if ($quyetToan)
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">Kết quả: {{ \App\Models\Keo::KET_QUA[$keo->ket_qua] }} — tiền sân {{ $tien($keo->tien_san) }}</h2>
            <table class="table table-sm small mb-0">
                <thead><tr><th></th><th class="text-end">Phải chịu</th><th class="text-end">Đã cọc</th><th class="text-end">Trả thêm tại sân</th></tr></thead>
                <tbody>
                @foreach (['a' => 'Bên A', 'b' => 'Bên B'] as $ben => $ten)
                    @php $q = $quyetToan[$ben]; @endphp
                    <tr><td>{{ $ten }}</td><td class="text-end">{{ $tien($q['phan']) }}</td><td class="text-end">{{ $tien($q['da_tra']) }}</td>
                        <td class="text-end fw-semibold">{{ $q['con'] >= 0 ? $tien($q['con']) : 'Sân trả lại '.$tien(-$q['con']) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div></div>
    @endif

    @if ($u && $keo->laDaiDien($u, 'a') && $keo->trang_thai === 'dang_tim')
        <form method="POST" action="{{ route('khach.keo.huy', $keo) }}" class="text-end" onsubmit="return confirm('Huỷ kèo này?')">@csrf
            <button class="btn btn-link text-danger">Huỷ kèo</button></form>
    @endif
</div>
@endsection
