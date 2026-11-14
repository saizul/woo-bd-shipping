<?php
declare(strict_types=1);

namespace WooBDShipping\Tests;

use PHPUnit\Framework\TestCase;
use WooBDShipping\CodRules;
use WooBDShipping\Districts;

final class CodRulesTest extends TestCase {

	public function testDefaultsAllowEverything(): void {
		$rules = new CodRules();
		$this->assertFalse( $rules->isActive() );
		$this->assertNull( $rules->blockedReason( 0, 0, null ) );
		$this->assertTrue( $rules->allows( 999999, 500, 'BD-13' ) );
	}

	public function testMinimumTotal(): void {
		$rules = new CodRules( 500 );
		$this->assertTrue( $rules->isActive() );
		$this->assertSame( CodRules::REASON_BELOW_MIN, $rules->blockedReason( 499.99, 1, 'BD-13' ) );
		$this->assertNull( $rules->blockedReason( 500, 1, 'BD-13' ) );
	}

	public function testMaximumTotal(): void {
		$rules = new CodRules( 0, 10000 );
		$this->assertNull( $rules->blockedReason( 10000, 1, 'BD-13' ) );
		$this->assertSame( CodRules::REASON_ABOVE_MAX, $rules->blockedReason( 10000.01, 1, 'BD-13' ) );
	}

	public function testMaximumItems(): void {
		$rules = new CodRules( 0, 0, 3 );
		$this->assertNull( $rules->blockedReason( 100, 3, 'BD-13' ) );
		$this->assertSame( CodRules::REASON_TOO_MANY, $rules->blockedReason( 100, 4, 'BD-13' ) );
	}

	public function testBlockedDistrict(): void {
		$rules = new CodRules( 0, 0, 0, array( 'BD-01', 'BD-29' ) );
		$this->assertSame( CodRules::REASON_DISTRICT, $rules->blockedReason( 100, 1, 'BD-01' ) );
		$this->assertNull( $rules->blockedReason( 100, 1, 'BD-13' ) );
		// Unknown district is not blocked unless a district is required.
		$this->assertNull( $rules->blockedReason( 100, 1, null ) );
	}

	public function testRequireDistrict(): void {
		$rules = new CodRules( 0, 0, 0, array(), true );
		$this->assertTrue( $rules->isActive() );
		$this->assertSame( CodRules::REASON_NO_DISTRICT, $rules->blockedReason( 100, 1, null ) );
		$this->assertSame( CodRules::REASON_NO_DISTRICT, $rules->blockedReason( 100, 1, '' ) );
		$this->assertNull( $rules->blockedReason( 100, 1, 'BD-13' ) );
	}

	public function testDistrictIsCheckedBeforeTotals(): void {
		$rules = new CodRules( 500, 1000, 2, array( 'BD-01' ) );
		$this->assertSame( CodRules::REASON_DISTRICT, $rules->blockedReason( 100, 10, 'BD-01' ) );
		$this->assertSame( CodRules::REASON_BELOW_MIN, $rules->blockedReason( 100, 10, 'BD-13' ) );
		$this->assertSame( CodRules::REASON_ABOVE_MAX, $rules->blockedReason( 5000, 10, 'BD-13' ) );
		$this->assertSame( CodRules::REASON_TOO_MANY, $rules->blockedReason( 800, 10, 'BD-13' ) );
	}

	public function testFromSettings(): void {
		$rules = CodRules::fromSettings(
			array(
				'cod_min_total'         => '৫০০',
				'cod_max_total'         => '20,000',
				'cod_max_items'         => '5.9',
				'cod_blocked_districts' => array( 'BD-01', 'BD-99', 'BD-01', 'nonsense' ),
				'cod_require_district'  => 'yes',
			),
			new Districts()
		);
		$this->assertSame( 500.0, $rules->minTotal );
		$this->assertSame( 20000.0, $rules->maxTotal );
		$this->assertSame( 5, $rules->maxItems );
		$this->assertSame( array( 'BD-01' ), $rules->blockedDistricts );
		$this->assertTrue( $rules->requireDistrict );
	}

	public function testFromSettingsWithGarbage(): void {
		$rules = CodRules::fromSettings(
			array(
				'cod_min_total'         => 'abc',
				'cod_max_total'         => -5,
				'cod_blocked_districts' => 'BD-01',
				'cod_require_district'  => 'maybe',
			),
			new Districts()
		);
		$this->assertFalse( $rules->isActive() );
	}

	public function testMessage(): void {
		$this->assertSame(
			'COD from ৳500.00 up to ৳20,000.00, max 5 items.',
			CodRules::message( 'COD from {min} up to {max}, max {items} items.', array( 'min' => '৳500.00', 'max' => '৳20,000.00', 'items' => '5' ) )
		);
		$this->assertSame( 'No COD in .', CodRules::message( 'No COD in {district}.', array() ) );
		$this->assertSame( 'Keep {unknown}', CodRules::message( ' Keep {unknown} ', array() ) );
	}
}
