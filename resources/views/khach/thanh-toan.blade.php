@extends('layouts.app')
@section('title', 'Thanh toán')

@section('content')
@php $tien = fn ($so) => number_format((int) $so, 0, ',', '.').'đ'; @endphp
<h1 class="h4 mb-3"><i class="bi bi-wallet2"></i> Thanh toán & việc cần làm</h1>

<h2 class="h6">Cần làm ngay
    @if ($viec->isNotEmpty()) <span class="badge rounded-pill bg-danger">{{ $viec->count() }}</span> @endif</h2>
@forelse ($viec as $v)
    <div class="card border-0 shadow-sm mb-2 border-start border-4 border-danger">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="badge bg-danger">{{ $v['nhan'] }}</span>
                    <span class="small">{{ $v['mo_ta'] }}</span>
                    @if ($v['so_tien']) <strong class="ms-1">{{ $v['so_tien'] }}</strong> @endif
                </div>
                @if ($v['loai'] !== 'ket_qua')
                    <a href="{{ $v['url'] }}" class="btn btn-sm btn-success">{{ $v['nut'] }}</a>
                @else
                    @php $k = $v['keo']; @endphp
                    <form method="POST" action="{{ route('keo.ket-qua', $k) }}" class="d-flex flex-wrap gap-1"
                          onsubmit="return confirm('Xác nhận: ' + event.submitter.innerText + '?')">@csrf
                        @foreach (\App\Models\Keo::KET_QUA as $ma => $nhan)
                            <button name="ket_qua" value="{{ $ma }}" class="btn btn-sm {{ $ma === 'hoa' ? 'btn-secondary' : 'btn-dark' }}">{{ $k->nhanKetQua($ma) }}</button>
                        @endforeach
                        <a href="{{ $v['url'] }}" class="btn btn-sm btn-link">Xem kèo</a>
                    </form>
                @endif
            </div>
            @if ($v['loai'] === 'ket_qua')
                <div class="small text-muted mt-1">{{ $k->tenBen('a') }} là đội treo kèo, {{ $k->tenBen('b') }} là đội nhận kèo. Hai đội báo khớp nhau thì kèo hoàn tất.</div>
            @endif
        </div>
    </div>
@empty
    <div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle"></i> Không có khoản nào cần thanh toán hay xác nhận.</div>
@endforelse

<h2 class="h6 mt-4">Còn phải trả tại sân</h2>
@php $conTra = $donDaDat->filter(fn ($d) => $d->conLaiPhai() > 0 && $d->ketThucLuc()->isFuture())->sortBy(fn ($d) => $d->batDauLuc()); @endphp
@forelse ($conTra as $d)
    @php $homNay = $d->batDauLuc()->isToday(); @endphp
    <div class="small bg-white rounded shadow-sm p-2 mb-1 d-flex flex-wrap justify-content-between gap-2 {{ $homNay ? 'border border-2 border-danger' : '' }}">
        <span>@if ($homNay) <span class="badge bg-danger">⚽ Hôm nay {{ $d->batDauLuc()->format('H:i') }}</span> @endif
            {{ $d->moTaNgan() }} · mã {{ $d->ma_don }}</span>
        <strong class="{{ $homNay ? 'text-danger' : '' }}">{{ $homNay ? 'Mang theo ' : '' }}{{ $tien($d->conLaiPhai()) }}</strong>
    </div>
@empty
    <div class="small text-muted mb-2">Không có.</div>
@endforelse

<h2 class="h6 mt-4">Lịch sử thanh toán</h2>
<div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-sm small align-middle mb-0">
        <thead class="table-light"><tr><th>Ngày</th><th>Khoản</th><th>Nội dung</th><th class="text-end">Số tiền</th><th>Trạng thái</th></tr></thead>
        <tbody>
        @forelse ($lichSu as $g)
            <tr>
                <td class="text-nowrap">{{ ($g->thanh_toan_luc ?? $g->created_at)->format('d/m/Y H:i') }}</td>
                <td>{{ ['coc_dat_san' => 'Cọc đặt sân', 'coc_keo_doi' => 'Cọc kèo đội', 'coc_keo_le' => 'Cọc kèo ghép'][$g->loai] ?? $g->loai }}
                    @if ($g->datSan) <div class="text-muted">{{ $g->datSan->san?->ten }}</div> @endif</td>
                <td>{{ $g->noi_dung_ck }}</td>
                <td class="text-end">{{ $tien($g->so_tien) }}</td>
                <td>
                    @switch($g->trang_thai)
                        @case('thanh_cong') <span class="badge bg-success">Đã nhận</span> @break
                        @case('het_han') <span class="badge bg-secondary">Hết hạn</span> @break
                        @default <span class="badge bg-secondary">Đã huỷ</span>
                    @endswitch
                    @if (($g->xu_ly_hoan ?? 'khong') === 'cho_hoan') <span class="badge bg-warning text-dark">Chờ hoàn</span>
                    @elseif (($g->xu_ly_hoan ?? 'khong') === 'da_hoan') <span class="badge bg-info text-dark">Đã hoàn</span> @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-3">Chưa có giao dịch.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-2">{{ $lichSu->links() }}</div>
@endsection
