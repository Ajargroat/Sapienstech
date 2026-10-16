<?php

namespace Tests\Unit;

use App\Support\IranianIdentity;
use App\Support\LoginIdentifier;
use PHPUnit\Framework\TestCase;

/**
 * The normalization contract behind the کد ملی / mobile identity: one stored
 * form no matter how the person typed it, and one shape-based login lookup.
 */
class IranianIdentityTest extends TestCase
{
    public function test_persian_and_arabic_digits_convert_to_ascii(): void
    {
        $this->assertSame('09123456789', IranianIdentity::toAsciiDigits('۰۹۱۲۳۴۵۶۷۸۹'));
        $this->assertSame('0123456789', IranianIdentity::toAsciiDigits('٠١٢٣٤٥٦٧٨٩'));
        $this->assertSame('12a34', IranianIdentity::toAsciiDigits('۱۲a۳۴'));
    }

    public function test_national_code_normalization_strips_non_digits(): void
    {
        $this->assertSame('0499370899', IranianIdentity::normalizeNationalCode(' ۰۴۹-۹۳۷ ۰۸۹۹ '));
        $this->assertNull(IranianIdentity::normalizeNationalCode('هیچ‌چیز'));
        $this->assertNull(IranianIdentity::normalizeNationalCode(null));
    }

    public function test_national_code_checksum(): void
    {
        $code = IranianIdentity::randomValidNationalCode();
        $this->assertTrue(IranianIdentity::isValidNationalCode($code));

        // A valid code whose check digit is flipped can never stay valid.
        $broken = substr($code, 0, 9).(((int) $code[9] + 1) % 10);
        $this->assertFalse(IranianIdentity::isValidNationalCode($broken));

        // 1111111111 happens to pass the checksum; the all-same-digit rule is
        // what rejects it (such codes are never issued).
        $this->assertFalse(IranianIdentity::isValidNationalCode('1111111111'));

        $this->assertFalse(IranianIdentity::isValidNationalCode('123456789'));
        $this->assertFalse(IranianIdentity::isValidNationalCode(null));
    }

    public function test_phone_normalization_accepts_every_shape(): void
    {
        $this->assertSame('09123456789', IranianIdentity::normalizePhone('09123456789'));
        $this->assertSame('09123456789', IranianIdentity::normalizePhone('۰۹۱۲۳۴۵۶۷۸۹'));
        $this->assertSame('09123456789', IranianIdentity::normalizePhone('+98 912 345 6789'));
        $this->assertSame('09123456789', IranianIdentity::normalizePhone('00989123456789'));
        $this->assertSame('09123456789', IranianIdentity::normalizePhone('989123456789'));
    }

    public function test_phone_normalization_rejects_non_mobiles(): void
    {
        $this->assertNull(IranianIdentity::normalizePhone(''));
        $this->assertNull(IranianIdentity::normalizePhone(null));
        $this->assertNull(IranianIdentity::normalizePhone('0812345678'));
        $this->assertNull(IranianIdentity::normalizePhone('0912345678'));
        $this->assertFalse(IranianIdentity::isValidPhone('0912-345-678x'));
    }

    public function test_login_candidates_are_shape_determined(): void
    {
        $this->assertSame(['email' => 'a@b.test'], LoginIdentifier::candidates(' A@B.TEST '));
        $this->assertSame(['phone' => '09123456789'], LoginIdentifier::candidates('۰۹۱۲۳۴۵۶۷۸۹'));
        $this->assertSame(['phone' => '09123456789'], LoginIdentifier::candidates('+989123456789'));

        $code = IranianIdentity::randomValidNationalCode();
        $this->assertSame(['national_code' => $code], LoginIdentifier::candidates($code));

        // An unshaped value never misses: it tries all three columns.
        $candidates = LoginIdentifier::candidates('  oops  ');
        $this->assertSame(['email', 'phone', 'national_code'], array_keys($candidates));

        $this->assertSame([], LoginIdentifier::candidates('   '));
    }
}
