<?php
/**
 * Cash on delivery availability rules. Pure PHP.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping;

/**
 * Decides whether Cash on Delivery may be offered for a cart, based on the order total,
 * the number of items and the delivery district.
 */
final class CodRules {

	public const REASON_BELOW_MIN   = 'below_min';
	public const REASON_ABOVE_MAX   = 'above_max';
	public const REASON_TOO_MANY    = 'too_many_items';
	public const REASON_DISTRICT    = 'district';
	public const REASON_NO_DISTRICT = 'no_district';

	/**
	 * Constructor.
	 *
	 * @param float    $minTotal         Smallest order total that may use COD. 0 disables the check.
	 * @param float    $maxTotal         Largest order total that may use COD. 0 disables the check.
	 * @param int      $maxItems         Largest item quantity that may use COD. 0 disables the check.
	 * @param string[] $blockedDistricts District codes where COD is not offered.
	 * @param bool     $requireDistrict  Hide COD until a known district is chosen.
	 */
	public function __construct(
		public readonly float $minTotal = 0.0,
		public readonly float $maxTotal = 0.0,
		public readonly int $maxItems = 0,
		public readonly array $blockedDistricts = array(),
		public readonly bool $requireDistrict = false
	) {
	}

	/**
	 * Build the rules from the stored plugin settings.
	 *
	 * @param array<string,mixed> $settings  Plugin settings.
	 * @param Districts           $districts District lookup used to validate codes.
	 */
	public static function fromSettings( array $settings, Districts $districts ): self {
		$blocked = $settings['cod_blocked_districts'] ?? array();
		$blocked = is_array( $blocked )
			? array_values( array_unique( array_filter( array_map( 'strval', $blocked ), array( $districts, 'has' ) ) ) )
			: array();

		return new self(
			RateConfig::money( $settings['cod_min_total'] ?? 0 ),
			RateConfig::money( $settings['cod_max_total'] ?? 0 ),
			(int) floor( RateConfig::money( $settings['cod_max_items'] ?? 0 ) ),
			$blocked,
			'yes' === ( $settings['cod_require_district'] ?? 'no' )
		);
	}

	/**
	 * Whether any rule is configured at all.
	 */
	public function isActive(): bool {
		return $this->minTotal > 0
			|| $this->maxTotal > 0
			|| $this->maxItems > 0
			|| array() !== $this->blockedDistricts
			|| $this->requireDistrict;
	}

	/**
	 * First rule that blocks COD, or null when COD may be offered.
	 *
	 * Checks run in a fixed order: district, minimum total, maximum total, item count.
	 *
	 * @param float       $total        Order total the limits are compared against.
	 * @param int         $items        Number of items in the cart.
	 * @param string|null $districtCode Resolved district code, null when unknown.
	 */
	public function blockedReason( float $total, int $items, ?string $districtCode ): ?string {
		if ( null === $districtCode || '' === $districtCode ) {
			if ( $this->requireDistrict ) {
				return self::REASON_NO_DISTRICT;
			}
		} elseif ( in_array( $districtCode, $this->blockedDistricts, true ) ) {
			return self::REASON_DISTRICT;
		}

		if ( $this->minTotal > 0 && $total < $this->minTotal ) {
			return self::REASON_BELOW_MIN;
		}
		if ( $this->maxTotal > 0 && $total > $this->maxTotal ) {
			return self::REASON_ABOVE_MAX;
		}
		if ( $this->maxItems > 0 && $items > $this->maxItems ) {
			return self::REASON_TOO_MANY;
		}
		return null;
	}

	/**
	 * Whether COD may be offered.
	 *
	 * @param float       $total        Order total.
	 * @param int         $items        Number of items in the cart.
	 * @param string|null $districtCode Resolved district code.
	 */
	public function allows( float $total, int $items, ?string $districtCode ): bool {
		return null === $this->blockedReason( $total, $items, $districtCode );
	}

	/**
	 * Fill a message template for a reason. Supported placeholders: {min}, {max}, {items}, {district}.
	 *
	 * @param string               $template Message template.
	 * @param array<string,string> $values   Formatted values keyed by placeholder name without braces.
	 */
	public static function message( string $template, array $values ): string {
		$pairs = array();
		foreach ( array( 'min', 'max', 'items', 'district' ) as $name ) {
			$pairs[ '{' . $name . '}' ] = $values[ $name ] ?? '';
		}
		return trim( strtr( $template, $pairs ) );
	}
}
