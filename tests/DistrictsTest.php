<?php
declare(strict_types=1);

namespace WooBDShipping\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WooBDShipping\Districts;

final class DistrictsTest extends TestCase {

	public function testThereAre64DistrictsAndEightDivisions(): void {
		$d = new Districts();
		$this->assertCount( 64, $d->all() );
		$this->assertCount( 8, $d->divisions() );
	}

	public function testEveryDistrictHasNamesAndAKnownDivision(): void {
		$d = new Districts();
		foreach ( $d->all() as $code => $row ) {
			$this->assertMatchesRegularExpression( '/^BD-\d{2}$/', $code );
			$this->assertNotSame( '', $row['en'] );
			$this->assertNotSame( '', $row['bn'] );
			$this->assertArrayHasKey( $row['division'], $d->divisions() );
		}
	}

	public function testDivisionCounts(): void {
		$d = new Districts();
		$this->assertCount( 13, $d->inDivision( 'dhaka' ) );
		$this->assertCount( 11, $d->inDivision( 'chattogram' ) );
		$this->assertCount( 4, $d->inDivision( 'sylhet' ) );
		$this->assertCount( 4, $d->inDivision( 'mymensingh' ) );
	}

	public function testBanglaNamesAreUnique(): void {
		$names = array_column( ( new Districts() )->all(), 'bn' );
		$this->assertCount( 64, array_unique( $names ) );
	}

	#[DataProvider( 'resolveProvider' )]
	public function testResolve( string $input, ?string $expected ): void {
		$this->assertSame( $expected, ( new Districts() )->resolve( $input ) );
	}

	/** @return array<string,array{string,?string}> */
	public static function resolveProvider(): array {
		return array(
			'code'            => array( 'BD-13', 'BD-13' ),
			'lowercase code'  => array( 'bd-60', 'BD-60' ),
			'english'         => array( 'Dhaka', 'BD-13' ),
			'english spaced'  => array( '  sylhet ', 'BD-60' ),
			'bangla'          => array( 'ঢাকা', 'BD-13' ),
			'bangla chtg'     => array( 'চট্টগ্রাম', 'BD-10' ),
			'alias chittagong' => array( 'Chittagong', 'BD-10' ),
			'alias comilla'   => array( 'Comilla', 'BD-08' ),
			'apostrophe'      => array( "Cox's Bazar", 'BD-11' ),
			'no apostrophe'   => array( 'Coxs Bazar', 'BD-11' ),
			'unknown'         => array( 'Atlantis', null ),
			'empty'           => array( '', null ),
		);
	}

	public function testLabelsAreSortedAndFormatted(): void {
		$d = new Districts();
		$en = $d->labels( 'en' );
		$this->assertSame( 'Bagerhat', reset( $en ) );
		$this->assertSame( 'Dhaka (ঢাকা)', $d->labels( 'both' )['BD-13'] );
		$this->assertSame( 'ঢাকা', $d->labels( 'bn' )['BD-13'] );
	}

	public function testCustomDataIsUsed(): void {
		$d = new Districts( array( 'XX-01' => array( 'en' => 'Test', 'bn' => 'টেস্ট', 'division' => 'dhaka' ) ) );
		$this->assertTrue( $d->has( 'XX-01' ) );
		$this->assertFalse( $d->has( 'BD-13' ) );
		$this->assertSame( 'XX-01', $d->resolve( 'test' ) );
	}
}
