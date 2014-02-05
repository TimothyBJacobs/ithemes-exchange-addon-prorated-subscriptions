<?php
/**
 * 
 * @package ithemes-exchange-prorated-subscriptions
 * @subpackage
 * @since
 */
class IT_Exchange_Prorated_Subscriptions {
	const DOMAIN = "it-l10n-exchange-addon-prorated-subscriptions";

	public function __construct() {
		add_filter( 'it_exchange_get_total_discount_for_cart', array( $this, 'modify_payment_amount' ), 10, 2 );

	}

	public function modify_payment_amount( $discount = false, $options = array() ) {
		$defaults = array(
			'format_price' => true,
		);

		$options = ITUtility::merge_defaults( $options, $defaults );

		return $discount;
	}
}
new IT_Exchange_Prorated_Subscriptions();
