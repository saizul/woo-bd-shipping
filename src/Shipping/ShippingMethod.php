<?php
/**
 * Bangladesh shipping method.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping\Shipping;

use WooBDShipping\DeliveryEstimate;
use WooBDShipping\Plugin;
use WooBDShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Zone based shipping method for Bangladesh. Rates live on the plugin settings page.
 */
class ShippingMethod extends \WC_Shipping_Method {

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping zone instance id.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );

		$this->id                 = 'woo_bd_shipping';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'BD Shipping', 'woo-bd-shipping' );
		$this->method_description = __( 'District based rates for Bangladesh. Configure rates under WooCommerce > BD Shipping.', 'woo-bd-shipping' );
		$this->supports           = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title      = (string) $this->get_option( 'title', __( 'Delivery charge', 'woo-bd-shipping' ) );
		$this->tax_status = (string) $this->get_option( 'tax_status', 'none' );
	}

	/**
	 * Instance form fields.
	 */
	public function init_form_fields(): void {
		$this->instance_form_fields = array(
			'title'      => array(
				'title'       => __( 'Method title', 'woo-bd-shipping' ),
				'type'        => 'text',
				'default'     => __( 'Delivery charge', 'woo-bd-shipping' ),
				'description' => __( 'Label shown to the customer at checkout.', 'woo-bd-shipping' ),
				'desc_tip'    => true,
			),
			'tax_status' => array(
				'title'   => __( 'Tax status', 'woo-bd-shipping' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'none',
				'options' => array(
					'taxable' => __( 'Taxable', 'woo-bd-shipping' ),
					'none'    => _x( 'None', 'Tax status', 'woo-bd-shipping' ),
				),
			),
		);
	}

	/**
	 * Calculate and add the rate for a package.
	 *
	 * @param array<string,mixed> $package Shipping package.
	 */
	public function calculate_shipping( $package = array() ): void {
		$destination = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
		if ( 'BD' !== ( $destination['country'] ?? '' ) ) {
			return;
		}

		$code = Plugin::districts()->resolve( (string) ( $destination['state'] ?? '' ) );
		if ( null === $code ) {
			return;
		}

		$calculator = Plugin::calculator();
		$subtotal   = (float) ( $package['contents_cost'] ?? 0 );
		$cost       = $calculator->calculate( $code, $this->package_weight( $package ), $subtotal );
		if ( null === $cost ) {
			return;
		}

		/**
		 * Filters the calculated shipping cost.
		 *
		 * @param float  $cost     Calculated cost.
		 * @param string $code     District code, e.g. BD-13.
		 * @param array  $package  WooCommerce shipping package.
		 * @param string $zone     inside_dhaka, sub_area or outside.
		 */
		$zone = $calculator->zoneFor( $code );
		$cost = (float) apply_filters( 'woo_bd_shipping_rate', $cost, $code, $package, $zone );

		$label    = $this->title;
		$meta     = array();
		$estimate = $this->delivery_estimate( $code, $zone, $package );
		if ( '' !== $estimate ) {
			$label = DeliveryEstimate::decorate( (string) Settings::get( 'delivery_label', '' ), $estimate, $label );
			$meta[ __( 'Estimated delivery', 'woo-bd-shipping' ) ] = $estimate;
		}

		$this->add_rate(
			array(
				'id'        => $this->get_rate_id(),
				'label'     => $label,
				'cost'      => max( 0.0, $cost ),
				'package'   => $package,
				'meta_data' => $meta,
			)
		);
	}

	/**
	 * Estimated delivery time for a district, or an empty string when the estimate is off or not configured.
	 *
	 * @param string              $code    District code.
	 * @param string              $zone    inside_dhaka, sub_area or outside.
	 * @param array<string,mixed> $package Shipping package.
	 */
	private function delivery_estimate( string $code, string $zone, array $package ): string {
		if ( ! Settings::enabled( 'delivery_estimate' ) ) {
			return '';
		}

		$words = array(
			'same_day' => __( 'Same day', 'woo-bd-shipping' ),
			/* translators: %s: number of days, always 1. */
			'day'      => __( '%s day', 'woo-bd-shipping' ),
			/* translators: %s: number of days or a range such as 2-3. */
			'days'     => __( '%s days', 'woo-bd-shipping' ),
		);
		$format = DeliveryEstimate::FORMAT_DATES === Settings::get( 'delivery_format' ) ? DeliveryEstimate::FORMAT_DATES : DeliveryEstimate::FORMAT_DAYS;

		$estimate = Plugin::deliveryEstimate()->estimate(
			$code,
			$zone,
			current_datetime(),
			$format,
			$words,
			static fn ( \DateTimeImmutable $date ): string => (string) wp_date( 'j M', $date->getTimestamp() )
		);

		/**
		 * Filters the delivery estimate text. Return an empty string to hide it.
		 *
		 * @param string $estimate Estimate such as "2-3 days" or "14 Nov - 16 Nov".
		 * @param string $code     District code, e.g. BD-13.
		 * @param string $zone     inside_dhaka, sub_area or outside.
		 * @param array  $package  WooCommerce shipping package.
		 */
		return (string) apply_filters( 'woo_bd_delivery_estimate', $estimate, $code, $zone, $package );
	}

	/**
	 * Total package weight in kilograms.
	 *
	 * @param array<string,mixed> $package Shipping package.
	 */
	private function package_weight( array $package ): float {
		$weight = 0.0;
		foreach ( (array) ( $package['contents'] ?? array() ) as $item ) {
			if ( empty( $item['data'] ) || ! is_object( $item['data'] ) || ! $item['data']->has_weight() ) {
				continue;
			}
			$weight += (float) wc_get_weight( (float) $item['data']->get_weight(), 'kg' ) * (int) ( $item['quantity'] ?? 1 );
		}
		return $weight;
	}
}
