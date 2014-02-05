<?php
/**
 *
 * @package ithemes-exchange-prorated-subscriptions
 * @subpackage
 * @since
 */
class IT_Exchange_Addon_Prorated_Subscriptions_Product_Feature {
	protected $feature_slug = "prorated-subscriptions";

	public function __construct() {
		if ( is_admin() ) {
			add_action( 'load-post-new.php', array( $this, 'init_feature_metaboxes' ) );
			add_action( 'load-post.php', array( $this, 'init_feature_metaboxes' ) );
			add_action( 'it_exchange_save_product', array( $this, 'save_feature_on_product_save' ) );
		}
		add_action( 'it_exchange_enabled_addons_loaded', array( $this, 'add_feature_support_to_product_types' ) );
		add_action( "it_exchange_update_product_feature_{$this->feature_slug}", array( $this, 'save_feature' ), 9, 3 );
		add_filter( "it_exchange_get_product_feature_{$this->feature_slug}", array( $this, 'get_feature' ), 9, 3 );
		add_filter( "it_exchange_product_has_feature_{$this->feature_slug}", array( $this, 'product_has_feature' ), 9, 2 );
		add_filter( "it_exchange_product_supports_feature_{$this->feature_slug}", array( $this, 'product_supports_feature' ), 9, 2 );
	}

	/**
	 * Register the product feature and add it to enabled product-type addons
	 *
	 * @since 1.0.0
	 */
	function add_feature_support_to_product_types() {
		// Register the product feature
		$slug = $this->feature_slug;
		$description = __( "Allows you to prorate subscriptions for a week, a month, or until a preset date.", IT_Exchange_Prorated_Subscriptions::DOMAIN );
		it_exchange_register_product_feature( $slug, $description );

		it_exchange_add_feature_support_to_product_type( $this->feature_slug, 'membership-product-type' );
	}

	/**
	 * Register's the metabox for any product type that supports the feature
	 *
	 * @since 1.0.0
	 * @return void
	 */
	function init_feature_metaboxes() {

		global $post;

		if ( isset( $_REQUEST['post_type'] ) ) {
			$post_type = $_REQUEST['post_type'];
		}
		else {
			if ( isset( $_REQUEST['post'] ) )
				$post_id = (int) $_REQUEST['post'];
			elseif ( isset( $_REQUEST['post_ID'] ) )
				$post_id = (int) $_REQUEST['post_ID'];
			else
				$post_id = 0;

			if ( $post_id )
				$post = get_post( $post_id );

			if ( isset( $post ) && ! empty( $post ) )
				$post_type = $post->post_type;
		}

		if ( ! empty( $_REQUEST['it-exchange-product-type'] ) )
			$product_type = $_REQUEST['it-exchange-product-type'];
		else
			$product_type = it_exchange_get_product_type( $post );

		if ( ! empty( $post_type ) && 'it_exchange_prod' === $post_type ) {
			if ( ! empty( $product_type ) && it_exchange_product_type_supports_feature( $product_type, $this->feature_slug ) )
				add_action( 'it_exchange_product_metabox_callback_' . $product_type, array( $this, 'register_metabox' ) );
		}

	}

	/**
	 * Registers the feature metabox for a specific product type
	 *
	 * Hooked to it_exchange_product_metabox_callback_[product-type] where product type supports the feature
	 *
	 * @since 1.0.0
	 * @return void
	 */
	function register_metabox() {
		add_meta_box( "it-exchange-product-feature-{$this->feature_slug}", __( 'Prorated Subscriptions', IT_Exchange_Prorated_Subscriptions::DOMAIN ), array( $this, 'print_metabox' ), 'it_exchange_prod', 'it_exchange_advanced' );
	}

