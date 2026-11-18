<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Đơn vị hành chính mới từ 1/7/2025: Tỉnh/Thành phố → Xã/Phường/Đặc khu (không còn cấp huyện). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tinh_thanh', function (Blueprint $table) {
            $table->id();
            $table->string('ma', 10)->nullable()->unique(); // mã hành chính chính thức (nạp từ file)
            $table->string('ten', 100)->unique();
            $table->enum('loai', ['thanh_pho', 'tinh'])->default('tinh');
            $table->unsignedSmallInteger('thu_tu')->default(0);
        });

        Schema::create('xa_phuong', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tinh_thanh_id')->constrained('tinh_thanh')->restrictOnDelete();
            $table->string('ma', 10)->nullable()->unique();
            $table->string('ten', 100);
            $table->enum('loai', ['phuong', 'xa', 'dac_khu'])->default('phuong');
            $table->unique(['tinh_thanh_id', 'ten']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xa_phuong');
        Schema::dropIfExists('tinh_thanh');
    }
};
