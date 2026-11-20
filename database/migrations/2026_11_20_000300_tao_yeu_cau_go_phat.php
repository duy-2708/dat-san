<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yeu_cau_go_phat', function (Blueprint $t) {
            $t->id();
            $t->enum('loai', ['mien_phat', 'go_han_che']);
            $t->foreignId('chu_san_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('tham_gia_keo_id')->nullable()->constrained('tham_gia_keo')->nullOnDelete();
            $t->string('ly_do', 300)->nullable();
            $t->enum('trang_thai', ['cho_duyet', 'da_duyet', 'tu_choi'])->default('cho_duyet')->index();
            $t->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('ghi_chu_admin', 300)->nullable();
            $t->timestamp('xu_ly_luc')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yeu_cau_go_phat');
    }
};
