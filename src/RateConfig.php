<?php
/**
 * Immutable shipping rate configuration. Pure PHP.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping;

/**
 * Values the rate calculator needs.
 */
final class RateConfig {

	public const DHAKA_CODE = 'BD-13';

	/**
	 * Constructor.
	 *
	 * @param float                $insideDhaka      Flat rate inside Dhaka district.
	 * @param float                $subArea          Flat rate for Dhaka sub-area districts.
	 * @param float                $outside          Flat rate for everywhere else.
	 * @param string[]             $subAreaDistricts District codes counted as Dhaka sub-area.
	 * @param array<string,float>  $overrides        Per-district flat rate overrides keyed by code.
	 * @param float                $includedWeight   Kilograms covered by the flat rate.
	 * @param float                $extraPerKg       Charge for each started kilogram above the included weight.
	 * @param float                $freeThreshold    Subtotal that unlocks free shipping, 0 disables it.
	 */
	public function __construct(
		public readonly float $insideDhaka = 80.0,
		public readonly float $subArea = 100.0,
		public readonly float $outside = 130.0,
		public readonly array $subAreaDistricts = array(),
		public readonly array $overrides = array(),
		public readonly float $includedWeight = 1.0,
		public readonly float $extraPerKg = 0.0,
		public readonly float $freeThreshold = 0.0
	) {
	}

	/**
	 * Build a config from the stored plugin settings.
	 *
	 * @param array<string,mixed> $settings  Plugin settings.
	 * @param Districts           $districts District lookup used to resolve override keys.
	 */
	public static function fromSettings( array $settings, Districts $districts ): self {
		$sub = $settings['sub_area_districts'] ?? array();
		$sub = is_array( $sub ) ? array_values( array_filter( array_map( 'strval', $sub ), array( $districts, 'has' ) ) ) : array();

		return new self(
			self::money( $settings['inside_dhaka_rate'] ?? 80 ),
			self::money( $settings['sub_area_rate'] ?? 100 ),
			self::money( $settings['outside_rate'] ?? 130 ),
			$sub,
			self::parseOverrides( (string) ( $settings['district_overrides'] ?? '' ), $districts ),
			self::money( $settings['included_weight'] ?? 1 ),
			self::money( $settings['extra_per_kg'] ?? 0 ),
			self::money( $settings['free_threshold'] ?? 0 )
		);
	}

	/**
	 * Parse override lines such as "Sylhet=150", "BD-60: 150" or "ঢাকা,60".
	 *
	 * @param string    $text      Multi-line text.
	 * @param Districts $districts District lookup.
	 * @return array<string,float>
	 */
	public static function parseOverrides( string $text, Districts $districts ): array {
		$out   = array();
		$lines = preg_split( '/\R/u', $text ) ?: array();

		foreach ( $lines as $line ) {
			$line = trim( PhoneNormalizer::toAsciiDigits( $line ) );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$parts = preg_split( '/\s*[=:,]\s*/u', $line, 2 );
			if ( ! is_array( $parts ) || 2 !== count( $parts ) || ! is_numeric( $parts[1] ) ) {
				continue;
			}
			$code = $districts->resolve( $parts[0] );
			if ( null === $code ) {
				continue;
			}
			$out[ $code ] = max( 0.0, round( (float) $parts[1], 2 ) );
		}

		return $out;
	}

	/**
	 * Coerce a stored value into a non-negative amount.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function money( mixed $value ): float {
		if ( is_string( $value ) ) {
			$value = str_replace( ',', '', PhoneNormalizer::toAsciiDigits( trim( $value ) ) );
		}
		return is_numeric( $value ) ? max( 0.0, round( (float) $value, 2 ) ) : 0.0;
	}
}
