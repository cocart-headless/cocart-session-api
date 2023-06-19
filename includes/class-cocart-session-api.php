<?php
/**
 * Handles support for Session API.
 *
 * @author  Sébastien Dumont
 * @package CoCart\Session API
 * @since   2.8.1 Introduced.
 * @version 4.0.0
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
	 *
	 * @static
	 */
	public static function init() {
		add_filter( 'cocart_rest_api_get_rest_namespaces', array( __CLASS__, 'add_rest_namespace' ) );
	}

	/**
	 * Return the name of the package.
	 *
	 * @access public
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'CoCart Session API';
	} // END get_name()

	/**
	 * Return the version of the package.
	 *
	 * @access public
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function get_version() {
		return self::$version;
	} // END get_version()

	/**
	 * Return the path to the package.
	 *
	 * @access public
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function get_path() {
		return dirname( __DIR__ );
	} // END get_path()

	/**
	 * Adds the REST API namespaces.
	 *
	 * @access public
	 *
	 * @static
	 *
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
	 *
	 * @static
	 *
	 * @return array
	 */
	protected static function get_v2_controllers() {
		return array(
			'cocart-v2-session'        => 'CoCart_REST_Session_v2_Controller',
			'cocart-v2-session-items'  => 'CoCart_REST_Session_Items_v2_Controller',
			'cocart-v2-delete-session' => 'CoCart_REST_Delete_Session_v2_Controller',
			'cocart-v2-sessions'       => 'CoCart_REST_Sessions_v2_Controller',
		);
	} // END get_v2_controllers()

} // END class.
