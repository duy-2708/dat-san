<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** chu_san_id = NULL: ngày lễ chung toàn hệ thống; có giá trị: ngày riêng của chủ sân đó. */
class NgayLe extends Model
{
    protected $table = 'ngay_le';

    public $timestamps = false;

    protected $fillable = ['ngay', 'ten'];

    // Không cast 'date': giữ chuỗi 'Y-m-d' để so khớp chính xác với cột DATE trên cả MySQL lẫn SQLite (test)
}
