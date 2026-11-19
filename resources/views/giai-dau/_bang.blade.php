{{-- Bảng xếp hạng + lịch/kết quả dùng chung cho trang công khai và trang quản lý --}}
@if ($g->the_thuc === 'vong_tron' && $bxh->isNotEmpty() && $g->trang_thai !== 'mo_dang_ky')
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h2 class="h6">Bảng xếp hạng</h2>
        <div class="table-responsive"><table class="table table-sm align-middle small mb-0">
            <thead class="table-light"><tr><th>#</th><th>Đội</th><th class="text-center">Tr</th><th class="text-center">T</th><th class="text-center">H</th><th class="text-center">B</th><th class="text-center">HS</th><th class="text-center">Đ</th></tr></thead>
            <tbody>
            @foreach ($bxh as $i => $r)
                <tr><td>{{ $i + 1 }}</td>
                    <td><a href="{{ route('doi.show', $r['doi']) }}" class="d-flex align-items-center gap-1 text-decoration-none">
                        <img src="{{ $r['doi']->logoUrl() }}" width="22" height="22" class="rounded-circle object-fit-cover" alt="">{{ $r['doi']->ten }}</a></td>
                    <td class="text-center">{{ $r['tran'] }}</td><td class="text-center">{{ $r['thang'] }}</td><td class="text-center">{{ $r['hoa'] }}</td>
                    <td class="text-center">{{ $r['thua'] }}</td><td class="text-center">{{ $r['hs'] > 0 ? '+' : '' }}{{ $r['hs'] }}</td><td class="text-center fw-bold">{{ $r['diem'] }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </div></div>
@endif

@if ($g->tranDaus->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h2 class="h6">Lịch & kết quả</h2>
        @foreach ($g->tranDaus->groupBy('vong') as $vong => $ds)
            <div class="small fw-semibold text-muted mt-3 mb-1 text-uppercase">{{ $vong }}</div>
            @foreach ($ds as $t)
                @php $xong = $t->trang_thai === 'da_xong'; $sua = ! empty($choSua) && $g->trang_thai === 'dang_dien_ra'; @endphp
                <div class="border rounded p-2 mb-2 {{ $xong ? 'bg-light' : 'bg-white' }}">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>{{ $t->thoi_gian?->format('d/m/Y H:i') ?? 'Chưa xếp giờ' }}</span>
                        <span class="badge {{ $xong ? 'bg-success' : 'bg-secondary' }}">{{ $xong ? 'Đã xong' : 'Chưa đá' }}</span>
                    </div>
                    @if ($sua && (! $xong || request('sua') == $t->id))
                        <form method="POST" action="{{ route('quan-ly.giai-dau.ket-qua', $t) }}" class="d-flex align-items-center gap-2 flex-wrap">@csrf @method('PATCH')
                            <span class="flex-grow-1 text-end fw-semibold" style="min-width: 110px">{{ $t->doiNha->ten }}</span>
                            <input name="ban_thang_nha" type="number" inputmode="numeric" min="0" max="99" value="{{ $t->ban_thang_nha }}" required
                                   class="form-control form-control-lg text-center fw-bold" style="width: 70px" aria-label="Bàn {{ $t->doiNha->ten }}">
                            <span class="fw-bold">–</span>
                            <input name="ban_thang_khach" type="number" inputmode="numeric" min="0" max="99" value="{{ $t->ban_thang_khach }}" required
                                   class="form-control form-control-lg text-center fw-bold" style="width: 70px" aria-label="Bàn {{ $t->doiKhach->ten }}">
                            <span class="flex-grow-1 fw-semibold" style="min-width: 110px">{{ $t->doiKhach->ten }}</span>
                            <button class="btn btn-success w-100 mt-1">Lưu tỉ số</button>
                        </form>
                    @else
                        <div class="d-flex align-items-center gap-2">
                            <span class="flex-grow-1 text-end fw-semibold">{{ $t->doiNha->ten }}</span>
                            <span class="fs-4 fw-bold px-2">{{ $xong ? $t->ban_thang_nha.' – '.$t->ban_thang_khach : 'vs' }}</span>
                            <span class="flex-grow-1 fw-semibold">{{ $t->doiKhach->ten }}</span>
                        </div>
                        @if ($sua && $xong)
                            <div class="text-end"><a href="?sua={{ $t->id }}" class="small">Sửa tỉ số</a></div>
                        @endif
                    @endif
                </div>
            @endforeach
        @endforeach
    </div></div>
@endif
