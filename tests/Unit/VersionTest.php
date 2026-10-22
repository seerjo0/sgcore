<?php

namespace Tests\Unit;

use App\Modules\Core\Support\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    public function test_release_versions_compare_like_semver(): void
    {
        $this->assertTrue(Version::isNewer('1.2.0', '1.1.0'));
        $this->assertTrue(Version::isNewer('1.10.0', '1.9.0'));
        $this->assertFalse(Version::isNewer('1.1.0', '1.1.0'));
        $this->assertFalse(Version::isNewer('0.9.0', '1.0.0'));
    }

    public function test_a_revision_is_newer_than_its_base_version(): void
    {
        $this->assertTrue(Version::isNewer('1.1.0-r1', '1.1.0'));
        $this->assertFalse(Version::isNewer('1.1.0', '1.1.0-r1'));
    }

    public function test_revisions_order_numerically(): void
    {
        $this->assertTrue(Version::isNewer('1.1.0-r2', '1.1.0-r1'));
        $this->assertTrue(Version::isNewer('1.1.0-r10', '1.1.0-r9'));
        $this->assertFalse(Version::isNewer('1.1.0-r1', '1.1.0-r2'));
        $this->assertFalse(Version::isNewer('1.1.0-r1', '1.1.0-r1'));
    }

    public function test_a_revision_stays_below_the_next_release(): void
    {
        $this->assertTrue(Version::isNewer('1.1.1', '1.1.0-r9'));
        $this->assertFalse(Version::isNewer('1.1.0-r9', '1.1.1'));
    }

    public function test_strips_a_leading_v_and_ignores_case(): void
    {
        $this->assertTrue(Version::isNewer('v1.1.0-R1', 'v1.1.0'));
        $this->assertFalse(Version::isNewer('v1.1.0', 'v1.1.0-r1'));
    }

    public function test_pre_releases_keep_version_compare_order_against_revisions(): void
    {
        $this->assertTrue(Version::isNewer('1.1.0-r1', '1.1.0-rc1'));
        $this->assertFalse(Version::isNewer('1.1.0-rc1', '1.1.0'));
    }
}