	/**
	 * This echos the feature metabox.
	 *
	 * @since 1.0.0
	 *
	 * @param $post
	 *
	 * @return void
	 */
	function print_metabox( $post ) {

		$date_format = get_option( 'date_format' );
		$jquery_date_format = it_exchange_php_date_format_to_jquery_datepicker_format( $date_format );

		// Grab the iThemes Exchange Product object from the WP $post object
		$product = it_exchange_get_product( $post );

		// Set the value of the feature for this product
		$values = it_exchange_get_product_feature( $product->ID, $this->feature_slug );

		$defaults = array(
			'until-date'     => "",
			'round-type'     => "months",
			'enable-prorate' => ''
		);

		$values = ITUtility::merge_defaults( $values, $defaults );

		if ( empty( $values['until-date'] ) ) {
			$values['until-date'] = "";
		}
		else {
			$epoch = $values['until-date'];
			$date = new DateTime( "@$epoch" );
			$values['until-date'] = $date->format( $date_format );
		}

		$description = __( "These settings will allow you to prorated a subscription for a certain amount of time.", IT_Exchange_Prorated_Subscriptions::DOMAIN );
		?>

		<?php if ( $description ) : ?>
			<p class="intro-description"><?php echo $description; ?></p>
		<?php endif; ?>

		<p>
		    <label>
			    <input type="checkbox" id="prorate-subscriptions-options-enable" name="it-exchange-product-feature-prorated-subscriptions[enable-prorate]" <?php checked( $values['enable-prorate'] ); ?>>
			    <?php _e( "Enable prorating for this product?", IT_Exchange_Prorated_Subscriptions::DOMAIN ); ?>
		    </label>
	    </p>

		<div class="<?php if ( $values['enable-prorate'] !== true ) echo "hide-if-js"; ?>" id="prorate-subscriptions-options">
			<p>
			    <label><?php _e( "Base discount on the number of days, weeks, or months between now and the date you are prorating to." ) ?></label>

			    <label>
			        <input type="radio" <?php checked( $values['round-type'], 'days' ); ?> id="it-exchange-product-feature-prorated-subscriptions[round-type]-days" name="it-exchange-product-feature-prorated-subscriptions[round-type]" value="days">
				    <?php _e( "Days", IT_Exchange_Prorated_Subscriptions::DOMAIN ); ?>
			    </label>

			    <label>
			        <input type="radio" <?php checked( $values['round-type'], 'weeks' ); ?> id="it-exchange-product-feature-prorated-subscriptions[round-type]-weeks" name="it-exchange-product-feature-prorated-subscriptions[round-type]" value="weeks">
				    <?php _e( "Weeks", IT_Exchange_Prorated_Subscriptions::DOMAIN ); ?>
			    </label>

			    <label>
			        <input type="radio" <?php checked( $values['round-type'], 'months' ); ?> id="it-exchange-product-feature-prorated-subscriptions[round-type]-months" name="it-exchange-product-feature-prorated-subscriptions[round-type]" value="months">
				    <?php _e( "Months", IT_Exchange_Prorated_Subscriptions::DOMAIN ); ?>
			    </label>
		    </p>

			<p>
				<label for="it-exchange-product-feature-prorated-subscriptions[until-date]"><?php _e( 'Prorate Subscription Until', IT_Exchange_Prorated_Subscriptions::DOMAIN ); ?></label>
				<input type="text" class="datepicker" id="it-exchange-product-feature-prorated-subscriptions[until-date]" name="it-exchange-product-feature-prorated-subscriptions[until-date]" value="<?php esc_attr_e( $values['until-date'] ); ?>"/>
			</p>

		</div>

		<script type="text/javascript">
			jQuery( document ).ready( function ( $ ) {
				$( "#prorate-subscriptions-options-enable" ).click( function () {
					var options = $( "#prorate-subscriptions-options" );

					console.log(this);

					if ( $(this).attr( 'checked' ) == 'checked' )
						options.removeClass( 'hide-if-js' ).show();
					else
						options.hide();
				} );
			} );
		</script>

		<input type="hidden" name="it_exchange_prorate_subscription_date_picker_format" value="<?php echo $jquery_date_format; ?>"/>
	<?php
	}

