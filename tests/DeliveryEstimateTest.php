<?php
declare(strict_types=1);

namespace WooBDShipping\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WooBDShipping\DeliveryEstimate;
use WooBDShipping\Districts;
use WooBDShipping\RateCalculator;

final class DeliveryEstimateTest extends TestCase {

	private function estimator( array $settings = array() ): DeliveryEstimate {
		$settings += array(
			'delivery_inside_dhaka' => '1-2',
			'delivery_sub_area'     => '2-3',
			'delivery_outside'      => '3-5',
		);
		return DeliveryEstimate::fromSettings( $settings, new Districts() );
	}

	/**
	 * 2026-11-11 is a Wednesday, so the 13th is a Friday.
	 */
	private function at( string $time ): DateTimeImmutable {
		return new DateTimeImmutable( $time, new DateTimeZone( 'Asia/Dhaka' ) );
	}

	private function ymd( array $dates ): array {
		return array( $dates[0]->format( 'Y-m-d' ), $dates[1]->format( 'Y-m-d' ) );
	}

	public static function rangeProvider(): array {
		return array(
			'single'         => array( '3', array( 3, 3 ) ),
			'range'          => array( '1-2', array( 1, 2 ) ),
			'spaces'         => array( ' 2 - 4 ', array( 2, 4 ) ),
			'en dash'        => array( '1–2', array( 1, 2 ) ),
			'word'           => array( '2 to 4', array( 2, 4 ) ),
			'days suffix'    => array( '3-5 Days', array( 3, 5 ) ),
			'day suffix'     => array( '1 day', array( 1, 1 ) ),
			'bangla digits'  => array( '১-২', array( 1, 2 ) ),
			'reversed'       => array( '5-3', array( 3, 5 ) ),
			'same day'       => array( '0', array( 0, 0 ) ),
			'upper limit'    => array( '30-60', array( 30, 60 ) ),
			'above limit'    => array( '61', null ),
			'empty'          => array( '', null ),
			'text'           => array( 'soon', null ),
			'negative'       => array( '-2', null ),
			'decimal'        => array( '1.5', null ),
			'three numbers'  => array( '1-2-3', null ),
		);
	}

	#[DataProvider( 'rangeProvider' )]
	public function testParseRange( string $text, ?array $expected ): void {
		$this->assertSame( $expected, DeliveryEstimate::parseRange( $text ) );
	}

	public function testParseCutoff(): void {
		$this->assertSame( 840, DeliveryEstimate::parseCutoff( '14' ) );
		$this->assertSame( 870, DeliveryEstimate::parseCutoff( '14:30' ) );
		$this->assertSame( 540, DeliveryEstimate::parseCutoff( ' 9.00 ' ) );
		$this->assertSame( 960, DeliveryEstimate::parseCutoff( '১৬:০০' ) );
		$this->assertSame( 0, DeliveryEstimate::parseCutoff( '0:00' ) );
		$this->assertNull( DeliveryEstimate::parseCutoff( '' ) );
		$this->assertNull( DeliveryEstimate::parseCutoff( '24:00' ) );
		$this->assertNull( DeliveryEstimate::parseCutoff( '12:60' ) );
		$this->assertNull( DeliveryEstimate::parseCutoff( '2pm' ) );
	}

	public function testParseOverrides(): void {
		$text = "Sylhet=2-3\nBD-01: 5-7\nঢাকা,১\n# comment\n\nNowhere=2\nKhulna=soon\nRangpur";
		$this->assertSame(
			array(
				'BD-60' => array( 2, 3 ),
				'BD-01' => array( 5, 7 ),
				'BD-13' => array( 1, 1 ),
			),
			DeliveryEstimate::parseOverrides( $text, new Districts() )
		);
	}

	public function testRangeForUsesZoneThenOverride(): void {
		$e = $this->estimator( array( 'delivery_overrides' => 'Sylhet=4-6' ) );
		$this->assertSame( array( 1, 2 ), $e->rangeFor( 'BD-13', RateCalculator::ZONE_INSIDE_DHAKA ) );
		$this->assertSame( array( 2, 3 ), $e->rangeFor( 'BD-18', RateCalculator::ZONE_SUB_AREA ) );
		$this->assertSame( array( 3, 5 ), $e->rangeFor( 'BD-06', RateCalculator::ZONE_OUTSIDE ) );
		$this->assertSame( array( 4, 6 ), $e->rangeFor( 'BD-60', RateCalculator::ZONE_OUTSIDE ) );
	}

	public function testUnconfiguredZoneHasNoRange(): void {
		$e = $this->estimator( array( 'delivery_outside' => '', 'delivery_sub_area' => 'n/a' ) );
		$this->assertNull( $e->rangeFor( 'BD-60', RateCalculator::ZONE_OUTSIDE ) );
		$this->assertNull( $e->rangeFor( 'BD-18', RateCalculator::ZONE_SUB_AREA ) );
		$this->assertSame( '', $e->estimate( 'BD-60', RateCalculator::ZONE_OUTSIDE, $this->at( '2026-11-11 10:00' ) ) );
	}

