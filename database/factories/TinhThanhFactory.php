<?php

namespace Database\Factories;

use App\Models\TinhThanh;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TinhThanh> */
class TinhThanhFactory extends Factory
{
    public function definition(): array
    {
        return ['ten' => 'Tỉnh '.fake()->unique()->numerify('####'), 'loai' => 'tinh', 'thu_tu' => 99];
    }
}
