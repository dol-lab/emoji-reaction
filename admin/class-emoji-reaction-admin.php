<?php

/**
 * The admin-specific functionality of the plugin.
 *
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/admin
 */
class Emoji_Reaction_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private string $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param    string $plugin_name The name of this plugin.
	 * @param    string $version     The version of this plugin.
	 */
	public function __construct( string $plugin_name, string $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'admin_post_emoji_reaction_add_chart', array( $this, 'handle_add_chart' ) );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 */
	public function enqueue_styles(): void {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/emoji-reaction-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 */
	public function enqueue_scripts(): void {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/emoji-reaction-admin.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Handle adding emoji reaction chart to post.
	 *
	 */
	public function handle_add_chart(): void {
		// Verify nonce and get post ID
		if ( ! isset( $_GET['post_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'emoji-reaction' ) );
		}

		$post_id = intval( $_GET['post_id'] );
		$nonce   = sanitize_text_field( $_GET['_wpnonce'] );

		// Verify nonce
		if ( ! wp_verify_nonce( $nonce, 'emoji_reaction_add_chart_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'emoji-reaction' ) );
		}

		// Check if post exists
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_die( esc_html__( 'Post not found.', 'emoji-reaction' ) );
		}

		// Check if user can edit this post
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this post.', 'emoji-reaction' ) );
		}

		// Check if chart block already exists (multiple patterns to be thorough)
		$has_chart      = false;
		$chart_patterns = array(
			'wp:emoji-reaction/chart',
			'[emoji_reaction_chart',  // Shortcode version
			'emoji-reaction-chart-container', // Rendered HTML
		);

		foreach ( $chart_patterns as $pattern ) {
			if ( strpos( $post->post_content, $pattern ) !== false ) {
				$has_chart = true;
				break;
			}
		}

		// Also check for blocks with different post_id attributes
		if ( ! $has_chart ) {
			if ( preg_match( '/wp:emoji-reaction\/chart\s*{[^}]*}/', $post->post_content ) ) {
				$has_chart = true;
			}
		}

		// Only add chart if it doesn't already exist
		if ( ! $has_chart ) {
			// Add the chart block to the post content
			$chart_block     = "\n\n<!-- wp:emoji-reaction/chart {\"post_id\":" . $post_id . '} /-->';
			$updated_content = $post->post_content . $chart_block;

			// Update the post
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $updated_content,
				)
			);

			// Add success message via query parameter
			$redirect_url = add_query_arg( 'emoji_chart_added', '1', get_permalink( $post_id ) );
		} else {
			// Add message that chart already exists
			$redirect_url = add_query_arg( 'emoji_chart_exists', '1', get_permalink( $post_id ) );
		}

		// Redirect to the post frontend
		wp_redirect( $redirect_url );
		exit;
	}
}