	public function testFromSettingsToleratesBadValues(): void {
		$e = DeliveryEstimate::fromSettings(
			array(
				'delivery_inside_dhaka' => array( 'x' ),
				'delivery_overrides'    => null,
				'delivery_cutoff'       => 'later',
				'delivery_off_days'     => 'everyday',
			),
			new Districts()
		);
		$this->assertSame( array(), $e->zoneDays );
		$this->assertSame( array(), $e->overrides );
		$this->assertNull( $e->cutoffMinutes );
		$this->assertSame( array(), $e->offDays );
	}

	public function testOffDayPresets(): void {
		$this->assertSame( array( 5 ), $this->estimator( array( 'delivery_off_days' => 'fri' ) )->offDays );
		$this->assertSame( array( 5, 6 ), $this->estimator( array( 'delivery_off_days' => 'fri_sat' ) )->offDays );
		$this->assertSame( array(), $this->estimator( array( 'delivery_off_days' => 'none' ) )->offDays );
		$this->assertSame( array(), $this->estimator()->offDays );
	}

	public function testDaysText(): void {
		$this->assertSame( 'Same day', DeliveryEstimate::daysText( array( 0, 0 ) ) );
		$this->assertSame( '1 day', DeliveryEstimate::daysText( array( 1, 1 ) ) );
		$this->assertSame( '3 days', DeliveryEstimate::daysText( array( 3, 3 ) ) );
		$this->assertSame( '1-2 days', DeliveryEstimate::daysText( array( 1, 2 ) ) );
		$this->assertSame( '0-1 days', DeliveryEstimate::daysText( array( 0, 1 ) ) );
	}

	public function testDaysTextUsesTranslatedWords(): void {
		$words = array( 'same_day' => 'আজই', 'day' => '%s দিন', 'days' => '%s দিন' );
		$this->assertSame( 'আজই', DeliveryEstimate::daysText( array( 0, 0 ), $words ) );
		$this->assertSame( '2-3 দিন', DeliveryEstimate::daysText( array( 2, 3 ), $words ) );
		$this->assertSame( '4 days', DeliveryEstimate::daysText( array( 4, 4 ), array( 'day' => '%s দিন' ) ) );
	}

	public function testDispatchWithoutCutoffIsToday(): void {
		$e = $this->estimator();
		$this->assertSame( '2026-11-11 00:00', $e->dispatchDate( $this->at( '2026-11-11 23:59' ) )->format( 'Y-m-d H:i' ) );
	}

	public function testDispatchRespectsCutoff(): void {
		$e = $this->estimator( array( 'delivery_cutoff' => '14:00' ) );
		$this->assertSame( '2026-11-11', $e->dispatchDate( $this->at( '2026-11-11 13:59' ) )->format( 'Y-m-d' ) );
		$this->assertSame( '2026-11-12', $e->dispatchDate( $this->at( '2026-11-11 14:00' ) )->format( 'Y-m-d' ) );
		$this->assertSame( '2026-11-12', $e->dispatchDate( $this->at( '2026-11-11 18:30' ) )->format( 'Y-m-d' ) );
	}

	public function testDispatchSkipsOffDays(): void {
		$e = $this->estimator( array( 'delivery_cutoff' => '14:00', 'delivery_off_days' => 'fri' ) );
		// Thursday after the cut-off: Friday is off, so the parcel leaves on Saturday.
		$this->assertSame( '2026-11-14', $e->dispatchDate( $this->at( '2026-11-12 15:00' ) )->format( 'Y-m-d' ) );
		// Ordered on the off day itself, before or after the cut-off: still Saturday.
		$this->assertSame( '2026-11-14', $e->dispatchDate( $this->at( '2026-11-13 09:00' ) )->format( 'Y-m-d' ) );
		$this->assertSame( '2026-11-14', $e->dispatchDate( $this->at( '2026-11-13 20:00' ) )->format( 'Y-m-d' ) );

		$weekend = $this->estimator( array( 'delivery_off_days' => 'fri_sat' ) );
		$this->assertSame( '2026-11-15', $weekend->dispatchDate( $this->at( '2026-11-13 09:00' ) )->format( 'Y-m-d' ) );
	}

	public function testAddWorkingDays(): void {
		$e = $this->estimator( array( 'delivery_off_days' => 'fri' ) );
		$this->assertSame( '2026-11-11', $e->addWorkingDays( $this->at( '2026-11-11' ), 0 )->format( 'Y-m-d' ) );
		$this->assertSame( '2026-11-12', $e->addWorkingDays( $this->at( '2026-11-11' ), 1 )->format( 'Y-m-d' ) );
		$this->assertSame( '2026-11-14', $e->addWorkingDays( $this->at( '2026-11-11' ), 2 )->format( 'Y-m-d' ) );
		$this->assertSame( '2026-11-21', $e->addWorkingDays( $this->at( '2026-11-11' ), 8 )->format( 'Y-m-d' ) );
	}

