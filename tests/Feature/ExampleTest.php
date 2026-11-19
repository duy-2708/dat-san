<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase; // trang chủ đọc danh sách sân từ CSDL

    public function test_trang_chu_mo_duoc(): void
    {
        $this->get('/')->assertOk();
    }
}
