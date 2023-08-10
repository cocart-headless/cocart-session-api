<?php
/**
 * REST API: CoCart_REST_Delete_Session_v2_Controller class
 *
 * @author  Sébastien Dumont
 * @package CoCart\RESTAPI\Sessions\v2
 * @since   4.0.0 Introduced.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes a specified session.
 *
 * @since 4.0.0 Introduced.
 *
 * @see CoCart_REST_Session_v2_Controller
 */
class CoCart_REST_Delete_Session_v2_Controller extends CoCart_REST_Session_v2_Controller {

	/**
	 * Register the routes.
	 *
	 * @access public
	 */
	public function register_routes() {
		// Delete Cart in Session - cocart/v2/session/ec2b1f30a304ed513d2975b7b9f222f6 (DELETE).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<session_key>[\w]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_cart' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);
	} // register_routes()

	/**
	 * Deletes the cart session.
	 *
	 * Once the cart session has been deleted it can not be recovered.
	 *
	 * @throws CoCart_Data_Exception Exception if invalid data is detected.
	 *
	 * @access  public
	 *
	 * @since   3.0.0 Introduced.
	 * @version 3.1.0
	 *
	 * @param WP_REST_Request $request Request used to generate the response.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_cart( $request = array() ) {
		try {
			$session_key = ! empty( $request['session_key'] ) ? $request['session_key'] : '';

			if ( empty( $session_key ) ) {
				throw new CoCart_Data_Exception( 'cocart_session_key_missing', __( 'Session Key is required!', 'cart-rest-api-for-woocommerce' ), 404 );
			}

			$handler = $this->get_handler();

			// If no session is saved with the ID specified return error.
			if ( empty( $handler->get_cart( $session_key ) ) ) {
				throw new CoCart_Data_Exception( 'cocart_session_not_valid', __( 'Session is not valid!', 'cart-rest-api-for-woocommerce' ), 404 );
			}

			// Delete cart session.
			$handler->delete_cart( $session_key );

			if ( apply_filters( 'woocommerce_persistent_cart_enabled', true ) ) {
				delete_user_meta( $session_key, '_woocommerce_persistent_cart_' . get_current_blog_id() );
			}

			if ( ! empty( $handler->get_cart( $session_key ) ) ) {
				throw new CoCart_Data_Exception( 'cocart_session_not_deleted', __( 'Session could not be deleted!', 'cart-rest-api-for-woocommerce' ), 500 );
			}

			return CoCart_Response::get_response( __( 'Session successfully deleted!', 'cart-rest-api-for-woocommerce' ), $this->namespace, $this->rest_base );
		} catch ( CoCart_Data_Exception $e ) {
			return CoCart_Response::get_error_response( $e->getErrorCode(), $e->getMessage(), $e->getCode(), $e->getAdditionalData() );
		}
	} // END delete_cart()

} // END class
