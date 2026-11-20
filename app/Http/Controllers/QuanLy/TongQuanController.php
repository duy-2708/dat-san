<?php

namespace App\Http\Controllers\QuanLy;

use App\Http\Controllers\Controller;
use App\Services\ThongKeThu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TongQuanController extends Controller
{
    public function index(Request $request, ThongKeThu $thongKe): View
    {
        $u = $request->user();
        $thang = rescue(fn () => Carbon::createFromFormat('Y-m', (string) $request->query('thang', now()->format('Y-m')))->startOfMonth(), now()->startOfMonth(), false);

        return view('quan-ly.tong-quan', [
            'thang' => $thang,
            'tk' => $u->laChuSan() ? $thongKe->thang($u->id, $thang) : null,
        ]);
    }
}
