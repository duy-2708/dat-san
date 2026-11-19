<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chủ sân tự đặt tiền cọc cho từng khung (để trống = tự tính theo tỉ lệ cọc). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khung_gio', fn (Blueprint $t) => $t->unsignedInteger('tien_coc')->nullable()->after('gia'));
    }

    public function down(): void
    {
        Schema::table('khung_gio', fn (Blueprint $t) => $t->dropColumn('tien_coc'));
    }
};
