<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hạn chế (khác "khoá tài khoản"): vẫn đăng nhập, xem, khiếu nại được nhưng KHÔNG đặt sân / vào kèo.
 * Dùng khi không xác nhận kết quả kèo trong 60 phút. Chủ sân hoặc admin gỡ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('han_che_luc')->nullable()->after('khoa_keo_den');
            $table->string('ly_do_han_che')->nullable()->after('han_che_luc');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['han_che_luc', 'ly_do_han_che']);
        });
    }
};
