<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThongBaoController extends Controller
{
    public function index(Request $request): View
    {
        return view('thong-bao.index', ['ds' => $request->user()->notifications()->paginate(30)]);
    }

    public function mo(Request $request, string $id): RedirectResponse
    {
        $tb = $request->user()->notifications()->findOrFail($id);
        $tb->markAsRead();

        return redirect($tb->data['url'] ?? route('thong-bao.index'));
    }

    public function docHet(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('thanh_cong', 'Đã đánh dấu đọc hết thông báo.');
    }
}
