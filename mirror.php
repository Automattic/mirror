<?php
/*
Plugin Name: Mirror
Description: Sync your WordPress environments
Version: 1.0-alpha
*/

class Mirror {

	const OPTION = 'mirror';
	const SLUG   = 'mirror';

	function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'init' ) );

		// Settings API
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	static function admin_menu() {
		add_management_page( __( 'Mirror', 'mirror' ), __( 'Mirror', 'mirror' ), 'manage_options', self::SLUG, array( __CLASS__, 'options_page' ) );
	}

	static function options_page() { ?>
		<div>
			<h2><?php _e( 'Site Mirroring', 'mirror' ); ?></h2>
			<?php
			self::test_connection();
			settings_errors();
			?>

			<form action="options.php" method="post">
				<?php settings_fields( self::OPTION ); ?>
				<?php do_settings_sections( self::SLUG ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
<?php }

	static function admin_init() {
		register_setting( self::OPTION, self::OPTION, array( __CLASS__, 'sanitize_text_field' ) );
		register_setting( self::OPTION, self::OPTION . '_mode', 'intval' );

		add_settings_section( self::SLUG, null, null, self::SLUG );

		add_settings_field( 'site',     __( 'Target Site', 'mirror' ),    array( __CLASS__, 'render_site' ),     self::SLUG, self::SLUG );
		add_settings_field( 'username', __( 'Username', 'mirror' ),       array( __CLASS__, 'render_username' ), self::SLUG, self::SLUG );
		add_settings_field( 'password', __( 'Password', 'mirror' ),       array( __CLASS__, 'render_password' ), self::SLUG, self::SLUG );

		add_settings_field( 'mode', __( 'Mode', 'mirror' ), array( __CLASS__, 'render_mode' ), self::SLUG, self::SLUG );
	}

	/**
	 * Tests the connection details to the remote server and display a notification message.
	 *
	 * @param array $options Optional. If set, use these values for the connection test.
	 */
	static function test_connection( $options = array() ) {

		// If in server mode, don't test connection
		if ( 0 === (int) get_option( self::OPTION . '_mode', 0 ) )
			return;

		if ( empty( $options ) )
			$options = get_option( self::OPTION );

		$password = isset( $options['password'] ) ? $options['password'] : '';
		$site     = isset( $options['site'] )     ? $options['site']     : '';
		$username = isset( $options['username'] ) ? $options['username'] : '';

		// Test if the credentials are valid
		$client = new WP_HTTP_IXR_Client( "http://{$site}/xmlrpc.php" );
		$client->query( 'mirror.pull', 'javascript', $username, $password );

		$error = '';
		if ( $client->isError() ) {
			$error = __( "There's a problem connecting to the Target Site. Please check the connection details.", 'mirror' );

			// "Incorrect username or password."
			if ( 403 === $client->getErrorCode() )
				$error = __( 'The username or password for the Target Site is incorrect.', 'mirror' );
		}

		if ( ! $error )
			return;

		// Add error
		add_settings_error( 'site', 'mirror-cloudy', $error, 'error' );
	}

	static function render_site() {
		$option = self::OPTION;
		$options = get_option( $option );
		$site = isset( $options['site'] ) ? $options['site'] : '';
		echo "<input id='{$option}_site' name='{$option}[site]' size='40' type='text' value='{$site}' />";
	}

	static function render_username() {
		$option = self::OPTION;
		$options = get_option( $option );
		$username = isset( $options['username'] ) ? $options['username'] : '';
		echo "<input id='{$option}_username' name='{$option}[username]' size='40' type='text' value='{$username}' />";
	}

	static function render_password() {
		$option = self::OPTION;
		$options = get_option( $option );
		$password = isset( $options['password' ] ) ? self::decrypt( $options['password'] ) : '';
		echo "<input id='{$option}_password' name='{$option}[password]' size='40' type='password' value='{$password}' />";
	}

	static function render_mode() {
		$option = self::OPTION . '_mode';
		$mode = get_option( $option, false );
		printf( '<input type="radio" name="%s", value="1" %s> Client<br>', $option, checked( $mode, true, false ) );
		printf( '<input type="radio" name="%s", value="0" %s> Server', $option, checked( !$mode, true, false ) );
	}

	static function sanitize_text_field( $array ) {
		$array             = array_filter( $array, 'sanitize_text_field' );
		$array['password'] = self::encrypt( $array['password'] );

		return $array;
	}

	/**
	 * Run server or client code
	 */
	static function init() {
		require_once ABSPATH . WPINC . '/class-IXR.php';
		require_once ABSPATH . WPINC . '/class-wp-http-ixr-client.php';

		// Initialize Javascript client code
		if (!class_exists('Custom_Javascript_Editor_Client'))
			require_once dirname(__FILE__) . '/clients/custom-javascript-editor.php';

		// Initialize Javascript client code
		if (!class_exists('Jetpack_CSS_Client'))
			require_once dirname(__FILE__) . '/clients/jetpack-css.php';

		// Initialize server code
		if (!class_exists('WordPress_Enterprise_Deployment_Server'))
			require_once dirname(__FILE__) . '/lib/mirror-server.php';
	}

	/**
	 * Encrypt data for sending over the wire
	 */
	public static function encrypt( $data ) {
		$data = serialize( $data );
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5(self::SLUG), $data, MCRYPT_MODE_CBC, md5(md5(self::SLUG))));
	}

	/**
	 * Decrypt data when it is recieved
	 */
	public static function decrypt( $data ) {
		$data = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5(self::SLUG), base64_decode($data), MCRYPT_MODE_CBC, md5(md5(self::SLUG))), "\0");

		if ( !$data )
			return false;

		return @unserialize( $data );
	}

}

$mirror = new Mirror();
