<?php

/**
 *
 * @package iThemes Exchange Prorated Subscriptions Addon
 * @subpackage Core
 * @since 1.0
 */
class IT_Exchange_Prorated_Subscriptions {
	const DOMAIN = "it-l10n-exchange-addon-prorated-subscriptions";

	/**
	 * Constructor for IT_Exchange_Prorated_Subscriptions objects.
	 */
	public function __construct() {
		add_filter( 'it_exchange_get_cart_product_base_price', array( $this, 'prorate_product' ), 9999, 3 );
		add_action( 'it_exchange_add_transaction_success', array( $this, 'modify_renewal_time' ), 9999 );
		add_action( 'it_exchange_super_widget_checkout_after_price_element', array( $this, 'add_prorated_label' ) );
		add_action( 'it_exchange_super_widget_cart_after_item_price_element', array( $this, 'add_prorated_label' ) );
	}

	/**
	 * Display a label saying this price is prorated.
	 */
	public function add_prorated_label() {
		$product = $GLOBALS[ 'it_exchange' ][ 'product' ];

		if ( ! $this->is_valid_product_for_modification( $product ) )
			return;

		echo '<span class="it_exchange_payment_label" style="padding:5px;background:#ECECEC;border-radius:3px;font-size:9px;text-transform: uppercase;margin-left:5px;color:#02A302;">prorated</span>';
	}

	/**
	 * Modifies the time that an subscription should be renewed.
	 *
	 * It is set to the until date specified in the features metabox.
	 *
	 * @param $transaction_id int
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public function modify_renewal_time( $transaction_id ) {
		$transaction = it_exchange_get_transaction( $transaction_id );

		$cart_object = get_post_meta( $transaction->ID, '_it_exchange_cart_object', true );

		foreach ( $cart_object->products as $product ) {
			$product = it_exchange_get_product( $product[ 'product_id' ] );
			$feature = it_exchange_get_product_feature( $product->ID, 'prorated-subscriptions' );

			if ( false === $this->is_valid_product_for_modification( $product ) )
				continue;

			$transaction->update_transaction_meta( 'subscription_expires_' . $product->ID, $feature[ 'until-date' ] );
		}
	}

	/**
	 * Apply the actual price discount to the product.
	 *
	 * @param $db_base_price int
	 * @param $product array
	 * @param $format boolean
	 *
	 * @since 1.0
	 *
	 * @return float/string
	 */
	public function prorate_product( $db_base_price, $product, $format = true ) {

		$db_base_price = self::remove_currency_format( $db_base_price );

		if ( ( $db_product = it_exchange_get_product( $product[ 'product_id' ] ) ) === false )
			return ( $format === true ) ? it_exchange_format_price( $db_base_price ) : $db_base_price;

		if ( false === $this->is_valid_product_for_modification( $db_product ) )
			return ( $format === true ) ? it_exchange_format_price( $db_base_price ) : $db_base_price;

		$feature = it_exchange_get_product_feature( $db_product->ID, 'prorated-subscriptions' );

		$epoch = $feature[ 'until-date' ];
		$target_date = new DateTime( "@$epoch" );

		switch ( $feature[ 'round-type' ] ) {
			case 'days' :
				$db_base_price = $this->prorate_product_days( $db_base_price, $target_date );
				break;
			case 'weeks' :
				$db_base_price = $this->prorate_product_weeks( $db_base_price, $target_date );
				break;
			case 'months' :
			default :
				$db_base_price = $this->prorate_product_months( $db_base_price, $target_date );
				break;
		}

		if ( $format )
			$db_base_price = it_exchange_format_price( $db_base_price );

		return $db_base_price;
	}

	/**
	 * Applies discount based on number of days.
	 *
	 * @param $price int
	 * @param $target_date DateTime
	 *
	 * @since 1.0
	 *
	 * @return float
	 */
	protected function prorate_product_days( $price, $target_date ) {
		$diff = $target_date->diff( new DateTime() );
		$days_before_date = $diff->days;

		if ( false == $days_before_date )
			return $price;

		$price_per_day = $price / 365;

		$prorate_amount = $days_before_date * $price_per_day;

		return $prorate_amount;

	}

	/**
	 * Applies discount based on number of weeks.
	 *
	 * @param $price int
	 * @param $target_date DateTime
	 *
	 * @since 1.0
	 *
	 * @return float
	 */
	protected function prorate_product_weeks( $price, $target_date ) {
		$diff = $target_date->diff( new DateTime() );
		$days_before_date = $diff->days;

		$weeks_before_date = round( $days_before_date / 7 );

		if ( false == $weeks_before_date )
			return $price;

		$price_per_week = $price / 52;

		$prorate_amount = $weeks_before_date * $price_per_week;

		return $prorate_amount;
	}

	/**
	 * Apples discount based on number of months.
	 *
	 * @param $price int
	 * @param $target_date DateTime
	 *
	 * @since 1.0
	 *
	 * @return float
	 */
	protected function prorate_product_months( $price, $target_date ) {
		$diff = $target_date->diff( new DateTime() );
		$months_before_date = $diff->m;

		if ( false == $months_before_date )
			return $price;

		$price_per_month = $price / 12;

		$prorate_amount = $months_before_date * $price_per_month;

		return $prorate_amount;
	}

	/**
	 * Determine if a product should be prorated.
	 *
	 * @param $product IT_Exchange_Product
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	protected function is_valid_product_for_modification( $product ) {
		$valid = true;

		if ( ! it_exchange_product_supports_feature( $product->ID, 'prorated-subscriptions' ) )
			$valid = false;

		$features = it_exchange_get_product_feature( $product->ID, 'prorated-subscriptions' );

		if ( ! isset( $features[ 'enable-prorate' ] ) || $features[ 'enable-prorate' ] !== true )
			$valid = false;

		if ( ! it_exchange_product_supports_feature( $product->ID, 'recurring-payments' ) )
			$valid = false;

		if ( 'yearly' != ( $time = it_exchange_get_product_feature( $product->ID, 'recurring-payments', array( 'setting' => 'time' ) ) ) && 'forever' != $time )
			$valid = false;

		return apply_filters( 'it_exchange_prorated_subscriptions_valid_product_for_modification', $valid, $product );
	}

	/**
	 * Remove the currency format.
	 *
	 * @param $string string
	 *
	 * @since 1.0
	 *
	 * @return float
	 */
	public static function remove_currency_format( $string ) {
		$before = $after = '';
		$settings = it_exchange_get_option( 'settings_general' );
		$currency = it_exchange_get_currency_symbol( $settings[ 'default-currency' ] );
		$decimal = $settings[ 'currency-decimals-separator' ];
		$thousands = $settings[ 'currency-thousands-separator' ];

		if ( 'after' === $settings[ 'currency-symbol-position' ] )
			$after = $currency;
		else
			$before = $currency;

		$string = str_replace( $before, "", $string );
		$string = str_replace( $after, "", $string );
		$string = str_replace( $thousands, "", $string );
		$string = str_replace( $decimal, ".", $string );

		return (float) $string;
	}
}

new IT_Exchange_Prorated_Subscriptions();