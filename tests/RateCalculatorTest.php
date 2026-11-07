<?php
declare(strict_types=1);

namespace WooBDShipping\Tests;

use PHPUnit\Framework\TestCase;
use WooBDShipping\Districts;
use WooBDShipping\RateCalculator;
use WooBDShipping\RateConfig;

final class RateCalculatorTest extends TestCase {

	private function calc( array $settings = array() ): RateCalculator {
		$settings += array(
			'inside_dhaka_rate'  => 60,
			'sub_area_rate'      => 100,
			'outside_rate'       => 130,
			'sub_area_districts' => array( 'BD-18', 'BD-40' ),
		);
		return new RateCalculator( RateConfig::fromSettings( $settings, new Districts() ) );
	}

	public function testZones(): void {
		$c = $this->calc();
		$this->assertSame( RateCalculator::ZONE_INSIDE_DHAKA, $c->zoneFor( 'BD-13' ) );
		$this->assertSame( RateCalculator::ZONE_SUB_AREA, $c->zoneFor( 'BD-18' ) );
		$this->assertSame( RateCalculator::ZONE_OUTSIDE, $c->zoneFor( 'BD-60' ) );
	}

	public function testFlatRates(): void {
		$c = $this->calc();
		$this->assertSame( 60.0, $c->calculate( 'BD-13', 0.5, 500 ) );
		$this->assertSame( 100.0, $c->calculate( 'BD-40', 0.5, 500 ) );
		$this->assertSame( 130.0, $c->calculate( 'BD-60', 0.5, 500 ) );
	}

	public function testUnknownDistrictHasNoRate(): void {
		$this->assertNull( $this->calc()->calculate( '', 1, 100 ) );
	}

	public function testDistrictOverrideBeatsZoneRate(): void {
		$c = $this->calc( array( 'district_overrides' => "Sylhet=150\nBD-13 : 70\n# comment\nbogus=5\nChittagong,140\nKhulna=abc" ) );
		$this->assertSame( 150.0, $c->calculate( 'BD-60', 0, 0 ) );
		$this->assertSame( 70.0, $c->calculate( 'BD-13', 0, 0 ) );
		$this->assertSame( 140.0, $c->calculate( 'BD-10', 0, 0 ) );
		$this->assertSame( 130.0, $c->calculate( 'BD-27', 0, 0 ) );
	}

	public function testWeightSurchargeCountsStartedKilograms(): void {
		$c = $this->calc( array( 'included_weight' => 1, 'extra_per_kg' => 20 ) );
		$this->assertSame( 60.0, $c->calculate( 'BD-13', 1.0, 0 ) );
		$this->assertSame( 80.0, $c->calculate( 'BD-13', 1.01, 0 ) );
		$this->assertSame( 80.0, $c->calculate( 'BD-13', 2.0, 0 ) );
		$this->assertSame( 100.0, $c->calculate( 'BD-13', 2.5, 0 ) );
	}

	public function testNoSurchargeWhenExtraPerKgIsZero(): void {
		$this->assertSame( 60.0, $this->calc()->calculate( 'BD-13', 50, 0 ) );
	}

	public function testFreeShippingThreshold(): void {
		$c = $this->calc( array( 'free_threshold' => 2000, 'extra_per_kg' => 20 ) );
		$this->assertSame( 130.0, $c->calculate( 'BD-60', 1, 1999.99 ) );
		$this->assertSame( 0.0, $c->calculate( 'BD-60', 10, 2000 ) );
	}

	public function testThresholdZeroMeansDisabled(): void {
		$this->assertSame( 60.0, $this->calc( array( 'free_threshold' => 0 ) )->calculate( 'BD-13', 0, 1000000 ) );
	}

	public function testMoneyParsing(): void {
		$this->assertSame( 1200.5, RateConfig::money( '1,200.50' ) );
		$this->assertSame( 120.0, RateConfig::money( '১২০' ) );
		$this->assertSame( 0.0, RateConfig::money( '-5' ) );
		$this->assertSame( 0.0, RateConfig::money( 'abc' ) );
	}

	public function testSubAreaIgnoresUnknownCodes(): void {
		$cfg = RateConfig::fromSettings( array( 'sub_area_districts' => array( 'BD-18', 'ZZ-99' ) ), new Districts() );
		$this->assertSame( array( 'BD-18' ), $cfg->subAreaDistricts );
	}

	public function testCodFee(): void {
		$this->assertSame( 20.0, RateCalculator::codFee( 1000, 'fixed', 20 ) );
		$this->assertSame( 15.0, RateCalculator::codFee( 1000, 'percent', 1.5 ) );
		$this->assertSame( 0.0, RateCalculator::codFee( 1000, 'percent', 0 ) );
		$this->assertSame( 0.0, RateCalculator::codFee( -1, 'fixed', 20 ) );
	}
}
