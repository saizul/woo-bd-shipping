<?php
/**
 * Cash on delivery fee.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping\Checkout;

use WooBDShipping\RateCalculator;
use WooBDShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a cart fee when Cash on Delivery is the chosen payment method.
 */
final class CodFee {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_fee' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Refresh totals when the payment method changes.
	 */
	public function enqueue(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || (float) Settings::get( 'cod_fee_value', 0 ) <= 0 ) {
			return;
		}
		wp_enqueue_script(
			'woo-bd-shipping-checkout',
			plugins_url( 'assets/checkout.js', WOO_BD_SHIPPING_FILE ),
			array( 'jquery', 'wc-checkout' ),
			WOO_BD_SHIPPING_VERSION,
			true
		);
	}

	/**
	 * Add the fee to the cart.
	 *
	 * @param \WC_Cart $cart Cart.
	 */
	public function add_fee( $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$type  = (string) Settings::get( 'cod_fee_type', 'fixed' );
		$value = (float) Settings::get( 'cod_fee_value', 0 );
		if ( $value <= 0 || ! WC()->session || 'cod' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		$country = WC()->customer ? WC()->customer->get_shipping_country() : '';
		if ( 'BD' !== $country ) {
			return;
		}

		// No fee when a COD restriction has hidden the gateway but the session still remembers it.
		$available = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
		if ( ! isset( $available['cod'] ) ) {
			return;
		}

		$base = (float) $cart->get_subtotal() + $this->chosen_shipping_cost();

		/**
		 * Filters the amount a percentage COD fee is calculated on.
		 *
		 * @param float $base Cart subtotal plus chosen shipping, excluding tax.
		 */
		$base = (float) apply_filters( 'woo_bd_cod_fee_base', $base );
		$fee  = RateCalculator::codFee( $base, $type, $value );

		/**
		 * Filters the final COD fee.
		 *
		 * @param float  $fee   Calculated fee.
		 * @param string $type  percent or fixed.
		 * @param float  $value Configured value.
		 */
		$fee = (float) apply_filters( 'woo_bd_cod_fee', $fee, $type, $value );

		if ( $fee > 0 ) {
			$label = (string) Settings::get( 'cod_fee_label', '' );
			$cart->add_fee( '' !== $label ? $label : __( 'Cash on delivery fee', 'woo-bd-shipping' ), $fee, false );
		}
	}

	/**
	 * Cost of the currently chosen shipping rates.
	 */
	private function chosen_shipping_cost(): float {
		$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
		$total  = 0.0;
		foreach ( WC()->shipping()->get_packages() as $index => $package ) {
			$rate_id = $chosen[ $index ] ?? '';
			if ( '' !== $rate_id && isset( $package['rates'][ $rate_id ] ) ) {
				$total += (float) $package['rates'][ $rate_id ]->get_cost();
			}
		}
		return $total;
	}
}
