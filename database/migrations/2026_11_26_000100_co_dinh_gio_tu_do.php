<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chuoi_co_dinh', function (Blueprint $t) {
            $t->time('gio_bat_dau')->nullable();
            $t->time('gio_ket_thuc')->nullable();
            $t->date('den_ngay')->nullable();
        });

        foreach (DB::table('chuoi_co_dinh')->whereNull('gio_bat_dau')->get() as $c) {
            $khung = DB::table('khung_gio')->find($c->khung_gio_id);
            if ($khung) {
                DB::table('chuoi_co_dinh')->where('id', $c->id)
                    ->update(['gio_bat_dau' => $khung->gio_bat_dau, 'gio_ket_thuc' => $khung->gio_ket_thuc]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('chuoi_co_dinh', fn (Blueprint $t) => $t->dropColumn(['gio_bat_dau', 'gio_ket_thuc', 'den_ngay']));
    }
};
