<?php

namespace App\Http\Controllers;

use App\Models\XaPhuong;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API nhỏ cho ô chọn Tỉnh → Xã/Phường (dùng ở form sân và bộ lọc trang chủ). */
class DiaChiController extends Controller
{
    public function xaPhuong(Request $request): JsonResponse
    {
        $tinhId = (int) $request->query('tinh_thanh_id');

        return response()->json(
            XaPhuong::where('tinh_thanh_id', $tinhId)->orderBy('ten')->get(['id', 'ten'])
        );
    }
}
