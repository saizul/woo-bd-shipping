<?php
declare(strict_types=1);

namespace WooBDShipping\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WooBDShipping\PhoneNormalizer;

final class PhoneNormalizerTest extends TestCase {

	#[DataProvider( 'validProvider' )]
	public function testValidNumbers( string $input ): void {
		$this->assertSame( '01712345678', PhoneNormalizer::normalize( $input ) );
		$this->assertTrue( PhoneNormalizer::isValid( $input ) );
	}

	/** @return array<string,array{string}> */
	public static function validProvider(): array {
		return array(
			'local'           => array( '01712345678' ),
			'plus 880'        => array( '+8801712345678' ),
			'880'             => array( '8801712345678' ),
			'0088'            => array( '008801712345678' ),
			'spaces'          => array( ' 01712 345 678 ' ),
			'dashes'          => array( '017-1234-5678' ),
			'plus with space' => array( '+880 1712-345678' ),
			'bangla digits'   => array( '০১৭১২৩৪৫৬৭৮' ),
			'bangla intl'     => array( '+৮৮০১৭১২৩৪৫৬৭৮' ),
		);
	}

	#[DataProvider( 'invalidProvider' )]
	public function testInvalidNumbers( string $input ): void {
		$this->assertNull( PhoneNormalizer::normalize( $input ) );
		$this->assertFalse( PhoneNormalizer::isValid( $input ) );
	}

	/** @return array<string,array{string}> */
	public static function invalidProvider(): array {
		return array(
			'empty'          => array( '' ),
			'too short'      => array( '0171234567' ),
			'too long'       => array( '017123456789' ),
			'bad operator 0' => array( '01012345678' ),
			'bad operator 2' => array( '01212345678' ),
			'landline'       => array( '028123456' ),
			'letters'        => array( '01712abc678' ),
			'wrong country'  => array( '+919876543210' ),
			'no leading 0'   => array( '1712345678' ),
		);
	}

	public function testAllValidOperatorPrefixes(): void {
		foreach ( range( 3, 9 ) as $digit ) {
			$this->assertTrue( PhoneNormalizer::isValid( '01' . $digit . '12345678' ) );
		}
	}

	public function testInternationalFormat(): void {
		$this->assertSame( '+8801712345678', PhoneNormalizer::toInternational( '01712345678' ) );
		$this->assertNull( PhoneNormalizer::toInternational( 'nope' ) );
	}

	public function testBanglaDigitConversion(): void {
		$this->assertSame( '0123456789', PhoneNormalizer::toAsciiDigits( '০১২৩৪৫৬৭৮৯' ) );
	}
}
