<a href="{{ route('thong-bao.mo', $t['id']) }}" class="tb-muc {{ $t['chua_doc'] ? 'chua-doc' : 'da-doc' }}" title="{{ $t['day_du'] }}"
   data-nhom="{{ $t['nhom'] }}" data-can="{{ $t['can_xu_ly'] ? 1 : 0 }}">
    <div class="tb-dong1">
        <i class="bi bi-{{ $t['icon'] }} text-{{ $t['mau'] }}" style="width:1.1rem"></i>
        <span class="tb-tieu-de">{{ $t['tieu_de'] }}</span>
        @if ($t['so_cap_nhat'] > 1) <span class="badge bg-light text-secondary border" style="font-size:.65rem">{{ $t['so_cap_nhat'] }} cập nhật</span> @endif
        <span class="text-muted text-nowrap" style="font-size:.7rem">{{ $t['luc']->diffForHumans(null, true, true) }}</span>
        @if ($t['chua_doc']) <span class="tb-cham"></span> @endif
    </div>
    <div class="tb-dong2">{{ $t['dong2'] }}</div>
</a>
