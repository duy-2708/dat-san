<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chuoi_co_dinh', function (Blueprint $t) {
            $t->id();
            $t->foreignId('san_id')->constrained('san')->restrictOnDelete();
            $t->unsignedTinyInteger('thu');
            $t->unsignedBigInteger('khung_gio_id');
            $t->string('ten', 100);
            $t->string('sdt', 20);
            $t->unsignedSmallInteger('thoi_luong')->nullable();
            $t->foreignId('nguoi_tao_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('dat_san', function (Blueprint $t) {
            $t->unsignedBigInteger('chuoi_co_dinh_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('dat_san', fn (Blueprint $t) => $t->dropColumn('chuoi_co_dinh_id'));
        Schema::dropIfExists('chuoi_co_dinh');
    }
};
