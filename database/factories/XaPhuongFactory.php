<?php

namespace Database\Factories;

use App\Models\TinhThanh;
use App\Models\XaPhuong;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<XaPhuong> */
class XaPhuongFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tinh_thanh_id' => TinhThanh::factory(),
            'ten' => 'Phường '.fake()->unique()->numerify('####'),
            'loai' => 'phuong',
        ];
    }
}
