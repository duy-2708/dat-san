<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Kèo trước, đặt sân sau: kèo chưa có đơn đặt (dat_san_id null) mà ghi sân/ngày/khung/thời lượng;
 *   xin nhận kèo → duyệt → chốt thì mới tự đặt sân và mỗi bên cọc 15%.
 * - Kèo riêng (không hiện công khai, vào bằng link có mã mời).
 * - Khách báo "Tôi đã chuyển khoản" (1 lần/đơn), chủ sân "Không thấy tiền" / "Không thanh toán đủ".
 * - Cấm đặt sân & cáp kèo tới một ngày (cam_dat_den).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keo', function (Blueprint $t) {
            $t->foreignId('dat_san_id')->nullable()->change();
            $t->foreignId('san_id')->nullable()->after('dat_san_id')->constrained('san')->nullOnDelete();
            $t->date('ngay')->nullable()->after('san_id');
            $t->foreignId('khung_gio_id')->nullable()->after('ngay')->constrained('khung_gio')->nullOnDelete();
            $t->unsignedSmallInteger('thoi_luong')->nullable()->after('khung_gio_id');
            $t->foreignId('dai_dien_a_id')->nullable()->after('nguoi_tao_id')->constrained('users')->nullOnDelete();
            $t->boolean('cong_khai')->default(true);
            $t->string('ma_moi', 20)->nullable()->unique();
            $t->timestamp('han_coc_luc')->nullable();
            $t->enum('trang_thai', ['dang_tim', 'cho_coc', 'da_chot', 'cho_xac_nhan', 'tranh_chap', 'hoan_tat', 'da_huy'])
                ->default('dang_tim')->change();
        });

        Schema::create('xin_keo', function (Blueprint $t) {
            $t->id();
            $t->foreignId('keo_id')->constrained('keo')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('doi_id')->nullable()->constrained('doi')->nullOnDelete();
            $t->enum('trang_thai', ['cho_duyet', 'da_duyet', 'tu_choi', 'da_rut'])->default('cho_duyet');
            $t->string('loi_nhan', 255)->nullable();
            $t->timestamps();
            $t->unique(['keo_id', 'user_id']);
        });

        Schema::table('dat_san', function (Blueprint $t) {
            $t->timestamp('bao_da_chuyen_luc')->nullable();
            $t->boolean('da_nhac_bao_chuyen')->default(false);
            $t->timestamp('khong_tra_du_luc')->nullable();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('cam_dat_den')->nullable();
            $t->string('ly_do_cam_dat')->nullable();
        });

        Schema::table('lich_su_uy_tin', function (Blueprint $t) {
            $t->enum('ly_do', ['vang_mat', 'di_du', 'khong_xac_nhan_ket_qua', 'chu_san_dieu_chinh', 'bung_keo', 'khong_tra_du', 'bao_chuyen_sai'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['cam_dat_den', 'ly_do_cam_dat']));
        Schema::table('dat_san', fn (Blueprint $t) => $t->dropColumn(['bao_da_chuyen_luc', 'da_nhac_bao_chuyen', 'khong_tra_du_luc']));
        Schema::dropIfExists('xin_keo');
        Schema::table('keo', function (Blueprint $t) {
            $t->dropConstrainedForeignId('san_id');
            $t->dropConstrainedForeignId('khung_gio_id');
            $t->dropConstrainedForeignId('dai_dien_a_id');
            $t->dropColumn(['ngay', 'thoi_luong', 'cong_khai', 'ma_moi', 'han_coc_luc']);
        });
    }
};
