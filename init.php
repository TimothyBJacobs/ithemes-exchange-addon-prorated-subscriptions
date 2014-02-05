<?php
/**
 *
 * @package ithemes-exchange-prorated-subscriptions
 * @subpackage
 * @since
 */
require( 'lib/product-features/load.php' );

class IT_Exchange_Prorated_Subscriptions {
	const DOMAIN = "it-l10n-exchange-addon-prorated-subscriptions";

	/**
	 *
	 */
	public function __construct() {
		add_filter( 'it_exchange_get_cart_product_base_price', array( $this, 'prorate_product' ), 9999, 3 );
		add_action( 'it_exchange_add_transaction_success', array( $this, 'modify_renewal_time' ), 9999 );
	}

	/**
	 * @param $transaction_id int
	 *
	 * @return void
	 */
	public function modify_renewal_time( $transaction_id ) {
		$transaction = it_exchange_get_transaction( $transaction_id );

		$cart_object = get_post_meta( $transaction->ID, '_it_exchange_cart_object', true );

		foreach ( $cart_object->products as $product ) {
			$product = it_exchange_get_product( $product['product_id'] );
			$feature = it_exchange_get_product_feature( $product->ID, 'prorated-subscriptions' );

			if ( false === $this->is_valid_product_for_modification( $product ) )
				continue;

			$transaction->update_transaction_meta( 'subscription_expires_' . $product->ID, $feature['until-date'] );
		}
	}

	/**
	 * @param $db_base_price int
	 * @param $product array
	 * @param $format boolean
	 *
	 * @return float
	 */
	public function prorate_product( $db_base_price, $product, $format = true ) {

		$db_base_price = self::remove_currency_format($db_base_price);

		if ( ! $db_product = it_exchange_get_product( $product['product_id'] ) )
			return ( $format === true ) ? it_exchange_format_price( $db_base_price ) : $db_base_price;

		if ( false === $this->is_valid_product_for_modification( $db_product ) )
			return ( $format === true ) ? it_exchange_format_price( $db_base_price ) : $db_base_price;

		$feature = it_exchange_get_product_feature( $db_product->ID, 'prorated-subscriptions' );

		$epoch = $feature['until-date'];
		$target_date = new DateTime( "@$epoch" );

		switch ( $feature['round-type'] ) {
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
	 * @param $price int
	 * @param $target_date DateTime
	 *
	 * @return float
	 */
	protected function prorate_product_days( $price, $target_date ) {
		$diff = $target_date->diff( new DateTime() );
		$days_before_date = $diff->d;

		if ( false == $days_before_date )
			return $price;

		$price_per_day = $price / 365;

		$prorate_amount = $days_before_date * $price_per_day;

		return $prorate_amount;

	}

	/**
	 * @param $price int
	 * @param $target_date DateTime
	 *
	 * @return float
	 */
	protected function prorate_product_weeks( $price, $target_date ) {
		$diff = $target_date->diff( new DateTime() );
		$days_before_date = $diff->d;

		$weeks_before_date = round( $days_before_date / 7 );

		if ( false == $weeks_before_date )
			return $price;

		$price_per_week = $price / 52;

		$prorate_amount = $weeks_before_date * $price_per_week;

		return $prorate_amount;
	}

	/**
	 * @param $price int
	 * @param $target_date DateTime
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
	 * @param $product IT_Exchange_Product
	 *
	 * @return bool
	 */
	protected function is_valid_product_for_modification( $product ) {
		$valid = true;

		if ( ! it_exchange_product_supports_feature( $product->ID, 'prorated-subscriptions' ) )
			$valid = false;

		$features = it_exchange_get_product_feature( $product->ID, 'prorated-subscriptions' );

		if ( ! isset( $features['enable-prorate'] ) || $features['enable-prorate'] !== true )
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
	 * @return int
	 */
	public static function remove_currency_format( $string ) {
		$before = $after = '';
		$settings = it_exchange_get_option( 'settings_general' );
		$currency = it_exchange_get_currency_symbol( $settings['default-currency'] );

		if ( 'after' === $settings['currency-symbol-position'] )
			$after = $currency;
		else
			$before = $currency;

		$string = str_replace( $before, "", $string );
		$string = str_replace( $after, "", $string );

		return (int) $string;
	}
}

new IT_Exchange_Prorated_Subscriptions();
