<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cài đặt hệ thống do admin chỉnh: thời gian miễn phí, tài khoản nhận tiền thuê bao. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cai_dat', function (Blueprint $table) {
            $table->string('khoa', 50)->primary();
            $table->text('gia_tri')->nullable();
            $table->timestamps();
        });

        Schema::table('ho_so_chu_san', function (Blueprint $table) {
            $table->timestamp('da_nhac_het_han_luc')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ho_so_chu_san', fn (Blueprint $t) => $t->dropColumn('da_nhac_het_han_luc'));
        Schema::dropIfExists('cai_dat');
    }
};
