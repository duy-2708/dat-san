<?php

namespace Tests\Feature\GiaiDoan2;

use App\Models\AnhSan;
use App\Models\San;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnhSanTest extends TestCase
{
    use RefreshDatabase;

    public function test_tai_anh_len_va_xoa(): void
    {
        Storage::fake('public');
        $chu = User::factory()->chuSan()->create();
        $san = San::factory()->cua($chu)->create();

        $this->actingAs($chu)->post("/quan-ly/san/{$san->id}/anh", [
            'anh' => [UploadedFile::fake()->image('a.jpg', 800, 450), UploadedFile::fake()->image('b.png', 800, 450)],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $san->anhSans()->count());
        $anh = $san->anhSans()->first();
        Storage::disk('public')->assertExists($anh->duong_dan);

        $this->actingAs($chu)->delete("/quan-ly/anh-san/{$anh->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($anh->duong_dan);
        $this->assertSame(1, $san->anhSans()->count());
    }

    public function test_tu_choi_file_khong_phai_anh(): void
    {
        Storage::fake('public');
        $chu = User::factory()->chuSan()->create();
        $san = San::factory()->cua($chu)->create();

        $this->actingAs($chu)->post("/quan-ly/san/{$san->id}/anh", [
            'anh' => [UploadedFile::fake()->create('virus.php', 10, 'application/x-php')],
        ])->assertSessionHasErrors('anh.0');

        $this->assertSame(0, $san->anhSans()->count());
    }

    public function test_khong_tai_anh_vao_san_nguoi_khac(): void
    {
        Storage::fake('public');
        $chuA = User::factory()->chuSan()->create();
        $sanB = San::factory()->create();

        $this->actingAs($chuA)->post("/quan-ly/san/{$sanB->id}/anh", [
            'anh' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertForbidden();

        $anhB = new AnhSan(['duong_dan' => 'san/x.jpg', 'thu_tu' => 1]);
        $anhB->san_id = $sanB->id;
        $anhB->save();
        $this->actingAs($chuA)->delete("/quan-ly/anh-san/{$anhB->id}")->assertForbidden();
    }

    public function test_anh_dai_dien_va_anh_mac_dinh(): void
    {
        Storage::fake('public');
        $chu = User::factory()->chuSan()->create();
        $san = San::factory()->cua($chu)->create();

        $this->assertStringContainsString('san-mac-dinh.svg', $san->anhDaiDien());

        $this->actingAs($chu)->post("/quan-ly/san/{$san->id}/anh", [
            'anh' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')],
        ]);
        $thuHai = $san->anhSans()->get()->last();
        $this->actingAs($chu)->patch("/quan-ly/anh-san/{$thuHai->id}/len-dau");

        $this->assertSame($thuHai->id, $san->fresh()->anhSans()->first()->id);
    }
}
