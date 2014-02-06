<?php
/**
 * Plugin Name: iThemes Exchange - Prorated Subscriptions Add-on
 * Plugin URI: http://ironbounddesigns.com
 * Description: Allows you to prorate subscriptions for a week, a month, or until a preset date.
 * Version: 0.1
 * Author: Iron Bound Designs
 * Author URI: http://ironbounddesigns.com
 * License: GPL2
 */
define( 'ITE_PRORATED_SUBSCRIPTIONS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );


/**
 * This registers our plugin as a membership add-on
 *
 * @since 1.0.0
 *
 * @return void
 */
function it_exchange_register_prorated_subscription_addon() {
	$options = array(
		'name'              => __( 'Prorated Subscriptions', "it-l10n-exchange-addon-prorated-subscriptions" ),
		'description'       => __( 'Allows you to prorate subscriptions for a week, a month, or until a preset date.', "it-l10n-exchange-addon-prorated-subscriptions" ),
		'author'            => 'Iron Bound Designs',
		'author_url'        => 'http://www.ironbounddesigns.com',
		'file'              => dirname( __FILE__ ) . '/init.php',
		'category'          => 'product-feature',
		'basename'          => plugin_basename( __FILE__ ),
		'labels'      => array(
			'singular_name' => __( 'Prorated Subscription', "it-l10n-exchange-addon-prorated-subscriptions" ),
		),
	);
	it_exchange_register_addon( 'prorated-subscriptions', $options );
}
add_action( 'it_exchange_register_addons', 'it_exchange_register_prorated_subscription_addon' );

/**
 * Loads the translation data for WordPress
 *
 * @uses load_plugin_textdomain()
 * @since 1.0.0
 * @return void
 */
function it_exchange_prorated_subscription_set_textdomain() {
	load_plugin_textdomain( 'it-l10n-exchange-addon-prorated-subscriptions', false, dirname( plugin_basename( __FILE__  ) ) . '/lang/' );
}
add_action( 'plugins_loaded', 'it_exchange_prorated_subscription_set_textdomain' );