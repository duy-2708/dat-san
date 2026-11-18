<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('san', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chu_san_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('xa_phuong_id')->constrained('xa_phuong')->restrictOnDelete();
            $table->string('dia_chi_chi_tiet'); // số nhà, đường
            $table->string('ten', 100);
            $table->string('biet_danh', 100)->nullable();
            $table->string('slug', 150)->unique();
            $table->unsignedTinyInteger('loai_san'); // 5, 7, 11
            $table->text('mo_ta')->nullable();
            $table->json('tien_ich')->nullable();   // ["wifi","gui_xe","nuoc_uong",...]
            $table->time('gio_mo_cua')->default('05:00');
            $table->time('gio_dong_cua')->default('23:00');
            $table->decimal('vi_do', 10, 7)->nullable();
            $table->decimal('kinh_do', 10, 7)->nullable();
            $table->enum('trang_thai', ['hoat_dong', 'tam_ngung'])->default('hoat_dong');
            $table->timestamps();

            $table->index(['xa_phuong_id', 'loai_san', 'trang_thai']);
        });

        Schema::create('anh_san', function (Blueprint $table) {
            $table->id();
            $table->foreignId('san_id')->constrained('san')->cascadeOnDelete();
            $table->string('duong_dan');
            $table->unsignedSmallInteger('thu_tu')->default(0);
            $table->timestamps();
        });

        // Sân 11 (cha) ghép từ các sân 5/7 (con)
        Schema::create('san_ghep', function (Blueprint $table) {
            // RESTRICT (không CASCADE) vì MySQL không cho CHECK trên cột có hành động FK CASCADE
            $table->foreignId('san_cha_id')->constrained('san')->restrictOnDelete();
            $table->foreignId('san_con_id')->constrained('san')->restrictOnDelete();
            $table->primary(['san_cha_id', 'san_con_id']);
        });

        Schema::create('khung_gio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('san_id')->constrained('san')->cascadeOnDelete();
            $table->enum('loai_ngay', ['ngay_thuong', 'cuoi_tuan', 'ngay_le']);
            $table->time('gio_bat_dau');
            $table->time('gio_ket_thuc');
            $table->decimal('gia', 12, 0);
            $table->boolean('la_gio_vang')->default(false);
            $table->boolean('dang_ap_dung')->default(true);
            $table->timestamps();

            $table->unique(['san_id', 'loai_ngay', 'gio_bat_dau']);
        });

        // chu_san_id NULL = ngày lễ chung toàn hệ thống (admin nhập)
        Schema::create('ngay_le', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chu_san_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->date('ngay');
            $table->string('ten', 100);
            $table->unique(['chu_san_id', 'ngay']);
            $table->index('ngay');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE san ADD CONSTRAINT chk_loai_san CHECK (loai_san IN (5, 7, 11))');
            DB::statement('ALTER TABLE san_ghep ADD CONSTRAINT chk_san_ghep CHECK (san_cha_id <> san_con_id)');
            DB::statement('ALTER TABLE khung_gio ADD CONSTRAINT chk_khung_gio_gia CHECK (gia >= 0)');
            DB::statement('ALTER TABLE khung_gio ADD CONSTRAINT chk_khung_gio_gio CHECK (gio_ket_thuc > gio_bat_dau)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ngay_le');
        Schema::dropIfExists('khung_gio');
        Schema::dropIfExists('san_ghep');
        Schema::dropIfExists('anh_san');
        Schema::dropIfExists('san');
    }
};
