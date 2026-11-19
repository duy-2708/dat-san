<?php

namespace Database\Factories;

use App\Models\San;
use App\Models\User;
use App\Models\XaPhuong;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<San> */
class SanFactory extends Factory
{
    public function definition(): array
    {
        $ten = 'Sân '.fake()->unique()->bothify('##?');

        return [
            'chu_san_id' => User::factory()->chuSan(),
            'xa_phuong_id' => XaPhuong::factory(),
            'dia_chi_chi_tiet' => fake()->numberBetween(1, 500).' Đường Số '.fake()->numberBetween(1, 50),
            'ten' => $ten,
            'slug' => Str::slug($ten).'-'.Str::lower(Str::random(6)),
            'loai_san' => 5,
            'tien_ich' => ['den_chieu_sang', 'gui_xe'],
            'gio_mo_cua' => '06:00:00',
            'gio_dong_cua' => '23:00:00',
            'trang_thai' => 'hoat_dong',
        ];
    }

    public function cua(User $chuSan): static
    {
        return $this->state(fn () => ['chu_san_id' => $chuSan->id]);
    }

    public function loai(int $loai): static
    {
        return $this->state(fn () => ['loai_san' => $loai]);
    }
}
