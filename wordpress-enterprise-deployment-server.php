<?php

class WordPress_Enterprise_Deployment_Server {

	const NSPACE = 'enterprise';

	function __construct() {
		add_filter( 'xmlrpc_methods', array( $this, 'xmlrpc_methods' ) );
	}

	/**
	 * XML-RPC: enterprise.push
	 */
	function push( $args ) {
		global $wp_xmlrpc_server, $wordpress_enterprise_deployment;
		$wp_xmlrpc_server->escape( $args );

		$type     = esc_attr( $args[0] );
		$username = esc_attr( $args[1] );
		$password = $wordpress_enterprise_deployment::decrypt( $args[2] );
		$data     = esc_html( $args[3] );

		// We need a username and password
		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		// We need permission to bring in JS,
		// which should be higher than just authoring a new post
		if ( ! user_can( $user->ID, 'edit_theme_options' ) )
			return $wp_xmlrpc_server->error;

		// TODO
		// do_action( self::NSPACE . '_push_ ' . $type, $data );

		switch( $type ) {
			case 'javascript':
				$this->save_js_revision( $data );
				break;

			case 'css':
				$this->save_css_revision( $data );
				break;
		}
	}

	/**
	 * XML-RPC: enterprise.pull
	 */
	function pull( $args ) {
		global $wp_xmlrpc_server, $wordpress_enterprise_deployment;
		$wp_xmlrpc_server->escape( $args );

		$type = esc_attr( $args[0] );
		$username = esc_attr( $args[1] );
		$password = $wordpress_enterprise_deployment::decrypt( $args[2] );

		// We need a username and password
		if ( ! $user = $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		// We need permission to bring in JS,
		// which should be higher than just authoring a new post
		if ( ! user_can( $user->ID, 'edit_theme_options' ) )
			return $wp_xmlrpc_server->error;

		// TODO
		// do_action( self::NSPACE . '_pull_ ' . $type );

		switch( $type ) {
			case 'javascript':
				$js_post = $this->get_js_post();
				return $js_post['post_content'];

			case 'css':
				$css_post = $this->get_css_post();
				return $css_post['post_content'];
		}
	}

	/**
	 * List of new XML-RPC methods
	 */
	function xmlrpc_methods( $methods ) {
		$namespace = self::NSPACE;
		$methods["$namespace.push"] = array( $this, 'push' );
		$methods["$namespace.pull"] = array( $this, 'pull' );
		return $methods;
	}

	/**
	 * Save a new revision of the javascript
	 */
	function save_js_revision( $js, $is_preview = false ) {

		if ( !$js_post = $this->get_js_post() ) {
			$post = array(
				'post_content' => $js,
				'post_status' => 'publish',
				'post_type' => 'customjs'
			);

			$post_id = wp_insert_post( $post );

			return true;
		}

		$js_post['post_content'] = $js;

		if ( false === $is_preview )
			return wp_update_post( $js_post );
	}

	/**
	 * Grab the local javascript post
	 */
	function get_js_post() {
		$args = array(
			'numberposts' => 1,
			'post_type' => 'customjs',
			'post_status' => 'publish',
		);

		if ( $post = array_shift( get_posts( $args ) ) )
			return get_object_vars( $post );

		return false;
	}

	/**
	 * Save a new revision of the css
	 */
	function save_css_revision( $css, $is_preview = false ) {

		if ( !$css_post = $this->get_css_post() ) {
			$post = array(
				'post_content' => $css,
				'post_status' => 'publish',
				'post_type' => 'safecss'
			);

			$post_id = wp_insert_post( $post );

			return true;
		}

		$css_post['post_content'] = $css;

		if ( false === $is_preview )
			return wp_update_post( $css_post );
	}

	/**
	 * Grab the local css post
	 */
	function get_css_post() {
		$args = array(
			'numberposts' => 1,
			'post_type' => 'safecss',
			'post_status' => 'publish',
		);

		if ( $post = array_shift( get_posts( $args ) ) )
			return get_object_vars( $post );

		return false;
	}

}

new WordPress_Enterprise_Deployment_Server();
