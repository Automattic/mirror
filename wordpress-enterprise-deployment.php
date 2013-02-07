<?php
/*
Plugin Name: WordPress.com Enterprise Deployment
Description: Deploy CSS and Javascript to your WordPress.com Enterprise website.
Version: 1.0
*/

class WordPress_Enterprise_Deployment {

	const OPTION = 'wordpress-enterprise-deployment';
	const SLUG   = 'wordpress-enterprise-deployment';

	function __construct() {
		add_action( 'plugins_loaded', array( __CLASS__, 'init' ) );

		// Settings API
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	static function admin_menu() {
		add_options_page( __( 'WordPress Enterprise Deployment', 'wordpress-enterprise-deployment' ), __( 'Deployment', 'wordpress-enterprise-deployment' ), 'manage_options', self::SLUG, array( __CLASS__, 'options_page' ) );
	}

	static function options_page() { ?>
		<div>
			<h2><?php _e( 'WordPress Enterprise Deployment', 'wordpress-enterprise-deployment' ); ?></h2>
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

		add_settings_field( 'site',     __( 'Site', 'wordpress-enterprise-deployment' ),     array( __CLASS__, 'render_site' ),     self::SLUG, self::SLUG );
		add_settings_field( 'username', __( 'Username', 'wordpress-enterprise-deployment' ), array( __CLASS__, 'render_username' ), self::SLUG, self::SLUG );
		add_settings_field( 'password', __( 'Password', 'wordpress-enterprise-deployment' ), array( __CLASS__, 'render_password' ), self::SLUG, self::SLUG );

		add_settings_field( 'mode', __( 'Mode', 'wordpress-enterprise-deployment' ), array( __CLASS__, 'render_mode' ), self::SLUG, self::SLUG );

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
		$array = array_filter( $array, 'sanitize_text_field' );

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
		if (!class_exists('WordPress_Enterprise_CJE_Client'))
			require_once dirname(__FILE__) . '/wordpress-enterprise-cje.php';

		// Initialize Javascript client code
		if (!class_exists('WordPress_Enterprise_CSS_Client'))
			require_once dirname(__FILE__) . '/wordpress-enterprise-css.php';

		// Initialize server code
		if (!class_exists('WordPress_Enterprise_Deployment_Server'))
			require_once dirname(__FILE__) . '/wordpress-enterprise-deployment-server.php';
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

$wordpress_enterprise_deployment = new WordPress_Enterprise_Deployment();
