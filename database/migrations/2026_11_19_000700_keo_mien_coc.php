<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kèo do sân làm giùm: sân có thể bảo lãnh, miễn cọc 15% cho hai đội. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keo', fn (Blueprint $t) => $t->boolean('mien_coc')->default(false));
    }

    public function down(): void
    {
        Schema::table('keo', fn (Blueprint $t) => $t->dropColumn('mien_coc'));
    }
};
