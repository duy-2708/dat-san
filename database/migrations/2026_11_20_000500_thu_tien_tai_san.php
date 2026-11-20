<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dat_san', function (Blueprint $t) {
            $t->decimal('tien_thu_tai_san', 12, 0)->nullable();
            $t->timestamp('da_thu_tien_luc')->nullable()->index();
            $t->foreignId('thu_tien_boi_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dat_san', function (Blueprint $t) {
            $t->dropConstrainedForeignId('thu_tien_boi_id');
            $t->dropColumn(['tien_thu_tai_san', 'da_thu_tien_luc']);
        });
    }
};
