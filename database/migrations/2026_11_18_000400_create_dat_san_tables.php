<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dat_san', function (Blueprint $table) {
            $table->id();
            $table->string('ma_don', 20)->unique();          // VD DS000123 — cũng là nội dung chuyển khoản
            $table->foreignId('san_id')->constrained('san')->restrictOnDelete();
            $table->foreignId('khach_id')->nullable()->constrained('users')->restrictOnDelete(); // NULL = khách đặt tại chỗ
            $table->string('ten_khach_tai_cho', 100)->nullable();
            $table->string('sdt_khach_tai_cho', 15)->nullable();

            $table->date('ngay');
            $table->time('gio_bat_dau');
            $table->time('gio_ket_thuc');
            $table->foreignId('khung_gio_id')->nullable()->constrained('khung_gio')->nullOnDelete(); // chỉ để tham khảo

            // Giá CHỐT lúc đặt — chủ sân đổi giá sau này không ảnh hưởng đơn cũ
            $table->decimal('gia_chot', 12, 0);
            $table->decimal('tien_coc', 12, 0);

            $table->enum('nguon', ['online', 'tai_cho'])->default('online');
            $table->enum('trang_thai', ['cho_thanh_toan', 'da_dat', 'da_huy'])->default('cho_thanh_toan');
            $table->timestamp('giu_cho_den')->nullable();

            $table->foreignId('nguoi_tao_id')->nullable()->constrained('users')->nullOnDelete();      // nhân viên/chủ sân đặt tại chỗ
            $table->foreignId('xac_nhan_coc_boi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('xac_nhan_coc_luc')->nullable();

            $table->timestamp('huy_luc')->nullable();
            $table->foreignId('huy_boi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ly_do_huy')->nullable();
            $table->enum('xu_ly_coc', ['khong_ap_dung', 'cho_hoan', 'da_hoan', 'mat_coc'])->default('khong_ap_dung');
            $table->timestamp('da_hoan_luc')->nullable();

            $table->timestamp('da_nhac_lich_luc')->nullable();
            $table->string('ghi_chu')->nullable();
            $table->timestamps();

            $table->index(['san_id', 'ngay']);
            $table->index(['trang_thai', 'giu_cho_den']);   // scheduler nhả chỗ quá hạn
            $table->index(['khach_id', 'ngay']);
        });

        // Ô lịch 15 phút: chống đặt trùng ở mức CSDL, kể cả sân ghép (xem docs/00-...md mục 4)
        Schema::create('o_lich', function (Blueprint $table) {
            $table->id();
            $table->foreignId('san_id')->constrained('san')->cascadeOnDelete();
            $table->date('ngay');
            $table->unsignedTinyInteger('o');                // 0..95
            $table->foreignId('dat_san_id')->constrained('dat_san')->cascadeOnDelete();
            $table->boolean('do_san_lien_quan')->default(false); // true = bị khoá vì sân cha/con được đặt

            $table->unique(['san_id', 'ngay', 'o']);
            $table->index('dat_san_id');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE dat_san ADD CONSTRAINT chk_dat_san_tien CHECK (gia_chot >= 0 AND tien_coc >= 0 AND tien_coc <= gia_chot)');
            DB::statement('ALTER TABLE dat_san ADD CONSTRAINT chk_dat_san_gio CHECK (gio_ket_thuc > gio_bat_dau)');
            DB::statement('ALTER TABLE o_lich ADD CONSTRAINT chk_o_lich CHECK (o BETWEEN 0 AND 95)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('o_lich');
        Schema::dropIfExists('dat_san');
    }
};
