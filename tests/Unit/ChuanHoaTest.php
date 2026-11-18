<?php

namespace Tests\Unit;

use App\Support\ChuanHoa;
use PHPUnit\Framework\TestCase;

class ChuanHoaTest extends TestCase
{
    public function test_chuan_hoa_email(): void
    {
        $this->assertSame('cauthu@gmail.com', ChuanHoa::email(' Cau.Thu+keo@GoogleMail.com '));
        $this->assertSame('a.b@yahoo.com', ChuanHoa::email('a.b+x@yahoo.com')); // chỉ Gmail mới bỏ dấu chấm
    }

    public function test_chuan_hoa_so_dien_thoai(): void
    {
        $this->assertSame('0901234567', ChuanHoa::soDienThoai('+84 901 234 567'));
        $this->assertSame('0901234567', ChuanHoa::soDienThoai('84901234567'));
        $this->assertSame('0901234567', ChuanHoa::soDienThoai('0901.234.567'));
        $this->assertNull(ChuanHoa::soDienThoai('  '));
        $this->assertMatchesRegularExpression(ChuanHoa::REGEX_SDT, '0901234567');
        $this->assertDoesNotMatchRegularExpression(ChuanHoa::REGEX_SDT, '0123456789');
    }
}
