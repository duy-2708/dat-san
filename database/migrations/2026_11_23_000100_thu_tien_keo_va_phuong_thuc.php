<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dat_san', function (Blueprint $t) {
            $t->string('phuong_thuc_thu_tai_san', 10)->nullable()->after('thu_tien_boi_id');
        });

        Schema::create('thu_tien_keo', function (Blueprint $t) {
            $t->id();
            $t->foreignId('keo_id')->constrained('keo')->cascadeOnDelete();
            $t->char('ben', 1);
            $t->string('loai', 10);
            $t->unsignedInteger('so_tien');
            $t->string('phuong_thuc', 10);
            $t->foreignId('nguoi_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('thu_luc');
            $t->timestamps();
            $t->index(['keo_id', 'ben', 'loai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thu_tien_keo');
        Schema::table('dat_san', fn (Blueprint $t) => $t->dropColumn('phuong_thuc_thu_tai_san'));
    }
};
