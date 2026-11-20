<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('co_so', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chu_san_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('xa_phuong_id')->constrained('xa_phuong')->restrictOnDelete();
            $t->string('ten', 100);
            $t->string('slug', 150)->unique();
            $t->string('dia_chi_chi_tiet');
            $t->text('mo_ta')->nullable();
            $t->json('tien_ich')->nullable();
            $t->time('gio_mo_cua')->default('05:00');
            $t->time('gio_dong_cua')->default('23:00');
            $t->timestamps();
        });

        Schema::table('san', function (Blueprint $t) {
            $t->foreignId('co_so_id')->nullable()->after('chu_san_id')->constrained('co_so')->restrictOnDelete();
            $t->boolean('gia_rieng')->default(false)->after('trang_thai');
        });

        foreach (DB::table('san')->orderBy('id')->get() as $s) {
            $id = DB::table('co_so')->insertGetId([
                'chu_san_id' => $s->chu_san_id, 'xa_phuong_id' => $s->xa_phuong_id, 'ten' => $s->ten,
                'slug' => Str::slug($s->ten).'-'.Str::lower(Str::random(6)),
                'dia_chi_chi_tiet' => $s->dia_chi_chi_tiet, 'mo_ta' => $s->mo_ta, 'tien_ich' => $s->tien_ich,
                'gio_mo_cua' => $s->gio_mo_cua, 'gio_dong_cua' => $s->gio_dong_cua,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('san')->where('id', $s->id)->update(['co_so_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('san', function (Blueprint $t) {
            $t->dropConstrainedForeignId('co_so_id');
            $t->dropColumn('gia_rieng');
        });
        Schema::dropIfExists('co_so');
    }
};
