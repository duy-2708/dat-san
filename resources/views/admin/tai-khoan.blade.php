@extends('layouts.app')
@section('title', 'Tài khoản')

@section('content')
<h1 class="h4 mb-3">Tất cả tài khoản</h1>

<form class="row g-2 mb-3" method="GET">
    <div class="col-12 col-md-6"><input name="q" value="{{ $tuKhoa }}" class="form-control" placeholder="Tìm tên, email, SĐT"></div>
    <div class="col-8 col-md-4">
        <select name="vai_tro" class="form-select">
            <option value="">Mọi vai trò</option>
            @foreach (\App\Models\User::TEN_VAI_TRO as $ma => $ten)
                <option value="{{ $ma }}" @selected($vaiTro === $ma)>{{ $ten }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-4 col-md-2"><button class="btn btn-outline-secondary w-100">Lọc</button></div>
</form>

<div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Tên</th><th>Vai trò</th><th>Uy tín</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td>{{ $u->name }}<div class="small text-muted">{{ $u->email }} · {{ $u->so_dien_thoai ?? 'chưa có SĐT' }}</div></td>
                <td>{{ $u->tenVaiTro() }}</td>
                <td>{{ $u->diem_uy_tin }}</td>
                <td>
                    @if ($u->trang_thai === 'bi_khoa') <span class="badge bg-danger" title="{{ $u->ly_do_khoa }}">Bị khoá</span>
                    @else <span class="badge bg-success">Hoạt động</span> @endif
                </td>
                <td class="text-end">
                    @if ($u->biHanChe())
                        <form method="POST" action="{{ route('admin.tai-khoan.go-han-che', $u) }}" class="d-inline">@csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-primary" title="{{ $u->ly_do_han_che }}">Gỡ hạn chế</button></form>
                    @endif
                    @include('admin.nut-khoa', ['u' => $u])
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
