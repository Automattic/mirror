<?php

class Custom_Javascript_Editor_Client {

	const SLUG = 'custom-javascript';

	function __construct() {
		add_action( 'init', array( $this, 'handle_requests' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// TODO: Server events
		add_action( 'enterprise_push_javascript', array( $this, 'enterprise_push_javascript' ) );
		add_action( 'enterprise_pull_javascript', array( $this, 'enterprise_pull_javascript' ) );
	}

	/**
	 * Handle request data
	 */
	function handle_requests() {
		if ( !isset( $_REQUEST['page'] ) || $_REQUEST['page'] != self::SLUG )
			return;

		if ( isset( $_POST['deploy'] ) ) {
			if ( check_admin_referer( self::SLUG, self::SLUG ) )
				$this->deployjs();
		} elseif ( isset( $_POST['clone'] ) ) {
			if ( check_admin_referer( self::SLUG, self::SLUG ) )
				$this->clonejs();
		} elseif ( isset( $_POST['merge'] ) ) {
			$query = http_build_query( array(
				'page' => self::SLUG
			));

			wp_safe_redirect( 'themes.php?' . $query );
		}
	}

	function add_meta_boxes() {
		global $mirror;
		$option = $mirror::OPTION;

		if ( get_option( $option . '_mode' ) )
			add_meta_box( self::SLUG, __( 'WordPress Mirror', 'mirror' ), array( $this, 'meta_box' ), 'customjs', 'normal' );
	}

	/**
	 * Display a meta box with merge, deploy, and clone buttons
	 */
	function meta_box() {
		global $mirror;
		$option = $mirror::OPTION;
		$options = get_option( $option );
		?>

		<form method="POST">
			<?php
				submit_button( __( 'Merge', 'mirror' ), 'secondary', 'merge', false );
				submit_button( __( 'Deploy', 'mirror' ), 'secondary', 'deploy', false );
				submit_button( __( 'Clone', 'mirror' ), 'secondary', 'clone', false );
				wp_nonce_field( self::SLUG, self::SLUG );
			?>
		</form>

		<?php
			if ( $options['site'] && $options['username'] && $options['password'] ) {
				$remote = $this->get_remote_js();
				$current = $this->get_js_post();
				$original = array_shift(get_posts(array(
					'numberposts' => 1,
					'post_type' => 'revision',
					'post_parent' => $current['ID'],
					'post_status' => 'inherit',
					'orderby' => 'post_date',
					'order' => 'ASC'
				)));
			}

			if ( !empty( $current ) && !empty( $original ) ):
				$original = $original->post_content;
				$current  = $current['post_content'];
				$remote   = $remote;

				// fix a merge conflict
				if ( $remote != $current && $remote != $original ): ?>
					<textarea id="f0" style="display:none"><?php echo $original; ?></textarea>
					<textarea id="f1" style="display:none"><?php echo $current; ?></textarea>
					<textarea id="f2" style="display:none"><?php echo $remote; ?></textarea>
				<?php endif; ?>
			<?php endif; ?>
<?php }

	/**
	 * Load diff3 scripts
	 */
	function admin_enqueue_scripts() {
		if ( !isset( $_REQUEST['page'] ) || $_REQUEST['page'] != self::SLUG )
			return;

		wp_enqueue_script('diff', plugins_url('diff.js', __FILE__));
		wp_enqueue_script('init', plugins_url('init.js', __FILE__), array('diff', 'jquery'));
	}

	/**
	 * Deploy javascript to remote server
	 */
	function deployjs() {
		global $mirror;
		$option = $mirror::OPTION;
		$options = get_option( $option );

		$js_post = $this->get_js_post();
		$js = isset( $_REQUEST['f3'] ) ? $_REQUEST['f3'] : $js_post['post_content'];

		$client = new WP_HTTP_IXR_Client( 'http://' . $options['site'] . '/xmlrpc.php' );
		$client->query( 'enterprise.push', 'javascript', $options['username'], $options['password'], $js );

		if ( $client->isError() )
			return false;

		$this->clonejs();
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
	 * Clone javascript from remote server
	 */
	function clonejs() {
		if ( $js = $this->get_js_post() )
			wp_delete_post( $js['ID'] );

		if ( $js = $this->get_remote_js() )
			$this->save_js_revision( $js );

		$query = http_build_query(array(
			'page' => self::SLUG
		));

		wp_safe_redirect( 'themes.php?' . $query );
		exit;
	}

	/**
	 * Retrieve remote javascript through XML-RPC
	 */
	function get_remote_js() {
		global $mirror;
		$option = $mirror::OPTION;
		$options = get_option( $option );

		$client = new WP_HTTP_IXR_Client( 'http://' . $options['site'] . '/xmlrpc.php' );
		$client->query( 'enterprise.pull', 'javascript', $options['username'], $options['password'] );

		if ( $client->isError() )
			return false;

		return $client->getResponse();
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

	// TODO
	function enterprise_push_javascript( $js ) {
		return $this->save_js_revision( $js );
	}

	// TODO
	function enterprise_pull_javascript() {
		$js_post = $this->get_js_post();
		return $js_post['post_content'];
	}

}

new Custom_Javascript_Editor_Client();
