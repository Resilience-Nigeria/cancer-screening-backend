<?php

namespace Tests\Unit\Bloom;

use App\Support\Bloom\Geo;
use App\Support\Bloom\NigerianPhone;
use App\Support\Bloom\OpeningHours;
use PHPUnit\Framework\TestCase;

/**
 * Pure-PHP checks (no database, no Laravel boot): run with `php artisan test --filter=BloomSupportTest`.
 */
class BloomSupportTest extends TestCase
{
    public function test_phone_is_normalised_whatever_way_it_is_typed(): void
    {
        foreach (['08031234567', '0803 123 4567', '+2348031234567', '2348031234567', '8031234567', '+234 (0) 803 123 4567', '002348031234567'] as $in) {
            $this->assertSame('2348031234567', NigerianPhone::normalize($in), $in);
        }
        $this->assertSame('2349012345678', NigerianPhone::normalize('09012345678'));
        $this->assertSame('2347012345678', NigerianPhone::normalize('07012345678'));
    }

    public function test_bad_phones_are_rejected(): void
    {
        foreach ([null, '', 'abc', '0803123456', '080312345678', '01234567890', '+441234567890', '12345'] as $in) {
            $this->assertNull(NigerianPhone::normalize($in), (string) $in);
        }
    }

    public function test_local_format(): void
    {
        $this->assertSame('08031234567', NigerianPhone::local('2348031234567'));
    }

    public function test_haversine_distance(): void
    {
        // Jabi (9.0689, 7.4233) to Garki (9.0360, 7.4890) is about 8 km.
        $km = Geo::distanceKm(9.0689, 7.4233, 9.0360, 7.4890);
        $this->assertEqualsWithDelta(8.2, $km, 0.6);
        $this->assertEqualsWithDelta(0.0, Geo::distanceKm(9.0, 7.0, 9.0, 7.0), 0.0001);
        // Abuja to Lagos is roughly 535 km in a straight line.
        $this->assertEqualsWithDelta(535, Geo::distanceKm(9.0579, 7.4951, 6.5244, 3.3792), 15);
    }

    public function test_bounding_box_contains_points_inside_the_radius(): void
    {
        $box = Geo::boundingBox(9.0579, 7.4951, 10);
        $this->assertLessThan(9.0579, $box['minLat']);
        $this->assertGreaterThan(9.0579, $box['maxLat']);
        $this->assertLessThan(7.4951, $box['minLng']);
        $this->assertGreaterThan(7.4951, $box['maxLng']);
    }

    public function test_opening_hours(): void
    {
        $tz  = new \DateTimeZone('Africa/Lagos');
        $mon = new \DateTimeImmutable('2026-11-09 10:30', $tz);   // a Monday
        $sun = new \DateTimeImmutable('2026-11-08 10:30', $tz);   // a Sunday

        $this->assertTrue(OpeningHours::isOpen(['Mon', 'Wed'], '09:00', '16:00', $mon));
        $this->assertTrue(OpeningHours::isOpen(['Monday'], '9:00 AM', '4:00 PM', $mon));
        $this->assertFalse(OpeningHours::isOpen(['Mon', 'Wed'], '09:00', '16:00', $sun));
        $this->assertFalse(OpeningHours::isOpen(null, '11:00', '16:00', $mon));
        $this->assertTrue(OpeningHours::isOpen([], '08:00:00', '17:00:00', $sun));
        $this->assertNull(OpeningHours::isOpen(['Mon'], null, '16:00', $mon));
        $this->assertNull(OpeningHours::isOpen(['Mon'], 'soon', 'later', $mon));
    }

    public function test_overnight_hours(): void
    {
        $tz = new \DateTimeZone('Africa/Lagos');
        $this->assertTrue(OpeningHours::isOpen(null, '20:00', '06:00', new \DateTimeImmutable('2026-11-09 23:00', $tz)));
        $this->assertTrue(OpeningHours::isOpen(null, '20:00', '06:00', new \DateTimeImmutable('2026-11-09 05:00', $tz)));
        $this->assertFalse(OpeningHours::isOpen(null, '20:00', '06:00', new \DateTimeImmutable('2026-11-09 12:00', $tz)));
    }

    public function test_time_parsing(): void
    {
        $this->assertSame(0, OpeningHours::minutes('12:00 AM'));
        $this->assertSame(12 * 60, OpeningHours::minutes('12:00 PM'));
        $this->assertSame(13 * 60 + 5, OpeningHours::minutes('1:05 pm'));
        $this->assertSame(9 * 60, OpeningHours::minutes('9'));
        $this->assertNull(OpeningHours::minutes('25:00'));
        $this->assertNull(OpeningHours::minutes('13:00 PM'));
    }
}
