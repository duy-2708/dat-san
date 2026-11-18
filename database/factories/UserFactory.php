<?php

namespace Database\Factories;

use App\Models\HoSoChuSan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'so_dien_thoai' => '09'.fake()->unique()->numerify('########'),
            'password' => static::$password ??= Hash::make('password'),
            'vai_tro' => User::KHACH,
            'trang_thai' => 'hoat_dong',
            'remember_token' => Str::random(10),
        ];
    }

    public function chuaXacThuc(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function biKhoa(): static
    {
        return $this->state(fn () => ['trang_thai' => 'bi_khoa']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['vai_tro' => User::ADMIN]);
    }

    /** Chủ sân kèm hồ sơ, còn hạn 30 ngày. */
    public function chuSan(): static
    {
        return $this->state(fn () => ['vai_tro' => User::CHU_SAN])
            ->afterCreating(function (User $user) {
                $hoSo = new HoSoChuSan(['ten_co_so' => 'Sân '.$user->name]);
                $hoSo->user_id = $user->id;
                $hoSo->het_han_luc = now()->addDays(30);
                $hoSo->save();
            });
    }

    public function nhanVienCua(User $chuSan): static
    {
        return $this->state(fn () => [
            'vai_tro' => User::NHAN_VIEN,
            'chu_san_id' => $chuSan->id,
        ]);
    }
}
