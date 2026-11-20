@extends('layouts.app')
@section('title', 'Kèo #'.$keo->id)

@section('content')
@php
    $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ';
    $u = auth()->user();
    $sanK = $keo->sanThuc();
    $daToiGio = $keo->batDauLuc()->lte(now());
    $laSan = $u && $u->idChuSanQuanLy() !== null && (int) $sanK->chu_san_id === $u->idChuSanQuanLy();
    $laChuSan = $u && $u->laChuSan() && (int) $sanK->chu_san_id === $u->id;
    $laChuKeo = $u && $keo->dai_dien_a_id && (int) $keo->dai_dien_a_id === $u->id;
    $xinCuaToi = $u ? $keo->xinKeos->firstWhere('user_id', $u->id) : null;
    $doiBen = ['a' => $keo->doiA, 'b' => $keo->doiB];
    $benToi = $u ? $keo->benCua($u) : null;
    $laDaiDien = $u && $keo->laDaiDien($u);
    $toiTg = $u ? $keo->thamGias->firstWhere('user_id', $u->id) : null;
    $cocCho = $toiTg && $toiTg->trang_thai === 'cho_coc'
        ? \App\Models\GiaoDich::where('tham_gia_keo_id', $toiTg->id)->where('trang_thai', 'cho_thanh_toan')->latest('id')->first() : null;
    $mauTt = ['dang_tim' => 'primary', 'cho_coc' => 'warning', 'da_chot' => 'success', 'cho_xac_nhan' => 'warning', 'tranh_chap' => 'danger', 'hoan_tat' => 'secondary', 'da_huy' => 'dark'];
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
                @if (! $keo->cong_khai) <span class="badge bg-dark">Kèo riêng</span> @endif
                @if ($keo->mien_coc) <span class="badge bg-success">Sân bảo lãnh · miễn cọc</span> @endif
                <h1 class="h5 mt-2 mb-0">{{ $keo->batDauLuc()->format('d/m/Y') }} · {{ $keo->nhanGio() }}</h1>
                <div class="small"><a href="{{ route('san.show', $sanK) }}">{{ $sanK->tenHienThi() }}</a> · {{ $sanK->diaChiDayDu() }}</div>
            </div>
            <div class="text-end small">
                Trình độ: <strong>{{ \App\Models\Keo::TRINH_DO[$keo->trinh_do] }}</strong><br>
                @if ($keo->laDoiDau())
                    Thua chịu: <strong>{{ \App\Models\Keo::TY_LE[$keo->ty_le_thua] }}</strong> · tiền sân {{ $tien($keo->tien_san) }}<br>
                    {{ $keo->mien_coc ? 'Miễn cọc (sân bảo lãnh)' : ($keo->laKeoTruoc() ? 'Mỗi đội cọc ' : 'Bên B cọc ').$tien($keo->coc_moi_ben) }}
                @else
                    Cần {{ $keo->so_nguoi_can }} người · {{ $tien($keo->tien_moi_nguoi) }}/người
                    @if ($keo->tien_coc_ca_nhan) <br>Cọc {{ $tien($keo->tien_coc_ca_nhan) }} @endif
                @endif
            </div>
        </div>
        @if ($keo->mo_ta) <p class="small mt-2 mb-0" style="white-space: pre-line">{{ $keo->mo_ta }}</p> @endif

        @if ($keo->trang_thai === 'cho_coc')
            <div class="alert alert-warning small py-2 mt-3 mb-0"><i class="bi bi-lock"></i> <strong>Đã chốt kèo, sân đã được giữ.</strong>
                Mỗi đội cọc {{ $tien($keo->coc_moi_ben) }} trước <strong>{{ $keo->han_coc_luc->format('H:i d/m/Y') }}</strong>.
                Đội không cọc kịp: kèo huỷ, đội đó bị trừ uy tín, đội đã cọc được hoàn tiền.</div>
        @endif
        @if (! $keo->cong_khai && ($keo->laDaiDien($u ?? new \App\Models\User) || $laSan))
            <div class="input-group input-group-sm mt-3">
                <span class="input-group-text">Link mời</span>
                <input id="linkMoi" class="form-control" readonly value="{{ route('keo.show', ['keo' => $keo, 'ma' => $keo->ma_moi]) }}">
                <button type="button" class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('linkMoi').value); this.textContent='Đã chép'">Chép</button>
            </div>
            <div class="form-text">Kèo riêng không hiện ở danh sách. Gửi link này cho đội đối thủ qua Zalo/Messenger.</div>
        @endif

        {{-- Kèo trước: xin nhận kèo --}}
        @if ($keo->laKeoTruoc() && $keo->trang_thai === 'dang_tim' && ! $daToiGio && $u?->laKhach() && ! $benToi && ! $laChuKeo)
            <div class="mt-3">
                @if ($xinCuaToi?->trang_thai === 'cho_duyet')
                    <div class="alert alert-info py-2 small mb-0">Đã gửi yêu cầu nhận kèo với đội <strong>{{ $xinCuaToi->doi?->ten }}</strong>. Chờ duyệt.</div>
                @elseif ($doiCuaToi->isEmpty())
                    <div class="alert alert-warning py-2 small mb-0">Cần có đội để nhận kèo đội đấu đội. <a href="{{ route('khach.doi.create') }}">Tạo đội</a></div>
                @else
                    <form method="POST" action="{{ route('khach.keo.xin', $keo) }}" class="d-flex flex-wrap gap-2">@csrf
                        <select name="doi_id" class="form-select form-select-sm" style="max-width: 220px">
                            @foreach ($doiCuaToi as $doi) <option value="{{ $doi->id }}">{{ $doi->ten }}</option> @endforeach
                        </select>
                        <input name="loi_nhan" class="form-control form-control-sm flex-grow-1" style="min-width: 160px" placeholder="Lời nhắn (tuỳ chọn)">
                        <button class="btn btn-danger btn-sm">{{ $keo->dai_dien_a_id ? 'Xin nhận kèo' : 'Xin vào ô kèo' }}</button>
                    </form>
                    <div class="form-text">Chưa mất tiền. Được duyệt đủ hai đội thì mới cọc {{ $tien($keo->coc_moi_ben) }}.</div>
                @endif
            </div>
        @endif

        {{-- Hành động tham gia --}}
        <div class="mt-3">
            @guest
                <a href="{{ route('login') }}" class="btn btn-success w-100">Đăng nhập để tham gia</a>
            @else
                @if ($cocCho)
                    <a href="{{ route('keo.coc', $cocCho) }}" class="btn btn-warning w-100">Quét QR cọc {{ $tien($cocCho->so_tien) }} để giữ chỗ</a>
                @elseif (! $benToi && $keo->trang_thai === 'dang_tim' && ! $daToiGio && $u->laKhach())
                    @if ($keo->laDoiDau() && ! $keo->dai_dien_b_id && ! $keo->laKeoTruoc())
                        <form method="POST" action="{{ route('khach.keo.nhan', $keo) }}" class="d-flex gap-2">@csrf
                            @if ($doiCuaToi->isNotEmpty())
                                <select name="doi_id" class="form-select" style="max-width: 220px">
                                    @foreach ($doiCuaToi as $doi) <option value="{{ $doi->id }}">{{ $doi->ten }}</option> @endforeach
                                </select>
                            @endif
                            <button class="btn btn-danger flex-grow-1">Nhận kèo · cọc {{ $tien($keo->coc_moi_ben) }}</button></form>
                    @elseif (! $keo->laDoiDau())
                        <form method="POST" action="{{ route('khach.keo.tham-gia', $keo) }}">@csrf
                            <button class="btn btn-primary w-100">Tham gia{{ $keo->tien_coc_ca_nhan ? ' · cọc '.$tien($keo->tien_coc_ca_nhan) : '' }}</button></form>
                    @endif
                @endif
            @endguest
        </div>
    </div></div>

    {{-- Yêu cầu nhận kèo (chủ kèo / sân duyệt) --}}
    @if ($keo->laKeoTruoc() && $keo->trang_thai === 'dang_tim' && ($laChuKeo || $laSan))
        @php $choDuyet = $keo->xinKeos->where('trang_thai', 'cho_duyet'); @endphp
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">Đội xin {{ $keo->dai_dien_a_id ? 'nhận kèo' : 'vào ô kèo' }} <span class="badge bg-danger">{{ $choDuyet->count() }}</span></h2>
            @forelse ($choDuyet as $x)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-2 small">
                    <a href="{{ $x->doi ? route('doi.show', $x->doi) : '#' }}" class="d-flex align-items-center gap-2 text-decoration-none text-body" target="_blank">
                        <img src="{{ $x->doi?->logoUrl() }}" width="36" height="36" class="rounded-circle object-fit-cover" alt="">
                        <span><strong>{{ $x->doi?->ten }}</strong> <i class="bi bi-box-arrow-up-right small"></i><br>
                            <span class="text-muted">{{ $x->user->name }} · uy tín {{ $x->user->diem_uy_tin }} · {{ $x->user->uyTinNhan() }}</span>
                            @if ($x->loi_nhan) <br><em>"{{ $x->loi_nhan }}"</em> @endif</span>
                    </a>
                    <span class="d-flex gap-1">
                        <form method="POST" action="{{ route('keo.duyet-xin', $x) }}" onsubmit="return confirm('{{ $keo->dai_dien_a_id ? 'Nhận đội này? Kèo sẽ CHỐT: web giữ sân và hai đội phải cọc.' : 'Nhận đội này làm bên A?' }}')">@csrf @method('PATCH')
                            <input type="hidden" name="dong_y" value="1"><button class="btn btn-sm btn-success">Nhận</button></form>
                        <form method="POST" action="{{ route('keo.duyet-xin', $x) }}">@csrf @method('PATCH')
                            <input type="hidden" name="dong_y" value="0"><button class="btn btn-sm btn-outline-secondary">Từ chối</button></form>
                    </span>
                </div>
            @empty
                <div class="small text-muted">Chưa có đội nào xin. {{ $keo->cong_khai ? 'Kèo đang hiện ở trang Cáp kèo.' : 'Gửi link mời cho đội đối thủ.' }}</div>
            @endforelse
            @if ($laSan)
                <form method="POST" action="{{ route('keo.them-doi', $keo) }}" class="d-flex gap-1 mt-2">@csrf
                    <input name="doi" list="dsDoi" class="form-control form-control-sm" placeholder="Sân thêm thẳng một đội: gõ tên đội">
                    <datalist id="dsDoi">@foreach ($tatCaDoi as $doi) <option value="{{ $doi->ten }}"> @endforeach</datalist>
                    <button class="btn btn-sm btn-outline-success text-nowrap">Thêm đội</button>
                </form>
            @endif
        </div></div>
    @endif

    {{-- Thành viên --}}
    <div class="row g-3 mb-3">
        @foreach ($keo->laDoiDau() ? ['a' => $keo->tenBen('a').' (treo kèo)', 'b' => $keo->tenBen('b').' (nhận kèo)'] : ['a' => 'Người chơi'] as $ben => $tenBen)
            @php $ds = $keo->thamGias->where('ben', $ben)->whereIn('trang_thai', ['cho_coc', 'da_xac_nhan']); @endphp
            <div class="col-md-{{ $keo->laDoiDau() ? 6 : 12 }}">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0">
                            @if ($keo->laDoiDau() && $doiBen[$ben])
                                <a href="{{ route('doi.show', $doiBen[$ben]) }}" class="text-decoration-none d-inline-flex align-items-center gap-1">
                                    <img src="{{ $doiBen[$ben]->logoUrl() }}" width="26" height="26" class="rounded-circle object-fit-cover" alt="">{{ $doiBen[$ben]->ten }}</a>
                                <span class="text-muted small fw-normal">· {{ $ben === 'a' ? 'đội treo kèo' : 'đội nhận kèo' }}</span>
                            @else
                                {{ $tenBen }}
                            @endif
                        </h2>
                        @if ($keo->laDoiDau() && $keo->{$ben.'_bao_ket_qua'})
                            <span class="small">Báo: <strong>{{ $keo->nhanKetQua($keo->{$ben.'_bao_ket_qua'}) }}</strong></span>
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
                                    <form method="POST" action="{{ route('quan-ly.keo.mien-phat', $tg) }}" onsubmit="const l = prompt('Lý do đề nghị miễn phạt (admin sẽ xem):'); if (l === null) return false; this.ly_do.value = l;">@csrf @method('PATCH')
                                        <input type="hidden" name="ly_do">
                                        <button class="btn btn-sm btn-outline-secondary py-0" title="Người này thực tế có đến — gửi admin duyệt">Đề nghị miễn phạt</button></form>
                                @endif
                                @if ($laChuSan && $tg->user->biHanChe())
                                    <form method="POST" action="{{ route('quan-ly.han-che.go', $tg->user) }}" onsubmit="const l = prompt('Lý do đề nghị gỡ hạn chế (admin sẽ xem):'); if (l === null) return false; this.ly_do.value = l;">@csrf @method('PATCH')
                                        <input type="hidden" name="ly_do">
                                        <button class="btn btn-sm btn-outline-primary py-0">Đề nghị gỡ hạn chế</button></form>
                                @endif
                            </span>
                        </div>
                    @empty
                        <div class="small text-muted">{{ $ben === 'b' ? 'Đang chờ đội nhận kèo...' : ($keo->laKeoTruoc() ? 'Ô kèo của sân — chờ đội xin vào.' : ($keo->nguoiTao->laKhach() ? 'Chưa có ai.' : 'Khách đặt tại sân — chủ sân đại diện.')) }}</div>
                    @endforelse

                    @if ($keo->laDoiDau() && $u && $keo->laDaiDien($u, $ben) && in_array($keo->trang_thai, ['dang_tim', 'cho_coc', 'da_chot'], true) && ! $daToiGio)
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
            <p class="small text-muted mb-2">Hạn xác nhận: <strong>{{ $keo->han_xac_nhan_luc->format('H:i d/m/Y') }}</strong>. Hai bên báo khớp nhau thì kèo hoàn tất; bấm nhầm thì báo lại.</p>
            @if ($laDaiDien)
                <form method="POST" action="{{ route('keo.ket-qua', $keo) }}" class="d-flex flex-wrap gap-2">@csrf
                    @foreach (\App\Models\Keo::KET_QUA as $ma => $nhan)
                        <button name="ket_qua" value="{{ $ma }}" class="btn btn-outline-dark btn-sm">{{ $keo->nhanKetQua($ma) }}</button>
                    @endforeach
                </form>
            @endif
            @if ($laSan)
                <form method="POST" action="{{ route('quan-ly.keo.chot', $keo) }}" class="d-flex flex-wrap gap-2 mt-2 align-items-center">@csrf @method('PATCH')
                    <span class="small fw-semibold">Chủ sân chốt:</span>
                    @foreach (\App\Models\Keo::KET_QUA as $ma => $nhan)
                        <button name="ket_qua" value="{{ $ma }}" class="btn btn-sm btn-dark" onclick="return confirm('Chốt: {{ e($keo->nhanKetQua($ma)) }}?')">{{ $keo->nhanKetQua($ma) }}</button>
                    @endforeach
                </form>
            @endif
        </div></div>
    @endif

    {{-- Quyết toán --}}
    @if ($quyetToan)
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">Kết quả: {{ $keo->nhanKetQua($keo->ket_qua) }} — tiền sân {{ $tien($keo->tien_san) }}</h2>
            <table class="table table-sm small mb-0">
                <thead><tr><th></th><th class="text-end">Phải chịu</th><th class="text-end">Đã cọc</th><th class="text-end">Trả thêm tại sân</th></tr></thead>
                <tbody>
                @foreach (['a' => $keo->tenBen('a'), 'b' => $keo->tenBen('b')] as $ben => $ten)
                    @php $q = $quyetToan[$ben]; @endphp
                    <tr><td>{{ $ten }}</td><td class="text-end">{{ $tien($q['phan']) }}</td><td class="text-end">{{ $tien($q['da_tra']) }}</td>
                        <td class="text-end fw-semibold">{{ $q['con'] >= 0 ? $tien($q['con']) : 'Sân trả lại '.$tien(-$q['con']) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div></div>
    @endif

    @if ($laChuSan && ! in_array($keo->trang_thai, ['hoan_tat', 'da_huy'], true))
        <form method="POST" action="{{ route('quan-ly.keo.huy', $keo) }}" class="d-flex gap-2 justify-content-end mb-2"
              onsubmit="return confirm('Huỷ kèo? Ai đã cọc kèo sẽ được đánh dấu chờ bạn hoàn tiền.')">@csrf @method('PATCH')
            <input name="ly_do" class="form-control form-control-sm" style="max-width: 260px" placeholder="Lý do huỷ (VD: sân hỏng đèn)">
            <button class="btn btn-sm btn-outline-danger text-nowrap">Chủ sân huỷ kèo</button>
        </form>
    @endif
    @if ($u && $keo->laDaiDien($u, 'a') && $keo->trang_thai === 'dang_tim')
        <form method="POST" action="{{ route('khach.keo.huy', $keo) }}" class="text-end" onsubmit="return confirm('Huỷ kèo này?')">@csrf
            <button class="btn btn-link text-danger">Huỷ kèo</button></form>
    @endif
</div>
@endsection
