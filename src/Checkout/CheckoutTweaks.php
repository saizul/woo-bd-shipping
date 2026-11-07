<?php
/**
 * Checkout customisations.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping\Checkout;

use WooBDShipping\PhoneNormalizer;
use WooBDShipping\Plugin;
use WooBDShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * District select, upazila field, phone validation and field clean-up. Each part is toggleable.
 */
final class CheckoutTweaks {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_states', array( $this, 'bd_states' ) );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'locale' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ), 20 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_phone' ), 10, 2 );
		add_filter( 'woocommerce_process_checkout_field_billing_phone', array( $this, 'normalise_phone' ) );
		add_filter( 'woocommerce_process_checkout_field_shipping_phone', array( $this, 'normalise_phone' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_billing_upazila' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'show_shipping_upazila' ) );
	}

	/**
	 * Replace the Bangladesh state list with districts.
	 *
	 * @param array<string,array<string,string>> $states States by country.
	 * @return array<string,array<string,string>>
	 */
	public function bd_states( $states ) {
		if ( ! Settings::enabled( 'district_select' ) || ! is_array( $states ) ) {
			return $states;
		}
		$lang = (string) Settings::get( 'district_lang', 'both' );
		$states['BD'] = Plugin::districts()->labels( in_array( $lang, array( 'en', 'bn', 'both' ), true ) ? $lang : 'both' );
		return $states;
	}

	/**
	 * Locale tweaks for Bangladesh.
	 *
	 * @param array<string,array<string,mixed>> $locales Locales.
	 * @return array<string,array<string,mixed>>
	 */
	public function locale( $locales ) {
		if ( ! is_array( $locales ) ) {
			return $locales;
		}
		$bd = isset( $locales['BD'] ) && is_array( $locales['BD'] ) ? $locales['BD'] : array();

		if ( Settings::enabled( 'district_select' ) ) {
			$bd['state'] = array_merge(
				$bd['state'] ?? array(),
				array(
					'label'    => __( 'District', 'woo-bd-shipping' ),
					'required' => true,
				)
			);
		}
		if ( Settings::enabled( 'remove_postcode' ) ) {
			$bd['postcode'] = array_merge(
				$bd['postcode'] ?? array(),
				array(
					'required' => false,
					'hidden'   => true,
				)
			);
		}
		if ( Settings::enabled( 'remove_company' ) ) {
			$bd['company'] = array_merge( $bd['company'] ?? array(), array( 'hidden' => true ) );
		}

		$locales['BD'] = $bd;
		return $locales;
	}

	/**
	 * Adjust checkout fields.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $fields Checkout fields.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public function checkout_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		foreach ( array( 'billing', 'shipping' ) as $group ) {
			if ( Settings::enabled( 'remove_postcode' ) ) {
				unset( $fields[ $group ][ $group . '_postcode' ] );
			}
			if ( Settings::enabled( 'remove_company' ) ) {
				unset( $fields[ $group ][ $group . '_company' ] );
			}
			if ( Settings::enabled( 'enable_upazila' ) && isset( $fields[ $group ] ) ) {
				$priority = (int) ( $fields[ $group ][ $group . '_city' ]['priority'] ?? 70 );
				$fields[ $group ][ $group . '_upazila' ] = array(
					'label'       => __( 'Upazila / Thana', 'woo-bd-shipping' ),
					'placeholder' => __( 'Upazila or thana (optional)', 'woo-bd-shipping' ),
					'required'    => false,
					'class'       => array( 'form-row-wide' ),
					'priority'    => $priority + 1,
				);
			}
		}

		if ( Settings::enabled( 'require_phone' ) && isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['required'] = true;
		}
		if ( Settings::enabled( 'validate_phone' ) && isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['placeholder']  = '01XXXXXXXXX';
			$fields['billing']['billing_phone']['autocomplete'] = 'tel-national';
		}

		if ( Settings::enabled( 'bangla_order_note' ) && isset( $fields['order']['order_comments'] ) ) {
			$label = (string) Settings::get( 'order_note_label', '' );
			$hint  = (string) Settings::get( 'order_note_hint', '' );
			if ( '' !== $label ) {
				$fields['order']['order_comments']['label'] = $label;
			}
			if ( '' !== $hint ) {
				$fields['order']['order_comments']['placeholder'] = $hint;
			}
		}

		return $fields;
	}

	/**
	 * Validate Bangladeshi mobile numbers.
	 *
	 * @param array<string,mixed> $data   Posted checkout data.
	 * @param \WP_Error           $errors Validation errors.
	 */
	public function validate_phone( $data, $errors ): void {
		if ( ! Settings::enabled( 'validate_phone' ) || ! is_array( $data ) || ! $errors instanceof \WP_Error ) {
			return;
		}

		foreach ( array( 'billing', 'shipping' ) as $group ) {
			$phone = trim( (string) ( $data[ $group . '_phone' ] ?? '' ) );
			if ( '' === $phone || 'BD' !== ( $data[ $group . '_country' ] ?? '' ) ) {
				continue;
			}
			if ( ! PhoneNormalizer::isValid( $phone ) ) {
				$label = 'billing' === $group ? __( 'Billing phone', 'woo-bd-shipping' ) : __( 'Shipping phone', 'woo-bd-shipping' );
				$errors->add(
					$group . '_phone_invalid',
					sprintf(
						/* translators: %s: field label */
						__( '%s must be a valid Bangladeshi mobile number, for example 01712345678.', 'woo-bd-shipping' ),
						'<strong>' . esc_html( $label ) . '</strong>'
					),
					array( 'id' => $group . '_phone' )
				);
			}
		}
	}

	/**
	 * Store valid numbers in the local 11 digit form.
	 *
	 * @param mixed $value Posted value.
	 * @return mixed
	 */
	public function normalise_phone( $value ) {
		if ( ! Settings::enabled( 'validate_phone' ) || ! is_string( $value ) ) {
			return $value;
		}
		$normalised = PhoneNormalizer::normalize( $value );
		return null === $normalised ? $value : $normalised;
	}

	/**
	 * Show the billing upazila on the admin order screen.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function show_billing_upazila( $order ): void {
		$this->print_upazila( $order, '_billing_upazila' );
	}

	/**
	 * Show the shipping upazila on the admin order screen.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function show_shipping_upazila( $order ): void {
		$this->print_upazila( $order, '_shipping_upazila' );
	}

	/**
	 * Print an upazila meta value.
	 *
	 * @param mixed  $order    Order.
	 * @param string $meta_key Meta key.
	 */
	private function print_upazila( $order, string $meta_key ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$value = (string) $order->get_meta( $meta_key );
		if ( '' === $value ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'Upazila / Thana:', 'woo-bd-shipping' ) . '</strong> ' . esc_html( $value ) . '</p>';
	}
}
