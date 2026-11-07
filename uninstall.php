<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package WooBDShipping
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete this plugin's options on the current site.
 */
function woo_bd_shipping_cleanup_site(): void {
	global $wpdb;

	delete_option( 'woo_bd_shipping_settings' );

	// Shipping method instance settings: woocommerce_woo_bd_shipping_{instance}_settings.
	$pattern = $wpdb->esc_like( 'woocommerce_woo_bd_shipping_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $woo_bd_site_id ) {
		switch_to_blog( (int) $woo_bd_site_id );
		woo_bd_shipping_cleanup_site();
		restore_current_blog();
	}
} else {
	woo_bd_shipping_cleanup_site();
}