	/**
	 * This saves the value
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function save_feature_on_product_save() {

		// Abort if we can't determine a product type
		if ( ! $product_type = it_exchange_get_product_type() )
			return;

		// Abort if we don't have a product ID
		$product_id = empty( $_POST['ID'] ) ? false : $_POST['ID'];
		if ( ! $product_id )
			return;

		// Abort if this product type doesn't support this feature
		if ( ! it_exchange_product_type_supports_feature( $product_type, $this->feature_slug ) || empty( $_POST['it-exchange-product-feature-prorated-subscriptions'] ) )
			return;

		// If the value is empty (0), delete the key, otherwise save
		if ( empty( $_POST["it-exchange-product-feature-{$this->feature_slug}"] ) )
			delete_post_meta( $product_id, "_it-exchange-product-feature-{$this->feature_slug}" );
		else {
			$post_data = $_POST["it-exchange-product-feature-{$this->feature_slug}"];

			$new_values = array();

			/*
			 * Check and sanitize the date
			 */
			$until_date = $post_data['until-date'];

			// Get the user's option set in WP General Settings
			$wp_date_format = get_option( 'date_format', 'm/d/Y' );

			// strtotime requires formats starting with day to be separated by - and month separated by /
			if ( 'd' == substr( $wp_date_format, 0, 1 ) )
				$until_date = str_replace( '/', '-', $until_date );

			// Transfer to epoch
			if ( $epoch = strtotime( $until_date ) ) {

				// Returns an array with values of each date segment
				$date = date_parse( $until_date );

				// Confirms we have a legitimate date
				if ( checkdate( $date['month'], $date['day'], $date['year'] ) )
					$new_values['until-date'] = $epoch;
			}

			/*
			 * Check and sanitize the round type.
			 */
			$round_type = $post_data['round-type'];

			$allowed_values = array( 'days', 'weeks', 'months' );

			if ( in_array( $round_type, $allowed_values ) )
				$new_values['round-type'] = $round_type;
			else
				$new_values['round-type'] = "";

			/*
			 * Check and sanitize enabled prorate
			 */
			if ( isset( $post_data['enable-prorate'] ) && $post_data['enable-prorate'] == 'on' )
				$new_values['enable-prorate'] = true;
			else
				$new_values['enable-prorate'] = false;

			/*
			 * Save the data.
			 */
			it_exchange_update_product_feature( $product_id, $this->feature_slug, $new_values );
		}
	}

	/**
	 * This updates the feature for a product
	 *
	 * @since 1.0.0
	 *
	 * @param integer $product_id the product id
	 * @param mixed $new_value the new value
	 *
	 * @return boolean
	 */
	function save_feature( $product_id, $new_value ) {
		update_post_meta( $product_id, "_it-exchange-product-feature-{$this->feature_slug}", $new_value );

		return true;
	}

	/**
	 * Return the product's features
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $existing the values passed in by the WP Filter API. Ignored here.
	 * @param integer $product_id the WordPress post ID
	 *
	 * @return array product feature
	 */
	function get_feature( $existing, $product_id ) {
		// Is the the add / edit product page?
		$current_screen = is_admin() ? get_current_screen() : false;
		$editing_product = ( ! empty( $current_screen->id ) && 'it_exchange_prod' == $current_screen->id );

		// Return the value if supported or on add/edit screen
		if ( it_exchange_product_supports_feature( $product_id, $this->feature_slug ) || $editing_product )
			return get_post_meta( $product_id, "_it-exchange-product-feature-{$this->feature_slug}", true );

		return false;
	}

	/**
	 * Does the product have the feature?
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $result Not used by core
	 * @param integer $product_id
	 *
	 * @return boolean
	 */
	function product_has_feature( $result, $product_id ) {
		// Does this product type support this feature?
		if ( false === $this->product_supports_feature( false, $product_id ) )
			return false;

		return (boolean) $this->get_feature( false, $product_id );
	}

	/**
	 * Does the product support this feature?
	 *
	 * This is different than if it has the feature, a product can
	 * support a feature but might not have the feature set.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $result Not used by core
	 * @param integer $product_id
	 *
	 * @return boolean
	 */
	function product_supports_feature( $result, $product_id ) {
		// Does this product type support this feature?
		$product_type = it_exchange_get_product_type( $product_id );
		if ( ! it_exchange_product_type_supports_feature( $product_type, $this->feature_slug ) )
			return false;

		return true;
	}
}

new IT_Exchange_Addon_Prorated_Subscriptions_Product_Feature();