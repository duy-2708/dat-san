@can('khoaTaiKhoan', $u)
    @if ($u->trang_thai === 'bi_khoa')
        <form method="POST" action="{{ route('admin.tai-khoan.mo-khoa', $u) }}" class="d-inline">
            @csrf @method('PATCH')
            <button class="btn btn-sm btn-outline-success">Mở khoá</button>
        </form>
    @else
        <form method="POST" action="{{ route('admin.tai-khoan.khoa', $u) }}" class="d-inline"
              onsubmit="return confirm('Khoá tài khoản {{ $u->email }}?')">
            @csrf @method('PATCH')
            <button class="btn btn-sm btn-outline-danger">Khoá</button>
        </form>
    @endif
@endcan
