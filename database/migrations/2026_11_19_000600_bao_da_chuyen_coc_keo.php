<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Người cọc kèo bấm "Tôi đã chuyển khoản" (1 lần / khoản cọc). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('giao_dich', function (Blueprint $t) {
            $t->timestamp('bao_da_chuyen_luc')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('giao_dich', fn (Blueprint $t) => $t->dropColumn('bao_da_chuyen_luc'));
    }
};
