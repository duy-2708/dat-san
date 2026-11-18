<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ghi đè migration users mặc định của Laravel: giữ các cột Laravel/Fortify cần
 * (name, email, email_verified_at, password, remember_token) và thêm cột nghiệp vụ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            // Email đã chuẩn hoá (bỏ dấu chấm Gmail, bỏ +tag) để chặn tạo nhiều tài khoản từ 1 hộp thư
            $table->string('email_chuan_hoa')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('so_dien_thoai', 15)->nullable()->unique();
            $table->timestamp('sdt_xac_thuc_luc')->nullable();
            $table->string('password')->nullable(); // tài khoản chỉ đăng nhập Google có thể chưa có mật khẩu

            $table->enum('vai_tro', ['admin', 'chu_san', 'nhan_vien', 'khach'])->default('khach')->index();
            // Nhân viên thuộc chủ sân nào
            $table->foreignId('chu_san_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('trang_thai', ['hoat_dong', 'bi_khoa'])->default('hoat_dong');
            $table->string('ly_do_khoa')->nullable();

            $table->string('google_id')->nullable()->unique();

            // Hồ sơ cầu thủ
            $table->string('anh_dai_dien')->nullable();
            $table->string('vi_tri_so_truong', 30)->nullable();

            // Uy tín (giá trị đếm sẵn để hiển thị nhanh; lịch sử chi tiết ở bảng lich_su_uy_tin)
            $table->integer('diem_uy_tin')->default(100);
            $table->unsignedInteger('so_tran_tham_gia')->default(0);
            $table->unsignedInteger('so_tran_vang')->default(0);
            $table->timestamp('khoa_keo_den')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
