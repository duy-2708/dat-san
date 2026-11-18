<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lớp thanh toán chung cho cọc đặt sân và cọc kèo, dùng được cho cả VietQR thủ công (cách A)
 * và cổng tự động (cách B: PayOS, SePay...). Website KHÔNG giữ tiền: tiền vào thẳng tài khoản chủ sân.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giao_dich', function (Blueprint $table) {
            $table->id(); // dùng luôn làm orderCode (số) gửi PayOS
            $table->enum('loai', ['coc_dat_san', 'coc_keo_doi', 'coc_keo_le']);
            $table->foreignId('dat_san_id')->nullable()->constrained('dat_san')->restrictOnDelete();
            $table->foreignId('tham_gia_keo_id')->nullable()->constrained('tham_gia_keo')->restrictOnDelete(); // cọc người lẻ
            $table->foreignId('keo_id')->nullable()->constrained('keo')->restrictOnDelete();                   // cọc 15% của 1 bên
            $table->enum('ben', ['a', 'b'])->nullable();
            $table->foreignId('chu_san_id')->constrained('users')->restrictOnDelete(); // người nhận tiền
            $table->foreignId('nguoi_tra_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('phuong_thuc', ['vietqr', 'payos', 'tien_mat']);
            $table->string('noi_dung_ck', 30)->unique();       // VD DS000123 / DK000045
            $table->decimal('so_tien', 12, 0);
            $table->enum('trang_thai', ['cho_thanh_toan', 'thanh_cong', 'het_han', 'da_huy'])->default('cho_thanh_toan');

            $table->string('ma_cong', 100)->nullable();          // paymentLinkId của cổng
            $table->string('ma_tham_chieu', 100)->nullable();    // mã giao dịch ngân hàng cổng trả về
            $table->json('du_lieu_cong')->nullable();
            $table->timestamp('thanh_toan_luc')->nullable();
            $table->foreignId('xac_nhan_boi_id')->nullable()->constrained('users')->nullOnDelete(); // cách A: người bấm xác nhận
            $table->timestamps();

            $table->index(['chu_san_id', 'trang_thai']);
            $table->unique(['keo_id', 'ben']); // mỗi bên chỉ 1 giao dịch cọc
        });

        // Nhật ký webhook — UNIQUE(cong, khoa_su_kien) để webhook gửi lặp không xử lý 2 lần
        Schema::create('webhook_log', function (Blueprint $table) {
            $table->id();
            $table->string('cong', 20);                 // payos, sepay...
            $table->string('khoa_su_kien', 150);        // VD payos:<orderCode>:<reference>
            $table->foreignId('giao_dich_id')->nullable()->constrained('giao_dich')->nullOnDelete();
            $table->boolean('chu_ky_hop_le')->default(false);
            $table->string('ket_qua', 50);              // da_xu_ly, sai_so_tien, khong_tim_thay...
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['cong', 'khoa_su_kien']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE giao_dich ADD CONSTRAINT chk_giao_dich_tien CHECK (so_tien > 0)');
            DB::statement('ALTER TABLE giao_dich ADD CONSTRAINT chk_giao_dich_doi_tuong CHECK (dat_san_id IS NOT NULL OR tham_gia_keo_id IS NOT NULL OR keo_id IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_log');
        Schema::dropIfExists('giao_dich');
    }
};
