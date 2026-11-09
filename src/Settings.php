<?php
/**
 * Plugin settings storage.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the single settings option and merges defaults.
 */
final class Settings {

	public const OPTION = 'woo_bd_shipping_settings';

	/**
	 * Default values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Rates.
			'inside_dhaka_rate'  => 80,
			'sub_area_rate'      => 100,
			'outside_rate'       => 130,
			'sub_area_districts' => array( 'BD-18', 'BD-40', 'BD-42', 'BD-35', 'BD-33' ),
			'district_overrides' => '',
			'included_weight'    => 1,
			'extra_per_kg'       => 0,
			'free_threshold'     => 0,
			// Free shipping notice.
			'free_shipping_notice'  => 'no',
			'free_shipping_message' => 'Add {amount} more to get free delivery.',
			// Cash on delivery.
			'cod_fee_type'       => 'fixed',
			'cod_fee_value'      => 0,
			'cod_fee_label'      => 'Cash on delivery fee',
			// Checkout.
			'district_select'    => 'yes',
			'district_lang'      => 'both',
			'enable_upazila'     => 'yes',
			'validate_phone'     => 'yes',
			'require_phone'      => 'yes',
			'remove_postcode'    => 'yes',
			'remove_company'     => 'yes',
			'bangla_order_note'  => 'yes',
			'order_note_label'   => 'অর্ডার সম্পর্কে বিশেষ নোট (ঐচ্ছিক)',
			'order_note_hint'    => 'ডেলিভারি সংক্রান্ত কোনো বিশেষ নির্দেশনা থাকলে এখানে লিখুন।',
		);
	}

	/**
	 * All settings with defaults applied.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * A single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Whether a yes/no toggle is on.
	 *
	 * @param string $key Setting key.
	 */
	public static function enabled( string $key ): bool {
		return 'yes' === self::get( $key, 'no' );
	}
}
