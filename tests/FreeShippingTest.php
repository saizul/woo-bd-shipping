<?php
declare(strict_types=1);

namespace WooBDShipping\Tests;

use PHPUnit\Framework\TestCase;
use WooBDShipping\FreeShipping;

final class FreeShippingTest extends TestCase {

	public function testDisabledWhenThresholdIsZero(): void {
		$this->assertNull( FreeShipping::remaining( 500, 0 ) );
		$this->assertNull( FreeShipping::remaining( 500, -10 ) );
	}

	public function testRemainingBelowThreshold(): void {
		$this->assertSame( 1500.0, FreeShipping::remaining( 500, 2000 ) );
		$this->assertSame( 0.01, FreeShipping::remaining( 1999.99, 2000 ) );
		$this->assertSame( 2000.0, FreeShipping::remaining( 0, 2000 ) );
	}

	public function testUnlockedAtOrAboveThreshold(): void {
		$this->assertSame( 0.0, FreeShipping::remaining( 2000, 2000 ) );
		$this->assertSame( 0.0, FreeShipping::remaining( 5000, 2000 ) );
	}

	public function testNegativeSubtotalIsTreatedAsZero(): void {
		$this->assertSame( 2000.0, FreeShipping::remaining( -50, 2000 ) );
	}

	public function testPercent(): void {
		$this->assertSame( 0, FreeShipping::percent( 100, 0 ) );
		$this->assertSame( 25, FreeShipping::percent( 500, 2000 ) );
		$this->assertSame( 99, FreeShipping::percent( 1999.99, 2000 ) );
		$this->assertSame( 100, FreeShipping::percent( 9999, 2000 ) );
		$this->assertSame( 0, FreeShipping::percent( -10, 2000 ) );
	}

	public function testMessage(): void {
		$pending  = 'Add {amount} more for free delivery.';
		$unlocked = 'Free delivery unlocked.';

		$this->assertSame( '', FreeShipping::message( null, '', $pending, $unlocked ) );
		$this->assertSame( 'Free delivery unlocked.', FreeShipping::message( 0.0, '', $pending, $unlocked ) );
		$this->assertSame( 'Add ৳1,500.00 more for free delivery.', FreeShipping::message( 1500.0, '৳1,500.00', $pending, $unlocked ) );
	}

	public function testTemplateWithoutPlaceholderIsKept(): void {
		$this->assertSame( 'Keep shopping!', FreeShipping::message( 10.0, '৳10', 'Keep shopping!', 'done' ) );
		$this->assertSame( '৳10 / ৳10', FreeShipping::message( 10.0, '৳10', '{amount} / {amount}', 'done' ) );
	}
}
