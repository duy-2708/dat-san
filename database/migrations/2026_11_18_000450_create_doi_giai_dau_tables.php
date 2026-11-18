<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doi', function (Blueprint $table) {
            $table->id();
            $table->string('ten', 100);
            $table->string('slug', 150)->unique();
            $table->string('logo')->nullable();
            $table->foreignId('doi_truong_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('tinh_thanh_id')->nullable()->constrained('tinh_thanh')->nullOnDelete();
            $table->text('mo_ta')->nullable();
            $table->timestamps();
        });

        Schema::create('thanh_vien_doi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doi_id')->constrained('doi')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('vai_tro', ['doi_truong', 'thanh_vien'])->default('thanh_vien');
            $table->unsignedTinyInteger('so_ao')->nullable();
            $table->enum('trang_thai', ['cho_duyet', 'dang_choi', 'da_roi'])->default('cho_duyet');
            $table->timestamps();

            $table->unique(['doi_id', 'user_id']);
        });

        Schema::create('giai_dau', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chu_san_id')->constrained('users')->restrictOnDelete();
            $table->string('ten', 150);
            $table->string('slug', 180)->unique();
            $table->unsignedTinyInteger('loai_san');
            $table->enum('the_thuc', ['vong_tron', 'loai_truc_tiep', 'chia_bang'])->default('vong_tron');
            $table->date('tu_ngay');
            $table->date('den_ngay');
            $table->decimal('le_phi', 12, 0)->default(0);
            $table->unsignedSmallInteger('so_doi_toi_da')->default(8);
            $table->text('mo_ta')->nullable();
            $table->enum('trang_thai', ['mo_dang_ky', 'dang_dien_ra', 'ket_thuc', 'da_huy'])->default('mo_dang_ky');
            $table->timestamps();
        });

        Schema::create('doi_giai_dau', function (Blueprint $table) {
            $table->id();
            $table->foreignId('giai_dau_id')->constrained('giai_dau')->restrictOnDelete();
            $table->foreignId('doi_id')->constrained('doi')->restrictOnDelete();
            $table->enum('trang_thai', ['cho_duyet', 'da_duyet', 'tu_choi'])->default('cho_duyet');
            $table->unsignedSmallInteger('thu_hang')->nullable();
            $table->string('danh_hieu', 100)->nullable(); // Vô địch, Á quân, Fair-play...
            $table->timestamps();

            $table->unique(['giai_dau_id', 'doi_id']);
        });

        // Chụp danh sách cầu thủ lúc dự giải → thành tích không mất khi rời đội
        Schema::create('cau_thu_giai_dau', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doi_giai_dau_id')->constrained('doi_giai_dau')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unique(['doi_giai_dau_id', 'user_id']);
        });

        Schema::create('tran_dau', function (Blueprint $table) {
            $table->id();
            $table->foreignId('giai_dau_id')->constrained('giai_dau')->cascadeOnDelete();
            $table->string('vong', 50);  // "Bảng A - lượt 1", "Bán kết"...
            $table->foreignId('doi_nha_id')->constrained('doi')->restrictOnDelete();
            $table->foreignId('doi_khach_id')->constrained('doi')->restrictOnDelete();
            $table->foreignId('san_id')->nullable()->constrained('san')->nullOnDelete();
            $table->dateTime('thoi_gian')->nullable();
            $table->unsignedTinyInteger('ban_thang_nha')->nullable();
            $table->unsignedTinyInteger('ban_thang_khach')->nullable();
            $table->enum('trang_thai', ['chua_da', 'da_xong', 'hoan'])->default('chua_da');
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE giai_dau ADD CONSTRAINT chk_giai_dau_ngay CHECK (den_ngay >= tu_ngay)');
            DB::statement('ALTER TABLE tran_dau ADD CONSTRAINT chk_tran_dau_doi CHECK (doi_nha_id <> doi_khach_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tran_dau');
        Schema::dropIfExists('cau_thu_giai_dau');
        Schema::dropIfExists('doi_giai_dau');
        Schema::dropIfExists('giai_dau');
        Schema::dropIfExists('thanh_vien_doi');
        Schema::dropIfExists('doi');
    }
};
