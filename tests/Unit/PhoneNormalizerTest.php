<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Tests\Unit;

use InvalidArgumentException;
use Jmluang\SsoConsumer\Support\PhoneNormalizer;
use Jmluang\SsoConsumer\Tests\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_null_and_empty_normalize_to_null(): void
    {
        $this->assertNull(PhoneNormalizer::normalize(null));
        $this->assertNull(PhoneNormalizer::normalize(''));
        $this->assertNull(PhoneNormalizer::normalize('   '));
    }

    public function test_domestic_strips_separators_and_keeps_digits(): void
    {
        $this->assertSame('15912340001', PhoneNormalizer::normalize('15912340001'));
        $this->assertSame('15912340001', PhoneNormalizer::normalize(' 159 1234 0001 '));
        $this->assertSame('15912340001', PhoneNormalizer::normalize('159-1234-0001'));
        $this->assertSame('15912340001', PhoneNormalizer::normalize('(159) 1234 0001'));
    }

    public function test_international_canonicalizes_to_plus_country_space_local(): void
    {
        $this->assertSame('+852 91234567', PhoneNormalizer::normalize('+852 91234567'));
        $this->assertSame('+852 91234567', PhoneNormalizer::normalize('+852  9123 4567'));
        $this->assertSame('+852 91234567', PhoneNormalizer::normalize('+852-9123-4567'));
        $this->assertSame('+1 4155550123', PhoneNormalizer::normalize('+1 (415) 555-0123'));
    }

    public function test_international_requires_separator_between_country_and_local(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PhoneNormalizer::normalize('+85291234567');
    }

    public function test_domestic_too_short_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PhoneNormalizer::normalize('12345');
    }

    public function test_letters_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PhoneNormalizer::normalize('159abcd0001');
    }

    public function test_international_local_must_be_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PhoneNormalizer::normalize('+852 9123abcd');
    }
}
