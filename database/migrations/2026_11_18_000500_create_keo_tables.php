<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hai loại kèo gắn với 1 ô đã đặt:
 *
 * 1. doi_dau (đội đấu đội): bên A (người đặt sân) tìm bên B. Chọn trước tỉ lệ đội THUA chịu tiền sân:
 *    50 (5/5), 60 (6/4), 70 (7/3), 100 (thua trả hết). Hoà = 5/5.
 *    Chốt kèo: mỗi bên cọc 15% tiền sân. Sau trận CẢ HAI bên phải xác nhận kết quả (được sửa nếu nhầm)
 *    trong 60 phút sau giờ kết thúc. Quá hạn → khoá tài khoản các thành viên theo luật; hai bên báo khác nhau
 *    → tranh chấp, chủ sân/nhân viên chốt.
 *
 * 2. ghep_le: đội thiếu người, người lẻ xin vào bên A; mỗi người trả tien_moi_nguoi, cọc tien_coc_ca_nhan.
 *
 * Vắng mặt: trừ uy tín CẢ ĐỘI vắng; chủ sân có thể miễn phạt từng người đã đến (tham_gia_keo.mien_phat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dat_san_id')->unique()->constrained('dat_san')->restrictOnDelete(); // 1 ô = tối đa 1 kèo
            $table->enum('loai', ['doi_dau', 'ghep_le']);
            $table->foreignId('nguoi_tao_id')->constrained('users')->restrictOnDelete();       // đại diện bên A
            $table->foreignId('doi_a_id')->nullable()->constrained('doi')->nullOnDelete();     // nếu bên A là đội đã tạo
            $table->foreignId('dai_dien_b_id')->nullable()->constrained('users')->restrictOnDelete(); // người nhận kèo
            $table->foreignId('doi_b_id')->nullable()->constrained('doi')->nullOnDelete();
            $table->enum('trinh_do', ['moi_choi', 'trung_binh', 'kha', 'gioi'])->default('trung_binh');
            $table->text('mo_ta')->nullable();

            // --- Đội đấu đội ---
            $table->decimal('tien_san', 12, 0);                          // chụp gia_chot của đơn đặt sân
            $table->unsignedTinyInteger('ty_le_thua')->nullable();       // % tiền sân đội thua chịu: 50/60/70/100
            $table->decimal('coc_moi_ben', 12, 0)->default(0);           // 15% tiền sân

            // --- Ghép lẻ ---
            $table->unsignedTinyInteger('so_nguoi_can')->nullable();
            $table->decimal('tien_moi_nguoi', 12, 0)->default(0);
            $table->decimal('tien_coc_ca_nhan', 12, 0)->default(0);     // 0 = không cọc

            $table->enum('trang_thai', ['dang_tim', 'da_chot', 'cho_xac_nhan', 'tranh_chap', 'hoan_tat', 'da_huy'])
                ->default('dang_tim');

            // Xác nhận kết quả (chỉ đội đấu đội). Mỗi bên chọn, được sửa tới khi hai bên khớp.
            $table->enum('a_bao_ket_qua', ['a_thang', 'b_thang', 'hoa'])->nullable();
            $table->timestamp('a_bao_luc')->nullable();
            $table->enum('b_bao_ket_qua', ['a_thang', 'b_thang', 'hoa'])->nullable();
            $table->timestamp('b_bao_luc')->nullable();
            $table->timestamp('han_xac_nhan_luc')->nullable()->index();  // giờ kết thúc + 60 phút
            $table->enum('ket_qua', ['a_thang', 'b_thang', 'hoa'])->nullable();
            $table->foreignId('ket_qua_chot_boi_id')->nullable()->constrained('users')->nullOnDelete(); // chủ sân/NV xử lý tranh chấp
            $table->timestamp('hoan_tat_luc')->nullable();

            $table->timestamp('diem_danh_luc')->nullable();
            $table->foreignId('diem_danh_boi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trang_thai', 'loai']);
        });

        // Danh sách người của từng bên (đội đấu đội) hoặc người lẻ xin vào (ghép lẻ, luôn ở bên A)
        Schema::create('tham_gia_keo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keo_id')->constrained('keo')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('ben', ['a', 'b']);
            $table->enum('vai_tro', ['dai_dien', 'thanh_vien', 'nguoi_le'])->default('thanh_vien');
            $table->enum('trang_thai', ['cho_coc', 'da_xac_nhan', 'roi_keo', 'bi_huy'])->default('da_xac_nhan');
            $table->enum('diem_danh', ['chua', 'co_mat', 'vang'])->default('chua');
            $table->boolean('mien_phat')->default(false);       // chủ sân can thiệp: người này đã đến, không trừ
            $table->foreignId('mien_phat_boi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('giu_cho_den')->nullable();
            $table->timestamps();

            $table->unique(['keo_id', 'user_id']);   // 1 người không đứng cả hai bên
            $table->index(['user_id', 'diem_danh']);
        });

        Schema::create('lich_su_uy_tin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->smallInteger('thay_doi');
            $table->enum('ly_do', ['vang_mat', 'di_du', 'khong_xac_nhan_ket_qua', 'chu_san_dieu_chinh']);
            $table->string('ghi_chu')->nullable();
            $table->foreignId('keo_id')->nullable()->constrained('keo')->nullOnDelete();
            $table->foreignId('tham_gia_keo_id')->nullable()->constrained('tham_gia_keo')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Đếm "vắng 3 lần trong 30 ngày gần nhất"
            $table->index(['user_id', 'ly_do', 'created_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE keo ADD CONSTRAINT chk_keo_ty_le CHECK (ty_le_thua IS NULL OR ty_le_thua IN (50, 60, 70, 100))');
            DB::statement('ALTER TABLE keo ADD CONSTRAINT chk_keo_tien CHECK (tien_san >= 0 AND coc_moi_ben >= 0 AND tien_moi_nguoi >= 0 AND tien_coc_ca_nhan >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_uy_tin');
        Schema::dropIfExists('tham_gia_keo');
        Schema::dropIfExists('keo');
    }
};
