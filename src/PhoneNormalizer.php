<?php
/**
 * Bangladeshi mobile number normalisation and validation. Pure PHP.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping;

/**
 * Accepts 01XXXXXXXXX, +8801XXXXXXXXX, 8801XXXXXXXXX and Bangla digits.
 */
final class PhoneNormalizer {

	private const BANGLA_DIGITS = array(
		'০' => '0',
		'১' => '1',
		'২' => '2',
		'৩' => '3',
		'৪' => '4',
		'৫' => '5',
		'৬' => '6',
		'৭' => '7',
		'৮' => '8',
		'৯' => '9',
	);

	/**
	 * Convert Bangla digits to ASCII digits.
	 *
	 * @param string $value Input text.
	 */
	public static function toAsciiDigits( string $value ): string {
		return strtr( $value, self::BANGLA_DIGITS );
	}

	/**
	 * Normalise to the local 11 digit form (01XXXXXXXXX).
	 *
	 * @param string $raw User input.
	 * @return string|null Local number, or null when it is not a valid mobile number.
	 */
	public static function normalize( string $raw ): ?string {
		$value = self::toAsciiDigits( trim( $raw ) );

		// Only digits, plus and common separators are acceptable.
		if ( '' === $value || preg_match( '/[^0-9+\s().\-]/', $value ) ) {
			return null;
		}

		$has_plus = str_starts_with( $value, '+' );
		$digits   = (string) preg_replace( '/\D+/', '', $value );

		if ( str_starts_with( $digits, '00880' ) ) {
			$digits = substr( $digits, 5 );
		} elseif ( str_starts_with( $digits, '880' ) && ( $has_plus || strlen( $digits ) >= 13 ) ) {
			$digits = substr( $digits, 3 );
		} else {
			return 1 === preg_match( '/^01[3-9][0-9]{8}$/', $digits ) ? $digits : null;
		}

		// Country code removed: accept both 1XXXXXXXXX and 01XXXXXXXXX remainders.
		if ( ! str_starts_with( $digits, '0' ) ) {
			$digits = '0' . $digits;
		}

		return 1 === preg_match( '/^01[3-9][0-9]{8}$/', $digits ) ? $digits : null;
	}

	/**
	 * Whether the input is a valid Bangladeshi mobile number.
	 *
	 * @param string $raw User input.
	 */
	public static function isValid( string $raw ): bool {
		return null !== self::normalize( $raw );
	}

	/**
	 * International form, +8801XXXXXXXXX.
	 *
	 * @param string $raw User input.
	 * @return string|null
	 */
	public static function toInternational( string $raw ): ?string {
		$local = self::normalize( $raw );
		return null === $local ? null : '+88' . $local;
	}
}
