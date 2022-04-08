<?php
/**
 * Handles support for Session API.
 *
 * @author  Sébastien Dumont
 * @package CoCart\Session API
 * @since   2.8.1
 * @version 3.4.0
 * @license GPL-2.0+
 */

namespace CoCart\SessionAPI;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Package {

	/**
	 * Initiate Package.
	 *
	 * @access public
	 */
	public function init() {
		add_action( 'cocart_rest_api_controllers', array( __CLASS__, 'include_api_controllers' ) );
		add_filter( 'cocart_rest_api_get_rest_namespaces', array( __CLASS__, 'add_rest_namespace' ) );
	}

	/**
	 * Return the name of the package.
	 *
	 * @access public
	 * @static
	 * @return string
	 */
	public static function get_name() {
		return 'CoCart Session API';
	} // END get_name()

	/**
	 * Return the version of the package.
	 *
	 * @access public
	 * @static
	 * @return string
	 */
	public static function get_version() {
		return self::$version;
	} // END get_version()

	/**
	 * Return the path to the package.
	 *
	 * @access public
	 * @static
	 * @return string
	 */
	public static function get_path() {
		return dirname( __DIR__ );
	} // END get_path()

	/**
	 * Includes the API controllers.
	 *
	 * @access public
	 * @static
	 */
	public static function include_api_controllers() {
		include_once dirname( __FILE__ ) . '/api/v2/class-cocart-session-api-controller.php';
		include_once dirname( __FILE__ ) . '/api/v2/class-cocart-sessions-api-controller.php';
	}

	/**
	 * Adds the REST API namespaces.
	 *
	 * @access public
	 * @static
	 * @return array
	 */
	public static function add_rest_namespace( $namespaces ) {
		$namespaces['cocart/v2'] = array_merge( $namespaces['cocart/v2'], self::get_v2_controllers() );

		return $namespaces;
	} // END add_rest_namespace()

	/**
	 * List of controllers in the cocart/v2 namespace.
	 *
	 * @access protected
	 * @static
	 * @return array
	 */
	protected static function get_v2_controllers() {
		return array(
			'cocart-v2-session'  => 'CoCart_Session_V2_Controller',
			'cocart-v2-sessions' => 'CoCart_Sessions_V2_Controller',
		);
	} // END get_v2_controllers()

} // END class.
