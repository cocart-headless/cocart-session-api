<?php
/**
 * REST API: CoCart_REST_Session_Items_v2_Controller class
 *
 * @author  Sébastien Dumont
 * @package CoCart\RESTAPI\Sessions\v2
 * @since   4.0.0 Introduced.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns items from a specific session.
 *
 * @since 4.0.0 Introduced.
 *
 * @see CoCart_REST_Session_v2_Controller
 */
class CoCart_REST_Session_Items_v2_Controller extends CoCart_REST_Session_v2_Controller {

	/**
	 * Register the routes.
	 *
	 * @access public
	 */
	public function register_routes() {
		// Get Cart Items in Session - cocart/v2/session/ec2b1f30a304ed513d2975b7b9f222f6/items (GET).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<session_key>[\w]+)/items',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cart_items_in_session' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	} // register_routes()

	/**
	 * Returns the cart items from the session.
	 *
	 * @throws CoCart\DataException Exception if invalid data is detected.
	 *
	 * @access public
	 *
	 * @since 3.0.0 Introduced.
	 * @since 4.0.0 Use namespace for DataException.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response Returns the cart items from the session.
	 */
	public function get_cart_items_in_session( $request = array() ) {
		$session_key = ! empty( $request['session_key'] ) ? $request['session_key'] : '';
		$show_thumb  = ! empty( $request['thumb'] ) ? $request['thumb'] : false;

		try {
			// The cart key is a required variable.
			if ( empty( $session_key ) ) {
				throw new \CoCart\DataException( 'cocart_session_key_missing', __( 'Session Key is required!', 'cart-rest-api-for-woocommerce' ), 404 );
			}

			$handler = $this->get_handler();

			// Get the cart in the database.
			$cart = $handler->get_cart( $session_key );

			// If no cart is saved with the ID specified return error.
			if ( empty( $cart ) ) {
				throw new \CoCart\DataException( 'cocart_cart_in_session_not_valid', __( 'Cart in session is not valid!', 'cart-rest-api-for-woocommerce' ), 404 );
			}

			return CoCart_Response::get_response( $this->get_items( maybe_unserialize( $cart['cart'] ), $show_thumb ), $this->namespace, $this->rest_base );
		} catch ( \CoCart\DataException $e ) {
			return CoCart_Response::get_error_response( $e->getErrorCode(), $e->getMessage(), $e->getCode(), $e->getAdditionalData() );
		}
	} // END get_cart_items_in_session()

} // END class
