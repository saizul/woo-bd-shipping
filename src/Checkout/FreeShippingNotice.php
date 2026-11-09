<?php
/**
 * "Add X more for free delivery" notice and shortcode.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping\Checkout;

use WooBDShipping\FreeShipping;
use WooBDShipping\RateConfig;
use WooBDShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shows how far the cart is from the free shipping threshold, on the cart and checkout pages
 * (when enabled in the settings) and through the [woo_bd_free_shipping] shortcode.
 */
final class FreeShippingNotice {

	public const SHORTCODE = 'woo_bd_free_shipping';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
		add_action( 'woocommerce_before_cart', array( $this, 'print_notice' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'print_notice' ) );
	}

	/**
	 * Print the notice when the setting is on.
	 */
	public function print_notice(): void {
		if ( ! Settings::enabled( 'free_shipping_notice' ) ) {
			return;
		}
		$message = $this->message();
		if ( '' !== $message ) {
			wc_print_notice( esc_html( $message ), 'notice' );
		}
	}

	/**
	 * Shortcode output.
	 */
	public function shortcode(): string {
		$message = $this->message();
		return '' === $message ? '' : '<p class="woo-bd-free-shipping">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Current message, or an empty string when nothing should be shown.
	 */
	public function message(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return '';
		}

		// The threshold only applies to Bangladesh deliveries; an unset country is treated as the home market.
		$country = WC()->customer ? WC()->customer->get_shipping_country() : '';
		if ( '' !== $country && 'BD' !== $country ) {
			return '';
		}

		$threshold = RateConfig::money( Settings::get( 'free_threshold', 0 ) );
		$remaining = FreeShipping::remaining( (float) WC()->cart->get_cart_contents_total(), $threshold );

		$pending = trim( (string) Settings::get( 'free_shipping_message', '' ) );
		if ( '' === $pending ) {
			/* translators: {amount} is replaced with the missing amount, keep it unchanged. */
			$pending = __( 'Add {amount} more to get free delivery.', 'woo-bd-shipping' );
		}

		return FreeShipping::message(
			$remaining,
			null === $remaining ? '' : html_entity_decode( wp_strip_all_tags( wc_price( $remaining ) ), ENT_QUOTES, 'UTF-8' ),
			$pending,
			__( 'You have unlocked free delivery.', 'woo-bd-shipping' )
		);
	}
}
