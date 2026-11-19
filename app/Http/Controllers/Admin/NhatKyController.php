<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NhatKy;
use Illuminate\Http\Request;

class NhatKyController extends Controller
{
    public function index(Request $request)
    {
        $loc = $request->validate([
            'tim' => ['nullable', 'string', 'max:100'],
            'hanh_dong' => ['nullable', 'string', 'max:80'],
            'ngay' => ['nullable', 'date'],
        ]);

        $ds = NhatKy::with('user:id,name,email,vai_tro')
            ->when($loc['hanh_dong'] ?? null, fn ($q, $v) => $q->where('hanh_dong', 'like', $v.'%'))
            ->when($loc['ngay'] ?? null, fn ($q, $v) => $q->whereDate('created_at', $v))
            ->when($loc['tim'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('ip', $v)->orWhere('chi_tiet', 'like', "%$v%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%$v%")->orWhere('name', 'like', "%$v%"))))
            ->latest('id')->paginate(50)->withQueryString();

        return view('admin.nhat-ky', ['ds' => $ds, 'loc' => $loc]);
    }
}
