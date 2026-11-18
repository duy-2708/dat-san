<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goi_thue_bao', function (Blueprint $table) {
            $table->id();
            $table->string('ten', 100);
            $table->decimal('gia_thang', 12, 0);
            $table->unsignedSmallInteger('so_san_toi_da');
            $table->unsignedSmallInteger('so_nhan_vien_toi_da');
            $table->boolean('cho_phep_cong_tu_dong')->default(false); // được dùng PayOS (cách B)
            $table->text('mo_ta')->nullable();
            $table->boolean('dang_ban')->default(true);
            $table->timestamps();
        });

        // 1–1 với users có vai_tro = chu_san. Gom: thông tin cơ sở, trạng thái gói, cài đặt thanh toán, chính sách huỷ.
        Schema::create('ho_so_chu_san', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('ten_co_so', 150);
            $table->string('sdt_lien_he', 15)->nullable();

            $table->foreignId('goi_thue_bao_id')->nullable()->constrained('goi_thue_bao')->restrictOnDelete();
            $table->timestamp('het_han_luc')->nullable()->index();
            $table->boolean('bi_khoa_do_het_han')->default(false);

            // Cài đặt thanh toán
            $table->enum('phuong_thuc_tt', ['vietqr', 'payos'])->default('vietqr');
            $table->string('ngan_hang_bin', 10)->nullable();   // mã BIN NAPAS, VD 970436 = Vietcombank
            $table->string('so_tai_khoan', 30)->nullable();
            $table->string('ten_chu_tai_khoan', 100)->nullable();
            $table->text('payos_client_id')->nullable();    // 3 cột này lưu MÃ HOÁ (cast encrypted)
            $table->text('payos_api_key')->nullable();
            $table->text('payos_checksum_key')->nullable();
            $table->string('webhook_token', 64)->nullable()->unique(); // URL webhook riêng cho từng chủ sân

            // Đặt sân & huỷ
            $table->unsignedTinyInteger('ty_le_coc')->default(30);
            $table->unsignedSmallInteger('phut_giu_cho')->default(15);
            $table->unsignedSmallInteger('gio_huy_hoan_coc')->default(24); // huỷ trước N giờ thì hoàn cọc

            $table->timestamps();
        });

        Schema::create('thue_bao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chu_san_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('goi_thue_bao_id')->constrained('goi_thue_bao')->restrictOnDelete();
            $table->unsignedSmallInteger('so_thang');
            $table->decimal('so_tien', 12, 0);
            $table->timestamp('tu_ngay');
            $table->timestamp('den_ngay');
            $table->enum('trang_thai', ['cho_thanh_toan', 'da_thanh_toan', 'da_huy'])->default('cho_thanh_toan');
            $table->foreignId('xac_nhan_boi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('xac_nhan_luc')->nullable();
            $table->string('ghi_chu')->nullable();
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE goi_thue_bao ADD CONSTRAINT chk_goi_gia CHECK (gia_thang >= 0)');
            DB::statement('ALTER TABLE ho_so_chu_san ADD CONSTRAINT chk_ty_le_coc CHECK (ty_le_coc BETWEEN 0 AND 100)');
            DB::statement('ALTER TABLE thue_bao ADD CONSTRAINT chk_thue_bao_ngay CHECK (den_ngay > tu_ngay)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('thue_bao');
        Schema::dropIfExists('ho_so_chu_san');
        Schema::dropIfExists('goi_thue_bao');
    }
};
