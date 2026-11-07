<?php
/**
 * Shipping rate and COD fee maths. Pure PHP.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping;

/**
 * Calculates shipping cost from a RateConfig.
 */
final class RateCalculator {

	public const ZONE_INSIDE_DHAKA = 'inside_dhaka';
	public const ZONE_SUB_AREA     = 'sub_area';
	public const ZONE_OUTSIDE      = 'outside';

	/**
	 * Constructor.
	 *
	 * @param RateConfig $config Rate configuration.
	 */
	public function __construct( private readonly RateConfig $config ) {
	}

	/**
	 * Zone a district belongs to.
	 *
	 * @param string $districtCode District code.
	 */
	public function zoneFor( string $districtCode ): string {
		if ( RateConfig::DHAKA_CODE === $districtCode ) {
			return self::ZONE_INSIDE_DHAKA;
		}
		if ( in_array( $districtCode, $this->config->subAreaDistricts, true ) ) {
			return self::ZONE_SUB_AREA;
		}
		return self::ZONE_OUTSIDE;
	}

	/**
	 * Flat base rate for a district before weight surcharge: override first, then zone rate.
	 *
	 * @param string $districtCode District code.
	 */
	public function baseRate( string $districtCode ): float {
		if ( isset( $this->config->overrides[ $districtCode ] ) ) {
			return $this->config->overrides[ $districtCode ];
		}

		return match ( $this->zoneFor( $districtCode ) ) {
			self::ZONE_INSIDE_DHAKA => $this->config->insideDhaka,
			self::ZONE_SUB_AREA     => $this->config->subArea,
			default                 => $this->config->outside,
		};
	}

	/**
	 * Surcharge for weight above the included amount; every started kg counts.
	 *
	 * @param float $weightKg Total cart weight in kilograms.
	 */
	public function weightSurcharge( float $weightKg ): float {
		if ( $this->config->extraPerKg <= 0 ) {
			return 0.0;
		}
		$over = round( $weightKg - $this->config->includedWeight, 6 );
		if ( $over <= 0 ) {
			return 0.0;
		}
		return round( ceil( $over ) * $this->config->extraPerKg, 2 );
	}

	/**
	 * Whether a subtotal qualifies for free shipping.
	 *
	 * @param float $subtotal Cart subtotal.
	 */
	public function isFree( float $subtotal ): bool {
		return $this->config->freeThreshold > 0 && $subtotal >= $this->config->freeThreshold;
	}

	/**
	 * Total shipping cost.
	 *
	 * @param string $districtCode District code; an empty value yields null (no rate).
	 * @param float  $weightKg     Cart weight in kilograms.
	 * @param float  $subtotal     Cart subtotal used for the free shipping threshold.
	 * @return float|null
	 */
	public function calculate( string $districtCode, float $weightKg, float $subtotal ): ?float {
		if ( '' === $districtCode ) {
			return null;
		}
		if ( $this->isFree( $subtotal ) ) {
			return 0.0;
		}
		return round( $this->baseRate( $districtCode ) + $this->weightSurcharge( max( 0.0, $weightKg ) ), 2 );
	}

	/**
	 * Cash on delivery fee.
	 *
	 * @param float  $base  Amount a percentage fee is applied to.
	 * @param string $type  percent or fixed.
	 * @param float  $value Percentage or fixed amount.
	 */
	public static function codFee( float $base, string $type, float $value ): float {
		if ( $value <= 0 || $base < 0 ) {
			return 0.0;
		}
		return 'percent' === $type ? round( $base * $value / 100, 2 ) : round( $value, 2 );
	}
}
