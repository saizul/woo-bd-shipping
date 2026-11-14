<?php
/**
 * Cash on delivery availability: hides COD when a rule blocks it.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping\Checkout;

use WooBDShipping\CodRules;
use WooBDShipping\Plugin;
use WooBDShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Removes the Cash on Delivery gateway at checkout when the order total, item count or
 * delivery district is outside the configured limits, explains why, and rejects a COD
 * order that slips through (for example a stale checkout form).
 */
final class CodAvailability {

	public const GATEWAY = 'cod';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ) );
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'print_notice' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 10, 2 );
	}

	/**
	 * Rules built from the saved settings.
	 */
	public static function rules(): CodRules {
		return CodRules::fromSettings( Settings::all(), Plugin::districts() );
	}

	/**
	 * Remove COD from the available gateways when it is blocked.
	 *
	 * @param array<string,mixed> $gateways Available gateways keyed by id.
	 * @return array<string,mixed>
	 */
	public function filter_gateways( $gateways ) {
		if ( ! is_array( $gateways ) || ! isset( $gateways[ self::GATEWAY ] ) || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $gateways;
		}
		if ( null !== $this->current_reason() ) {
			unset( $gateways[ self::GATEWAY ] );
		}
		return $gateways;
	}

	/**
	 * Explain above the payment methods why COD is missing.
	 */
	public function print_notice(): void {
		if ( ! Settings::enabled( 'cod_restriction_notice' ) ) {
			return;
		}
		$reason = $this->current_reason();
		if ( null === $reason ) {
			return;
		}
		$message = $this->message_for( $reason );
		if ( '' !== $message ) {
			echo '<div class="woocommerce-info woo-bd-cod-notice">' . esc_html( $message ) . '</div>';
		}
	}

	/**
	 * Reject a COD order that a rule blocks.
	 *
	 * @param array<string,mixed> $data   Posted checkout data.
	 * @param \WP_Error           $errors Validation errors.
	 */
	public function validate( $data, $errors ): void {
		if ( ! is_array( $data ) || self::GATEWAY !== ( $data['payment_method'] ?? '' ) ) {
			return;
		}
		$country = (string) ( ! empty( $data['ship_to_different_address'] ) ? ( $data['shipping_country'] ?? '' ) : ( $data['billing_country'] ?? '' ) );
		$state   = (string) ( ! empty( $data['ship_to_different_address'] ) ? ( $data['shipping_state'] ?? '' ) : ( $data['billing_state'] ?? '' ) );
		$reason  = $this->reason_for( $country, $state );
		if ( null !== $reason ) {
			$errors->add( 'woo_bd_cod_unavailable', $this->message_for( $reason ) );
		}
	}

	/**
	 * Blocking reason for the current cart and customer, or null.
	 */
	private function current_reason(): ?string {
		if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
			return null;
		}
		return $this->reason_for( (string) WC()->customer->get_shipping_country(), (string) WC()->customer->get_shipping_state() );
	}

	/**
	 * Blocking reason for a destination, or null. Only Bangladeshi deliveries are restricted.
	 *
	 * @param string $country Shipping country code.
	 * @param string $state   Shipping state / district value.
	 */
	private function reason_for( string $country, string $state ): ?string {
		if ( 'BD' !== $country || ! WC()->cart || WC()->cart->is_empty() ) {
			return null;
		}
		$rules = self::rules();
		if ( ! $rules->isActive() ) {
			return null;
		}

		$code = Plugin::districts()->resolve( $state );

		/**
		 * Filters the amount the COD minimum and maximum are compared against.
		 *
		 * @param float $total Cart total including shipping, fees and tax.
		 */
		$total  = (float) apply_filters( 'woo_bd_cod_order_total', (float) WC()->cart->get_total( 'edit' ) );
		$items  = (int) WC()->cart->get_cart_contents_count();
		$reason = $rules->blockedReason( $total, $items, $code );

		/**
		 * Filters why COD is blocked. Return null to allow COD, or a reason string to block it.
		 *
		 * @param string|null $reason One of the CodRules::REASON_* values, or null when allowed.
		 * @param float       $total  Order total used for the limits.
		 * @param int         $items  Number of items in the cart.
		 * @param string|null $code   District code, null when unknown.
		 */
		$reason = apply_filters( 'woo_bd_cod_blocked_reason', $reason, $total, $items, $code );
		return is_string( $reason ) && '' !== $reason ? $reason : null;
	}

	/**
	 * Customer-facing explanation for a reason.
	 *
	 * @param string $reason CodRules::REASON_* value.
	 */
	private function message_for( string $reason ): string {
		$rules = self::rules();
		switch ( $reason ) {
			case CodRules::REASON_BELOW_MIN:
				/* translators: {min} is replaced with the minimum order amount, keep it unchanged. */
				$template = __( 'Cash on delivery is available for orders of {min} or more.', 'woo-bd-shipping' );
				break;
			case CodRules::REASON_ABOVE_MAX:
				/* translators: {max} is replaced with the maximum order amount, keep it unchanged. */
				$template = __( 'Cash on delivery is available for orders up to {max}. Please pay online.', 'woo-bd-shipping' );
				break;
			case CodRules::REASON_TOO_MANY:
				/* translators: {items} is replaced with the item limit, keep it unchanged. */
				$template = __( 'Cash on delivery is available for up to {items} items per order.', 'woo-bd-shipping' );
				break;
			case CodRules::REASON_DISTRICT:
				$template = __( 'Cash on delivery is not available for your district. Please pay online.', 'woo-bd-shipping' );
				break;
			case CodRules::REASON_NO_DISTRICT:
				$template = __( 'Select your district to see if cash on delivery is available.', 'woo-bd-shipping' );
				break;
			default:
				$template = __( 'Cash on delivery is not available for this order.', 'woo-bd-shipping' );
		}

		return CodRules::message(
			$template,
			array(
				'min'   => $this->price( $rules->minTotal ),
				'max'   => $this->price( $rules->maxTotal ),
				'items' => (string) $rules->maxItems,
			)
		);
	}

	/**
	 * Plain text price.
	 *
	 * @param float $amount Amount.
	 */
	private function price( float $amount ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}
}
