<?php
/**
 * REST API: CoCart_REST_Session_v2_Controller class
 *
 * @author  Sébastien Dumont
 * @package CoCart\RESTAPI\Sessions\v2
 * @since   3.0.0 Introduced.
 * @version 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns details of a specific cart session.
 *
 * @see CoCart_REST_Cart_v2_Controller
 */
class CoCart_REST_Session_v2_Controller extends CoCart_REST_Cart_v2_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'cocart/v2';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'session';

	/**
	 * Total defaults.
	 *
	 * @var array
	 */
	protected $totals = array(
		'subtotal'            => 0,
		'subtotal_tax'        => 0,
		'shipping_total'      => 0,
		'shipping_tax'        => 0,
		'shipping_taxes'      => array(),
		'discount_total'      => 0,
		'discount_tax'        => 0,
		'cart_contents_total' => 0,
		'cart_contents_tax'   => 0,
		'cart_contents_taxes' => array(),
		'fee_total'           => 0,
		'fee_tax'             => 0,
		'fee_taxes'           => array(),
		'total'               => 0,
		'total_tax'           => 0,
	);

	/**
	 * Caches the session data requested so we don't have to pass it into every function.
	 *
	 * @var array
	 */
	protected $session_data = array();

	/**
	 * Register the routes for index.
	 *
	 * @access public
	 */
	public function register_routes() {
		// Get Cart in Session - cocart/v2/session/ec2b1f30a304ed513d2975b7b9f222f6 (GET).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<session_key>[\w]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cart_in_session' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	} // register_routes()

	/**
	 * Check whether a given request has permission to read site data.
	 *
	 * @throws CoCart\DataException Exception if invalid data is detected.
	 *
	 * @access public
	 *
	 * @since 3.0.0 Introduced.
	 * @since 4.0.0 Use namespace for DataException.
	 * @since 4.0.0 Added check for the access token maybe required.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		try {
			$access_token         = cocart_get_setting( 'general', 'access_token' );
			$require_access_token = cocart_get_setting( 'general', 'require_access_token' );

			if ( $require_access_token === 'yes' && ! empty( $access_token ) ) {
				$requested_token = $request->get_header( 'x-cocart-access-token' );

				// Validate requested token.
				if ( ! empty( $requested_token ) && ! wp_is_uuid( $requested_token ) ) {
					throw new \CoCart\DataException( 'cocart_rest_invalid_token', __( 'Invalid token provided.', 'cart-rest-api-for-woocommerce' ), rest_authorization_required_code() );
				}

				// If token matches then proceed.
				if ( $access_token == $requested_token ) {
					return true;
				} else {
					throw new \CoCart\DataException( 'cocart_rest_permission_denied', __( 'Permission Denied.', 'cart-rest-api-for-woocommerce' ), rest_authorization_required_code() );
				}
			}

			if ( ! wc_rest_check_manager_permissions( 'settings', 'read' ) ) {
				throw new \CoCart\DataException( 'cocart_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'cart-rest-api-for-woocommerce' ), rest_authorization_required_code() );
			}
		} catch ( \CoCart\DataException $e ) {
			return CoCart_Response::get_error_response( $e->getErrorCode(), $e->getMessage(), $e->getCode(), $e->getAdditionalData() );
		}

		return true;
	} // END get_items_permissions_check()

	/**
	 * Get's the session handler.
	 *
	 * @access protected
	 *
	 * @since 4.0.0 Introduced.
	 *
	 * @return object
	 */
	protected function get_handler() {
		// Load session handler.
		include_once COCART_ABSPATH . 'includes/abstracts/abstract-cocart-session.php';

		$current_db_version = get_option( 'cocart_db_version', null );
		$session_upgraded   = get_option( 'cocart_session_upgraded', '' );

		if ( version_compare( $current_db_version, COCART_DB_VERSION, '==' ) && $session_upgraded === COCART_DB_VERSION ) {
			include_once COCART_ABSPATH . 'includes/classes/class-cocart-session-handler.php';
		} else {
			include_once COCART_ABSPATH . 'includes/classes/legacy/class-cocart-session-handler.php';
		}

		return new \CoCart\Session\Handler();
	} // END get_handler()

	/**
	 * Returns a saved cart in session if one exists.
	 *
	 * @throws CoCart\DataException Exception if invalid data is detected.
	 *
	 * @access public
	 *
	 * @since   2.1.0 Introduced.
	 * @version 3.1.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response Returns the cart data from the database.
	 */
	public function get_cart_in_session( $request = array() ) {
		$session_key = ! empty( $request['session_key'] ) ? $request['session_key'] : '';

		try {
			// The cart key is a required variable.
			if ( empty( $session_key ) ) {
				throw new \CoCart\DataException( 'cocart_session_key_missing', __( 'Session Key is required!', 'cart-rest-api-for-woocommerce' ), 404 );
			}

			$handler = $this->get_handler();

			// Get the cart in the database.
			$cart = maybe_unserialize( $handler->get_cart( $session_key ) );

			// If no cart is saved with the ID specified return error.
			if ( empty( $cart ) ) {
				throw new \CoCart\DataException( 'cocart_cart_in_session_not_valid', __( 'Cart in session is not valid!', 'cart-rest-api-for-woocommerce' ), 404 );
			}

			// Cache session data.
			$this->session_data = $cart;

			return CoCart_Response::get_response( $this->return_session_data( $request ), $this->namespace, $this->rest_base );
		} catch ( \CoCart\DataException $e ) {
			return CoCart_Response::get_error_response( $e->getErrorCode(), $e->getMessage(), $e->getCode(), $e->getAdditionalData() );
		}
	} // END get_cart_in_session()

	/**
	 * Return session data.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 * @since 4.0.0 Deprecated $session_data parameter and now filter response via requested fields.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array $session
	 */
	public function return_session_data( $request = array() ) {
		// Customer.
		$customer = '';

		if ( isset( $this->session_data['customer'] ) ) {
			$customer = maybe_unserialize( $this->session_data['customer'] );
		}

		/**
		 * Gets requested fields to return in the response.
		 *
		 * @since 4.0.0 Introduced.
		 */
		$fields = $this->get_fields_for_response( $request );

		// Session response container.
		$session = array();

		if ( rest_is_field_included( 'cart_key', $fields ) ) {
			$session['cart_key'] = $request['session_key'];
		}

		if ( rest_is_field_included( 'customer', $fields ) ) {
			$session['customer'] = array(
				'billing_address'  => $this->get_customer_fields( 'billing', $this->get_customer( $customer ) ),
				'shipping_address' => $this->get_customer_fields( 'shipping', $this->get_customer( $customer ) ),
			);
		}

		if ( rest_is_field_included( 'items', $fields ) ) {
			if ( isset( $this->session_data['cart_cache'] ) ) {
				$session['items'] = $this->get_items( maybe_unserialize( $this->session_data['cart_cache'] ), $request );
			} else {
				$session['items'] = $this->get_items( maybe_unserialize( $this->session_data['cart'] ), $request );
			}
		}

		if ( rest_is_field_included( 'item_count', $fields ) ) {
			$session['item_count'] = $this->get_cart_contents_count();
		}

		if ( rest_is_field_included( 'items_weight', $fields ) ) {
			$session['items_weight'] = wc_get_weight( (float) $this->get_cart_contents_weight(), get_option( 'woocommerce_weight_unit' ) );
		}

		if ( rest_is_field_included( 'coupons', $fields ) ) {
			$session['coupons'] = array();

			// Returns each coupon applied and coupon total applied if store has coupons enabled.
			$coupons = wc_coupons_enabled() ? $this->get_applied_coupons() : array();

			if ( ! empty( $coupons ) ) {
				foreach ( $coupons as $coupon ) {
					$session['coupons'][] = array(
						'coupon'      => wc_format_coupon_code( wp_unslash( $coupon ) ),
						'label'       => esc_attr( wc_cart_totals_coupon_label( $coupon, false ) ),
						'saving'      => $this->coupon_html( $coupon, false ),
						'saving_html' => $this->coupon_html( $coupon ),
					);
				}
			}
		}

		if ( rest_is_field_included( 'needs_payment', $fields ) ) {
			$session['needs_payment'] = $this->needs_payment();
		}

		if ( rest_is_field_included( 'fees', $fields ) ) {
			$session['fees'] = $this->get_fees( $request );
		}

		if ( rest_is_field_included( 'totals', $fields ) ) {
			$session['totals'] = $this->get_cart_totals( $request, $fields );
		}

		if ( rest_is_field_included( 'removed_items', $fields ) ) {
			$session['removed_items'] = $this->get_removed_items( $this->get_removed_cart_contents(), $request );
		}

		/**
		 * Filters the session before it is returned.
		 *
		 * @since 4.0.0 Introduced.
		 *
		 * @param array           $session The session before it's returned.
		 * @param WP_REST_Request $request The request object.
		 * @param object          $this    The session controller.
		 */
		return apply_filters( 'cocart_session', $session, $request, $this );
	} // END return_session_data()

	/**
	 * Gets the cart items.
	 *
	 * Note: This function mirrors the `get_items()` function in the cart controller
	 * but is not accessing the cart instance directly so the data is in "read only" mode.
	 *
	 * @access public
	 *
	 * @ignore Function ignored when parsed into Code Reference.
	 *
	 * @since 3.0.0 Introduced.
	 * @since 4.0.0 Added new parameter `$request` (REST API request) to allow more arguments to be passed.
	 *
	 * @deprecated 4.0.0 No longer use `$show_thumb` as parameter.
	 *
	 * @see CoCart_REST_Cart_v2_Controller::get_items()
	 *
	 * @param array           $cart_contents The cart contents passed.
	 * @param WP_REST_Request $request       The request object.
	 *
	 * @return array $items Returns all items in the cart.
	 */
	public function get_items( $cart_contents = array(), $request = array() ) {
		$items = array();

		foreach ( $cart_contents as $item_key => $cart_item ) {
			// If product data is missing then get product data and apply.
			if ( ! isset( $cart_item['data'] ) ) {
				$cart_item['data'] = wc_get_product( $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'] );
			}

			$_product = $cart_item['data'];

			$items[ $item_key ] = $this->get_item( $_product, $cart_item, $request );

			/**
			 * Filter allows additional data to be returned for a specific item in cart.
			 *
			 * @since 2.1.0 Introduced.
			 * @since 4.0.0 Added `$request` (REST API request) as parameter.
			 *
			 * @param array           $items     Array of items in the cart.
			 * @param string          $item_key  The item key currently looped.
			 * @param array           $cart_item The item in the cart containing the default cart item data.
			 * @param WC_Product      $_product  The product object.
			 * @param WP_REST_Request $request   The request object.
			 */
			$items = apply_filters( 'cocart_cart_items', $items, $item_key, $cart_item, $_product, $request );
		}

		return $items;
	} // END get_items()

	/**
	 * Get a single item from the session and present the data required.
	 *
	 * Note: This function mirrors the `get_item()` function in the cart controller
	 * but is not accessing the cart instance directly so the data is in "read only" mode.
	 *
	 * @access public
	 *
	 * @ignore Function ignored when parsed into Code Reference.
	 *
	 * @since 3.1.0 Introduced.
	 * @since 4.0.0 Added new parameter `$request` (REST API request) to allow more arguments to be passed.
	 *
	 * @deprecated 4.0.0 No longer use `$show_thumb` as parameter.
	 *
	 * @see CoCart_REST_Cart_v2_Controller::get_item()
	 *
	 * @param WC_Product      $_product     The product object.
	 * @param array           $cart_item    The item in the cart containing the default cart item data.
	 * @param WP_REST_Request $request      The request object.
	 * @param boolean         $removed_item Determines if the item in the cart is removed.
	 *
	 * @return array $item Full details of the item in the cart and it's purchase limits.
	 */
	public function get_item( $_product, $cart_item = array(), $request = array(), $removed_item = false ) {
		$show_thumb = ! empty( $request['thumb'] ) ? $request['thumb'] : false;

		$item_key = $cart_item['key'];

		/**
		 * Filter allows the item quantity to be changed.
		 *
		 * The quantity may need to show as a different quantity depending on the product added.
		 *
		 * @param float                      Original Quantity
		 * @param string          $item_key  Item key of the item in the cart.
		 * @param array           $cart_item The item in the cart containing the default cart item data.
		 * @param WP_REST_Request $request   The request object.
		 */
		$quantity   = apply_filters( 'cocart_cart_item_quantity', $cart_item['quantity'], $item_key, $cart_item, $request );
		$dimensions = $_product->get_dimensions( false );

		// Item container.
		$item = array();

		$item['item_key'] = $item_key;
		$item['id']       = $_product->get_id();
		$item['name']     = apply_filters( 'cocart_cart_item_name', $_product->get_name(), $_product, $cart_item, $item_key );
		$item['title']    = apply_filters( 'cocart_cart_item_title', $_product->get_title(), $_product, $cart_item, $item_key );
		$item['price']    = apply_filters( 'cocart_cart_item_price', wc_format_decimal( $_product->get_price(), wc_get_price_decimals() ), $cart_item, $item_key, $request );
		$item['quantity'] = array(
			'value'        => (float) $quantity,
			'min_purchase' => $_product->get_min_purchase_quantity(),
			'max_purchase' => $_product->get_max_purchase_quantity(),
		);
		$item['totals']   = array(
			'subtotal'     => apply_filters( 'cocart_cart_item_subtotal', $cart_item['line_subtotal'], $cart_item, $item_key, $request ),
			'subtotal_tax' => apply_filters( 'cocart_cart_item_subtotal_tax', $cart_item['line_subtotal_tax'], $cart_item, $item_key, $request ),
			'total'        => apply_filters( 'cocart_cart_item_total', $cart_item['line_total'], $cart_item, $item_key, $request ),
			'tax'          => apply_filters( 'cocart_cart_item_tax', $cart_item['line_tax'], $cart_item, $item_key, $request ),
		);
		$item['slug']     = $this->get_product_slug( $_product );
		$item['meta']     = array(
			'product_type' => $_product->get_type(),
			'sku'          => $_product->get_sku(),
			'dimensions'   => ! empty( $dimensions ) ? array(
				'length' => $dimensions['length'],
				'width'  => $dimensions['width'],
				'height' => $dimensions['height'],
				'unit'   => get_option( 'woocommerce_dimension_unit' ),
			) : array(),
			'weight'       => wc_get_weight( (float) $_product->get_weight() * (int) $cart_item['quantity'], get_option( 'woocommerce_weight_unit' ) ),
			'variation'    => isset( $cart_item['variation'] ) ? cocart_format_variation_data( $cart_item['variation'], $_product ) : array(),
		);

		// Backorder notification.
		$item['backorders'] = $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ? wp_kses_post( apply_filters( 'cocart_cart_item_backorder_notification', esc_html__( 'Available on backorder', 'cart-rest-api-for-woocommerce' ), $_product->get_id() ) ) : '';

		// Prepares the remaining cart item data.
		$cart_item = $this->prepare_item( $cart_item );

		/**
		 * Filter allows you to alter the remaining cart item data.
		 *
		 * @since 3.0.0 Introduced.
		 *
		 * @param array  $cart_item Cart item data.
		 * @param string $item_key  Item key of the item in the cart.
		 */
		$cart_item_data = apply_filters( 'cocart_cart_item_data', $cart_item, $item_key );

		// Returns remaining cart item data.
		$cart_item_data         = ! empty( $cart_item ) ? $cart_item_data : array();
		$item['cart_item_data'] = $cart_item_data;

		// If thumbnail is requested then add it to each item in cart.
		$item['featured_image'] = $show_thumb ? $this->get_item_thumbnail( $_product, $cart_item, $item_key, $removed_item ) : '';

		/**
		 * Filter allows plugin extensions to apply additional information.
		 *
		 * @since 4.0.0 Introduced.
		 *
		 * @param array           $cart_item Cart item data.
		 * @param string          $item_key  Item key of the item in the cart.
		 * @param WP_REST_Request $request   The request object.
		 */
		$item['extensions'] = apply_filters( 'cocart_cart_item_extensions', array(), $cart_item_data, $item_key, $request );

		return $item;
	} // END get_item()

	/**
	 * Gets the array of applied coupon codes.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return array of applied coupons
	 */
	public function get_applied_coupons() {
		return (array) maybe_unserialize( $this->session_data['applied_coupons'] );
	} // END get_applied_coupons()

	/**
	 * Get number of items in the cart.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return int
	 */
	public function get_cart_contents_count() {
		return array_sum( wp_list_pluck( maybe_unserialize( $this->session_data['cart'] ), 'quantity' ) );
	} // END get_cart_contents_count()

	/**
	 * Get weight of items in the cart.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float
	 */
	public function get_cart_contents_weight() {
		$weight = 0.0;

		$cart_contents = maybe_unserialize( $this->session_data['cart'] );

		foreach ( $cart_contents as $item_key => $cart_item ) {
			// Product data will be missing so we need to apply it.
			if ( ! isset( $cart_item['data'] ) ) {
				$cart_item['data'] = wc_get_product( $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'] );
			}

			if ( $cart_item['data']->has_weight() ) {
				$weight += (float) $cart_item['data']->get_weight() * $cart_item['quantity'];
			}
		}

		return $weight;
	} // END get_cart_contents_weight()

	/**
	 * Looks at the totals to see if payment is actually required.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return bool
	 */
	public function needs_payment() {
		return 0 < $this->get_total();
	} // END needs_payment()

	/**
	 * Get cart fees.
	 *
	 * Note: This function mirrors the `get_fees()` function in the cart controller
	 * but is not accessing the cart fees API directly so the data is in "read only" mode.
	 *
	 * @access public
	 *
	 * @ignore Function ignored when parsed into Code Reference.
	 *
	 * @since 3.1.0 Introduced.
	 * @since 4.0.0 Added `$request` (REST API request) as a parameter.
	 *
	 * @see CoCart_REST_Cart_v2_Controller::get_fees()
	 *
	 * @return array
	 */
	public function get_fees( $request = array() ) {
		$cart_fees = isset( $this->session_data['cart_fees'] ) ? maybe_unserialize( $this->session_data['cart_fees'] ) : array();

		$fees = array();

		if ( ! empty( $cart_fees ) ) {
			foreach ( $cart_fees as $key => $fee ) {
				$key = str_replace( 'cocart-', '', $key );

				$fees[ $key ] = array(
					'name' => esc_html( $fee['name'] ),
					'fee'  => apply_filters( 'cocart_cart_fee_amount', $fee['amount'], $request ),
				);
			}
		}

		return $fees;
	} // END get_fees()

	/**
	 * Get the fee value.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @param array $fee Fee data.
	 *
	 * @return string Returns the fee value.
	 */
	public function fee_html( $fee = array() ) {
		$cart_totals_fee_html = $this->display_prices_including_tax() ? cocart_price_no_html( $fee['total'] + $fee['tax'] ) : cocart_price_no_html( $fee['total'] );

		return apply_filters( 'cocart_cart_totals_fee_html', $cart_totals_fee_html, $fee );
	} // END fee_html()

	/**
	 * Get a total.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @param string $key Key of element in $totals array.
	 *
	 * @return mixed
	 */
	protected function get_totals_var( $key = '' ) {
		$totals = maybe_unserialize( $this->session_data['cart_totals'] );

	} // END get_totals_var()

	/**
	 * Get subtotal.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float
	 */
	public function get_subtotal() {
		return $this->get_totals_var( 'subtotal' );
	} // END get_subtotal()

	/**
	 * Get subtotal_tax.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float
	 */
	public function get_subtotal_tax() {
		return $this->get_totals_var( 'subtotal_tax' );
	} // END get_subtotal_tax()

	/**
	 * Get discount_total.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float
	 */
	public function get_discount_total() {
		return $this->get_totals_var( 'discount_total' );
	} // END get_discount_total()

	/**
	 * Get discount_tax.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float
	 */
	public function get_discount_tax() {
		return $this->get_totals_var( 'discount_tax' );
	} // END get_discount_tax()

	/**
	 * Get shipping_total.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float
	 */
	public function get_shipping_total() {
		return $this->get_totals_var( 'shipping_total' );
	} // END get_shipping_total()

	/**
	 * Get shipping_tax.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float
	 */
	public function get_shipping_tax() {
		return $this->get_totals_var( 'shipping_tax' );
	} // END get_shipping_tax()

	/**
	 * Gets cart total after calculation.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float|string
	 */
	public function get_total() {
		return $this->get_totals_var( 'total' );
	} // END get_total()

	/**
	 * Get total tax amount.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float
	 */
	public function get_total_tax() {
		return $this->get_totals_var( 'total_tax' );
	} // END get_total_tax()

	/**
	 * Get total fee amount.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float
	 */
	public function get_fee_total() {
		return $this->get_totals_var( 'fee_total' );
	} // END get_fee_total()

	/**
	 * Get total fee tax amount.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return float
	 */
	public function get_fee_tax() {
		return $this->get_totals_var( 'fee_tax' );
	} // END get_fee_tax()

	/**
	 * Get shipping taxes.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 */
	public function get_shipping_taxes() {
		return $this->get_totals_var( 'shipping_taxes' );
	} // END get_shipping_taxes()

	/**
	 * Get cart content taxes.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 */
	public function get_cart_contents_taxes() {
		return $this->get_totals_var( 'cart_contents_taxes' );
	} // END get_cart_contents_taxes()

	/**
	 * Get fee taxes.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 */
	public function get_fee_taxes() {
		return $this->get_totals_var( 'fee_taxes' );
	} // END get_fee_taxes()

	/**
	 * Return whether or not the cart is displaying prices including tax, rather than excluding tax.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return bool
	 */
	public function display_prices_including_tax() {
		return 'incl' === $this->get_tax_price_display_mode();
	} // END display_prices_including_tax()

	/**
	 * Returns 'incl' if tax should be included in cart, otherwise returns 'excl'.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return string
	 */
	public function get_tax_price_display_mode() {
		$customer = '';

		if ( isset( $this->session_data['customer'] ) ) {
			$customer = maybe_unserialize( $this->session_data['customer'] );
		}

		if ( $this->get_customer( $customer ) && $this->get_customer( $customer )->get_is_vat_exempt() ) {
			return 'excl';
		}

		return get_option( 'woocommerce_tax_display_cart' );
	} // END get_tax_price_display_mode()

	/**
	 * Get cart's owner.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @param mixed $customer Customer object or ID.
	 *
	 * @return WC_Customer $customer Customer object or ID.
	 */
	public function get_customer( $customer = 0 ) {
		if ( is_numeric( $customer ) ) {
			$user = get_user_by( 'id', $customer );

			// If user id does not exist then set as new customer.
			if ( is_wp_error( $user ) ) {
				$customer = 0;
			}
		}

		return new WC_Customer( $customer, true );
	} // END get_customer()

	/**
	 * Return items removed from the cart.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced.
	 *
	 * @return array
	 */
	public function get_removed_cart_contents() {
		return (array) maybe_unserialize( $this->session_data['removed_cart_contents'] );
	} // END get_removed_cart_contents()

	/**
	 * Get cart totals.
	 *
	 * Returns the cart subtotal, fees, discounted total, shipping total
	 * and total of the cart.
	 *
	 * @access public
	 *
	 * @since 4.0.0 Introduced.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @param array           $fields  An array of requested fields for the session response to return.
	 *
	 * @return array Cart totals.
	 */
	public function get_cart_totals( $request = array(), $fields = array() ) {
		$totals = array(
			'subtotal'       => $this->get_subtotal(),
			'subtotal_tax'   => $this->get_subtotal_tax(),
			'fee_total'      => $this->get_fee_total(),
			'fee_tax'        => $this->get_fee_tax(),
			'discount_total' => $this->get_discount_total(),
			'discount_tax'   => $this->get_discount_tax(),
			'shipping_total' => $this->get_shipping_total(),
			'shipping_tax'   => $this->get_shipping_tax(),
			'total'          => $this->get_total( 'edit' ),
			'total_tax'      => $this->get_total_tax(),
		);

		if ( ! in_array( 'fees', $fields ) ) {
			unset( $totals['fee_total'] );
			unset( $totals['fee_tax'] );
		}

		if ( ! in_array( 'shipping', $fields ) ) {
			unset( $totals['shipping_total'] );
			unset( $totals['shipping_tax'] );
		}

		/**
		 * Filters the session totals.
		 *
		 * @since 4.0.0 Introduced.
		 *
		 * @param array           $totals  Session totals.
		 * @param WP_REST_Request $request The request object.
		 * @param array           $fields  An array of requested fields for the session response to return.
		 */
		return apply_filters( 'cocart_session_totals', $totals, $request, $fields );
	} // END get_cart_totals()

	/**
	 * Retrieves the item schema for returning the session.
	 *
	 * @access public
	 *
	 * @since 4.0.0 Introduced.
	 *
	 * @return array Public item schema data.
	 */
	public function get_public_item_schema() {
		$schema = parent::get_public_item_schema();

		$schema['title'] = 'cocart_session';

		unset( $schema['properties']['cart_hash'] );
		unset( $schema['properties']['currency'] );
		unset( $schema['properties']['needs_shipping'] );
		unset( $schema['properties']['shipping'] );
		unset( $schema['properties']['cross_sells'] );
		unset( $schema['properties']['notices'] );

		return $schema;
	} // END get_public_item_schema()

	/**
	 * Get the query params for getting the session.
	 *
	 * @access public
	 *
	 * @since 4.0.0 Introduced.
	 *
	 * @return array $params The query params.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		unset( $params['cart_key'] );
		unset( $params['thumb'] );
		unset( $params['default'] );

		$defaults = get_option( 'cocart_settings', array() );
		$defaults = ! empty( $defaults[ 'session' ] ) ? $defaults[ 'session' ] : array();

		$params['prices']['default']   = ! empty( $defaults['session_prices'] ) ? $defaults['session_prices'] : 'raw';
		$params['response']['default'] = ! empty( $defaults['session_response'] ) ? $defaults['session_response'] : 'default';

		$params['response']['enum'] = array(
			'default',
			'digital',
			'digital_fees',
			'removed_items',
		);

		return $params;
	} // END get_collection_params()

} // END class
