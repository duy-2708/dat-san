<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dat_san', function (Blueprint $t) {
            $t->dateTime('nhac_thieu_tien_luc')->nullable()->after('khong_tra_du_luc');
        });
    }

    public function down(): void
    {
        Schema::table('dat_san', fn (Blueprint $t) => $t->dropColumn('nhac_thieu_tien_luc'));
    }
};
