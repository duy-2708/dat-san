<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Theo dõi việc hoàn tiền cho từng khoản cọc (chủ yếu cọc kèo):
 * kèo bị huỷ / lịch sân bị huỷ / tiền về sau khi đã nhả chỗ → chủ sân phải chuyển trả rồi bấm "Đã hoàn".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('giao_dich', function (Blueprint $table) {
            $table->enum('xu_ly_hoan', ['khong', 'cho_hoan', 'da_hoan'])->default('khong')->after('trang_thai')->index();
            $table->timestamp('da_hoan_luc')->nullable()->after('xu_ly_hoan');
        });
    }

    public function down(): void
    {
        Schema::table('giao_dich', function (Blueprint $table) {
            $table->dropColumn(['xu_ly_hoan', 'da_hoan_luc']);
        });
    }
};