	public function testDateRangeWithoutOffDays(): void {
		$e = $this->estimator();
		$this->assertSame( array( '2026-11-12', '2026-11-13' ), $this->ymd( $e->dateRange( array( 1, 2 ), $this->at( '2026-11-11 10:00' ) ) ) );
		$this->assertSame( array( '2026-11-11', '2026-11-11' ), $this->ymd( $e->dateRange( array( 0, 0 ), $this->at( '2026-11-11 10:00' ) ) ) );
	}

	public function testDateRangeSkipsFriday(): void {
		$e = $this->estimator( array( 'delivery_cutoff' => '14', 'delivery_off_days' => 'fri' ) );
		// Wednesday morning, 1-2 days: Thursday, then Saturday because Friday is off.
		$this->assertSame( array( '2026-11-12', '2026-11-14' ), $this->ymd( $e->dateRange( array( 1, 2 ), $this->at( '2026-11-11 10:00' ) ) ) );
		// Wednesday evening: dispatched Thursday, arrives Saturday to Sunday.
		$this->assertSame( array( '2026-11-14', '2026-11-15' ), $this->ymd( $e->dateRange( array( 1, 2 ), $this->at( '2026-11-11 19:00' ) ) ) );
	}

	public function testDateRangeCrossesMonthAndYear(): void {
		$e = $this->estimator( array( 'delivery_off_days' => 'fri' ) );
		$this->assertSame( array( '2027-01-03', '2027-01-05' ), $this->ymd( $e->dateRange( array( 3, 5 ), $this->at( '2026-12-30 10:00' ) ) ) );
	}

	public function testEveryDayOffDoesNotLoopForever(): void {
		$e = new DeliveryEstimate( array(), array(), null, array( 1, 2, 3, 4, 5, 6, 7 ) );
		$this->assertSame( array( '2026-11-12', '2026-11-13' ), $this->ymd( $e->dateRange( array( 1, 2 ), $this->at( '2026-11-11 10:00' ) ) ) );
	}

	public function testDatesText(): void {
		$a = $this->at( '2026-11-14' );
		$b = $this->at( '2026-11-16' );
		$this->assertSame( '14 Nov - 16 Nov', DeliveryEstimate::datesText( array( $a, $b ) ) );
		$this->assertSame( '14 Nov', DeliveryEstimate::datesText( array( $a, $a ) ) );
		$this->assertSame(
			'Sat 14/11 - Mon 16/11',
			DeliveryEstimate::datesText( array( $a, $b ), static fn ( DateTimeImmutable $d ): string => $d->format( 'D d/m' ) )
		);
	}

	public function testEstimateInDaysIgnoresTheClock(): void {
		$e = $this->estimator( array( 'delivery_cutoff' => '14', 'delivery_off_days' => 'fri', 'delivery_overrides' => 'Sylhet=4' ) );
		$this->assertSame( '1-2 days', $e->estimate( 'BD-13', RateCalculator::ZONE_INSIDE_DHAKA, $this->at( '2026-11-12 22:00' ) ) );
		$this->assertSame( '4 days', $e->estimate( 'BD-60', RateCalculator::ZONE_OUTSIDE, $this->at( '2026-11-12 22:00' ) ) );
	}

	public function testEstimateInDates(): void {
		$e = $this->estimator( array( 'delivery_cutoff' => '14', 'delivery_off_days' => 'fri' ) );
		$this->assertSame(
			'14 Nov - 15 Nov',
			$e->estimate( 'BD-13', RateCalculator::ZONE_INSIDE_DHAKA, $this->at( '2026-11-11 19:00' ), DeliveryEstimate::FORMAT_DATES )
		);
		$this->assertSame(
			'16 Nov - 18 Nov',
			$e->estimate( 'BD-60', RateCalculator::ZONE_OUTSIDE, $this->at( '2026-11-11 19:00' ), DeliveryEstimate::FORMAT_DATES )
		);
	}

	public function testDecorate(): void {
		$this->assertSame( 'Delivery charge (Delivery in 1-2 days)', DeliveryEstimate::decorate( 'Delivery in {estimate}', '1-2 days', 'Delivery charge' ) );
		$this->assertSame( 'Delivery charge (1-2 days)', DeliveryEstimate::decorate( '', '1-2 days', 'Delivery charge' ) );
		$this->assertSame( 'Delivery charge (1-2 days)', DeliveryEstimate::decorate( 'No placeholder', '1-2 days', 'Delivery charge' ) );
		$this->assertSame( 'Arrives 14 Nov', DeliveryEstimate::decorate( 'Arrives {estimate}', '14 Nov', ' ' ) );
		$this->assertSame( 'Delivery charge', DeliveryEstimate::decorate( 'Delivery in {estimate}', '', 'Delivery charge' ) );
	}
}
