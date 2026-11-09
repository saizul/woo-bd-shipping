<?php
/**
 * Free shipping progress maths and message building. Pure PHP.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping;

/**
 * Turns a subtotal and a free shipping threshold into a customer-facing message.
 */
final class FreeShipping {

	public const PLACEHOLDER = '{amount}';

	/**
	 * Amount still missing for free shipping.
	 *
	 * @param float $subtotal  Cart subtotal (same basis as RateCalculator::isFree()).
	 * @param float $threshold Free shipping threshold; 0 or less means the feature is off.
	 * @return float|null Null when disabled, 0.0 when already unlocked, otherwise the remaining amount.
	 */
	public static function remaining( float $subtotal, float $threshold ): ?float {
		if ( $threshold <= 0 ) {
			return null;
		}
		if ( $subtotal >= $threshold ) {
			return 0.0;
		}
		return round( $threshold - max( 0.0, $subtotal ), 2 );
	}

	/**
	 * Share of the threshold already reached, 0 to 100.
	 *
	 * @param float $subtotal  Cart subtotal.
	 * @param float $threshold Free shipping threshold.
	 */
	public static function percent( float $subtotal, float $threshold ): int {
		if ( $threshold <= 0 ) {
			return 0;
		}
		return (int) max( 0, min( 100, floor( $subtotal / $threshold * 100 ) ) );
	}

	/**
	 * Build the message. The pending template may contain {amount}.
	 *
	 * @param float|null $remaining       Result of remaining().
	 * @param string     $formattedAmount Remaining amount formatted as a price.
	 * @param string     $pending         Template shown while below the threshold.
	 * @param string     $unlocked        Text shown once free shipping is unlocked.
	 * @return string Empty when the feature is disabled.
	 */
	public static function message( ?float $remaining, string $formattedAmount, string $pending, string $unlocked ): string {
		if ( null === $remaining ) {
			return '';
		}
		if ( $remaining <= 0 ) {
			return $unlocked;
		}
		return str_replace( self::PLACEHOLDER, $formattedAmount, $pending );
	}
}
