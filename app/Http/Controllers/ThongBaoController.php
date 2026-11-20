<?php

namespace App\Http\Controllers;

use App\Support\TrinhBayThongBao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ThongBaoController extends Controller
{
    public function index(Request $request): View
    {
        $loc = $request->validate(['loc' => ['nullable', 'in:can,don,keo,khac'], 'ngay' => ['nullable', 'date']]);
        $ds = TrinhBayThongBao::gop($request->user()->notifications()
            ->when($loc['ngay'] ?? null, fn ($q, $v) => $q->whereDate('created_at', $v))->limit(500)->get())
            ->filter(fn ($t) => match ($loc['loc'] ?? null) {
                'can' => $t['can_xu_ly'], 'don', 'keo', 'khac' => $t['nhom'] === $loc['loc'], default => true,
            })->values();
        $trang = LengthAwarePaginator::resolveCurrentPage();
        $phanTrang = new LengthAwarePaginator($ds->forPage($trang, 30), $ds->count(), 30, $trang, ['path' => $request->url(), 'query' => $request->query()]);

        return view('thong-bao.index', ['ds' => $phanTrang, 'loc' => $loc]);
    }

    public function mo(Request $request, string $id): RedirectResponse
    {
        $tb = $request->user()->notifications()->findOrFail($id);
        $khoa = TrinhBayThongBao::mot($tb)['khoa'];
        $request->user()->unreadNotifications()->limit(300)->get()
            ->filter(fn ($n) => TrinhBayThongBao::mot($n)['khoa'] === $khoa)->each->markAsRead();
        $tb->markAsRead();

        return redirect($tb->data['url'] ?? route('thong-bao.index'));
    }

    public function docHet(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('thanh_cong', 'Đã đánh dấu đọc hết thông báo.');
    }
}
