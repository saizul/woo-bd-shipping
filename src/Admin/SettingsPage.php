<?php
/**
 * Admin settings page (WooCommerce > BD Shipping), built on the Settings API.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping\Admin;

use WooBDShipping\Plugin;
use WooBDShipping\RateConfig;
use WooBDShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers, renders and sanitises the plugin settings.
 */
final class SettingsPage {

	public const SLUG       = 'woo-bd-shipping';
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the submenu under WooCommerce.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'BD Shipping & Checkout', 'woo-bd-shipping' ),
			__( 'BD Shipping', 'woo-bd-shipping' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Section and field definitions.
	 *
	 * @return array<string,array{title:string,fields:array<string,array<string,mixed>>}>
	 */
	private function sections(): array {
		return array(
			'rates'    => array(
				'title'  => __( 'Shipping rates', 'woo-bd-shipping' ),
				'fields' => array(
					'inside_dhaka_rate'  => array( 'type' => 'money', 'label' => __( 'Inside Dhaka', 'woo-bd-shipping' ), 'desc' => __( 'Flat rate when the district is Dhaka.', 'woo-bd-shipping' ) ),
					'sub_area_rate'      => array( 'type' => 'money', 'label' => __( 'Dhaka sub-area', 'woo-bd-shipping' ), 'desc' => __( 'Flat rate for the sub-area districts selected below.', 'woo-bd-shipping' ) ),
					'outside_rate'       => array( 'type' => 'money', 'label' => __( 'Outside Dhaka', 'woo-bd-shipping' ), 'desc' => __( 'Flat rate for every other district.', 'woo-bd-shipping' ) ),
					'sub_area_districts' => array( 'type' => 'districts', 'label' => __( 'Sub-area districts', 'woo-bd-shipping' ), 'desc' => __( 'Hold Ctrl or Cmd to select several districts.', 'woo-bd-shipping' ) ),
					'district_overrides' => array( 'type' => 'textarea', 'label' => __( 'Per-district overrides', 'woo-bd-shipping' ), 'desc' => __( 'One per line as district=amount, for example Sylhet=150 or BD-60=150. Overrides replace the zone rate.', 'woo-bd-shipping' ) ),
					'included_weight'    => array( 'type' => 'money', 'label' => __( 'Included weight (kg)', 'woo-bd-shipping' ), 'desc' => __( 'Weight covered by the flat rate.', 'woo-bd-shipping' ) ),
					'extra_per_kg'       => array( 'type' => 'money', 'label' => __( 'Extra charge per kg', 'woo-bd-shipping' ), 'desc' => __( 'Added for every started kilogram above the included weight. 0 disables it.', 'woo-bd-shipping' ) ),
					'free_threshold'     => array( 'type' => 'money', 'label' => __( 'Free shipping threshold', 'woo-bd-shipping' ), 'desc' => __( 'Cart subtotal that unlocks free shipping. 0 disables it.', 'woo-bd-shipping' ) ),
					'free_shipping_notice'  => array( 'type' => 'checkbox', 'label' => __( 'Free shipping notice', 'woo-bd-shipping' ), 'desc' => __( 'Show how much more the customer must add for free delivery on the cart and checkout pages. Also available as the [woo_bd_free_shipping] shortcode.', 'woo-bd-shipping' ) ),
					'free_shipping_message' => array( 'type' => 'text', 'label' => __( 'Free shipping message', 'woo-bd-shipping' ), 'desc' => __( 'Use {amount} where the missing amount should appear.', 'woo-bd-shipping' ) ),
				),
			),
			'delivery' => array(
				'title'  => __( 'Delivery estimate', 'woo-bd-shipping' ),
				'fields' => array(
					'delivery_estimate'     => array( 'type' => 'checkbox', 'label' => __( 'Show delivery estimate', 'woo-bd-shipping' ), 'desc' => __( 'Add the estimated delivery time to the shipping method label and save it on the order.', 'woo-bd-shipping' ) ),
					'delivery_inside_dhaka' => array( 'type' => 'text', 'label' => __( 'Inside Dhaka (days)', 'woo-bd-shipping' ), 'desc' => __( 'Working days as a number or a range, for example 1 or 1-2. Leave empty for no estimate.', 'woo-bd-shipping' ) ),
					'delivery_sub_area'     => array( 'type' => 'text', 'label' => __( 'Dhaka sub-area (days)', 'woo-bd-shipping' ) ),
					'delivery_outside'      => array( 'type' => 'text', 'label' => __( 'Outside Dhaka (days)', 'woo-bd-shipping' ) ),
					'delivery_overrides'    => array( 'type' => 'textarea', 'label' => __( 'Per-district delivery days', 'woo-bd-shipping' ), 'desc' => __( 'One per line as district=days, for example Sylhet=2-3 or BD-01=5-7. Overrides replace the zone estimate.', 'woo-bd-shipping' ) ),
					'delivery_cutoff'       => array( 'type' => 'text', 'label' => __( 'Order cut-off time', 'woo-bd-shipping' ), 'desc' => __( '24-hour time such as 14:00. Orders placed later are dispatched on the next working day. Leave empty for no cut-off. Only used for arrival dates.', 'woo-bd-shipping' ) ),
					'delivery_off_days'     => array(
						'type'    => 'select',
						'label'   => __( 'Weekly off days', 'woo-bd-shipping' ),
						'desc'    => __( 'Days without dispatch or delivery. Only used for arrival dates.', 'woo-bd-shipping' ),
						'options' => array(
							'none'    => __( 'None', 'woo-bd-shipping' ),
							'fri'     => __( 'Friday', 'woo-bd-shipping' ),
							'fri_sat' => __( 'Friday and Saturday', 'woo-bd-shipping' ),
						),
					),
					'delivery_format'       => array(
						'type'    => 'select',
						'label'   => __( 'Estimate format', 'woo-bd-shipping' ),
						'options' => array(
							'days'  => __( 'Number of days (2-3 days)', 'woo-bd-shipping' ),
							'dates' => __( 'Arrival dates (14 Nov - 16 Nov)', 'woo-bd-shipping' ),
						),
					),
					'delivery_label'        => array( 'type' => 'text', 'label' => __( 'Estimate text', 'woo-bd-shipping' ), 'desc' => __( 'Shown in brackets after the method title. Use {estimate} where the days or dates should appear.', 'woo-bd-shipping' ) ),
				),
			),
			'cod'      => array(
				'title'  => __( 'Cash on delivery', 'woo-bd-shipping' ),
				'fields' => array(
					'cod_fee_type'  => array(
						'type'    => 'select',
						'label'   => __( 'COD fee type', 'woo-bd-shipping' ),
						'options' => array(
							'fixed'   => __( 'Fixed amount', 'woo-bd-shipping' ),
							'percent' => __( 'Percentage of subtotal + shipping', 'woo-bd-shipping' ),
						),
					),
					'cod_fee_value' => array( 'type' => 'money', 'label' => __( 'COD fee value', 'woo-bd-shipping' ), 'desc' => __( 'Amount or percentage. 0 disables the fee.', 'woo-bd-shipping' ) ),
					'cod_fee_label' => array( 'type' => 'text', 'label' => __( 'COD fee label', 'woo-bd-shipping' ), 'desc' => __( 'Name of the fee line at checkout.', 'woo-bd-shipping' ) ),
				),
			),
			'checkout' => array(
				'title'  => __( 'Checkout', 'woo-bd-shipping' ),
				'fields' => array(
					'district_select'   => array( 'type' => 'checkbox', 'label' => __( 'District select', 'woo-bd-shipping' ), 'desc' => __( 'Replace the Bangladesh state field with the 64 districts.', 'woo-bd-shipping' ) ),
					'district_lang'     => array(
						'type'    => 'select',
						'label'   => __( 'District names', 'woo-bd-shipping' ),
						'options' => array(
							'en'   => __( 'English', 'woo-bd-shipping' ),
							'bn'   => __( 'Bangla', 'woo-bd-shipping' ),
							'both' => __( 'English (Bangla)', 'woo-bd-shipping' ),
						),
					),
					'enable_upazila'    => array( 'type' => 'checkbox', 'label' => __( 'Upazila / thana field', 'woo-bd-shipping' ), 'desc' => __( 'Add an optional free text field after the city.', 'woo-bd-shipping' ) ),
					'validate_phone'    => array( 'type' => 'checkbox', 'label' => __( 'Validate mobile numbers', 'woo-bd-shipping' ), 'desc' => __( 'Accepts 01[3-9]XXXXXXXX, +880 / 880 prefixes and Bangla digits. Valid numbers are saved as 01XXXXXXXXX.', 'woo-bd-shipping' ) ),
					'require_phone'     => array( 'type' => 'checkbox', 'label' => __( 'Phone required', 'woo-bd-shipping' ), 'desc' => __( 'Make the billing phone mandatory.', 'woo-bd-shipping' ) ),
					'remove_postcode'   => array( 'type' => 'checkbox', 'label' => __( 'Remove postcode', 'woo-bd-shipping' ), 'desc' => __( 'Hide the postcode field.', 'woo-bd-shipping' ) ),
					'remove_company'    => array( 'type' => 'checkbox', 'label' => __( 'Remove company', 'woo-bd-shipping' ), 'desc' => __( 'Hide the company field.', 'woo-bd-shipping' ) ),
					'bangla_order_note' => array( 'type' => 'checkbox', 'label' => __( 'Bangla order note', 'woo-bd-shipping' ), 'desc' => __( 'Use the label and placeholder below on the order notes field.', 'woo-bd-shipping' ) ),
					'order_note_label'  => array( 'type' => 'text', 'label' => __( 'Order note label', 'woo-bd-shipping' ) ),
					'order_note_hint'   => array( 'type' => 'text', 'label' => __( 'Order note placeholder', 'woo-bd-shipping' ) ),
				),
			),
		);
	}

	/**
	 * Register the setting, sections and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::SLUG,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		foreach ( $this->sections() as $section_id => $section ) {
			add_settings_section( 'woo_bd_' . $section_id, $section['title'], '__return_false', self::SLUG );
			foreach ( $section['fields'] as $key => $field ) {
				add_settings_field(
					$key,
					$field['label'],
					array( $this, 'render_field' ),
					self::SLUG,
					'woo_bd_' . $section_id,
					array_merge( $field, array( 'key' => $key, 'label_for' => 'woo_bd_' . $key ) )
				);
			}
		}
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-bd-shipping' ), 403 );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'BD Shipping & Checkout', 'woo-bd-shipping' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::SLUG ); // Outputs the nonce and option group fields.
		do_settings_sections( self::SLUG );
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Render one field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_field( array $args ): void {
		$key   = (string) $args['key'];
		$type  = (string) $args['type'];
		$value = Settings::get( $key );
		$name  = Settings::OPTION . '[' . $key . ']';
		$id    = 'woo_bd_' . $key;

		switch ( $type ) {
			case 'money':
				printf( '<input type="number" step="0.01" min="0" class="small-text" id="%1$s" name="%2$s" value="%3$s" />', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
				break;
			case 'text':
				printf( '<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" />', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
				break;
			case 'textarea':
				printf( '<textarea class="large-text code" rows="5" id="%1$s" name="%2$s">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
				break;
			case 'checkbox':
				printf(
					'<input type="hidden" name="%2$s" value="no" /><label><input type="checkbox" id="%1$s" name="%2$s" value="yes" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 'yes', $value, false ),
					esc_html( (string) ( $args['desc'] ?? '' ) )
				);
				return;
			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( (array) $args['options'] as $option_value => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( (string) $option_value ), selected( (string) $value, (string) $option_value, false ), esc_html( (string) $label ) );
				}
				echo '</select>';
				break;
			case 'districts':
				$selected = is_array( $value ) ? $value : array();
				printf( '<select multiple size="10" id="%1$s" name="%2$s[]">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( Plugin::districts()->labels( 'both' ) as $code => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $code ), selected( in_array( $code, $selected, true ), true, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
		}

		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( (string) $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Sanitise submitted settings.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$current = Settings::all();
		if ( ! current_user_can( self::CAPABILITY ) || ! is_array( $input ) ) {
			return $current;
		}

		$defaults  = Settings::defaults();
		$clean     = array();
		$districts = Plugin::districts();

		foreach ( $this->sections() as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				$raw = $input[ $key ] ?? null;

				switch ( $field['type'] ) {
					case 'money':
						$clean[ $key ] = RateConfig::money( is_scalar( $raw ) ? (string) $raw : 0 );
						break;
					case 'checkbox':
						$clean[ $key ] = 'yes' === $raw ? 'yes' : 'no';
						break;
					case 'select':
						$allowed       = array_map( 'strval', array_keys( (array) $field['options'] ) );
						$clean[ $key ] = is_string( $raw ) && in_array( $raw, $allowed, true ) ? $raw : $defaults[ $key ];
						break;
					case 'districts':
						$codes         = is_array( $raw ) ? array_map( 'sanitize_text_field', array_map( 'strval', $raw ) ) : array();
						$clean[ $key ] = array_values( array_unique( array_filter( $codes, array( $districts, 'has' ) ) ) );
						break;
					case 'textarea':
						$clean[ $key ] = is_string( $raw ) ? sanitize_textarea_field( $raw ) : '';
						break;
					default:
						$clean[ $key ] = is_string( $raw ) ? sanitize_text_field( $raw ) : (string) $defaults[ $key ];
				}
			}
		}

		return $clean;
	}
}
