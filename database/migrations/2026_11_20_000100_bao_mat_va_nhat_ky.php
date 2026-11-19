<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nhật ký thao tác + chỉ mục tăng tốc danh sách kèo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nhat_ky', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('hanh_dong', 80)->index();
            $t->string('chi_tiet', 500)->nullable();
            $t->string('ip', 45)->nullable()->index();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('keo', fn (Blueprint $t) => $t->index(['trang_thai', 'ngay'], 'keo_trang_thai_ngay_index'));
    }

    public function down(): void
    {
        Schema::table('keo', fn (Blueprint $t) => $t->dropIndex('keo_trang_thai_ngay_index'));
        Schema::dropIfExists('nhat_ky');
    }
};
