<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       Author uri
 * @since      0.0.1
 *
 * @package    Emoji_Reactions
 * @subpackage Emoji_Reactions/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Emoji_Reactions
 * @subpackage Emoji_Reactions/public
 * @author     Author name <Author mail>
 */
class Emoji_Reactions_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.0.1
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/emoji-reactions-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name . '-public-js', plugin_dir_url( __FILE__ ) . 'js/emoji-reactions-public.js', array( 'jquery' ), $this->version, false );

		wp_localize_script( $this->plugin_name . '-public-js' , 'emoji_reactions', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ));

	}

	/**
	 * display_buttons
	 */
	public function display_buttons($args) {
		$type = !empty($args['type']) ? $args['type'] : 'post';
		$ID = !empty($args['ID']) ? $args['ID'] : get_the_ID();

		ob_start();
		require plugin_dir_path( dirname( __FILE__ ) ) . 'public/partials/emoji-reactions-public-display.php';
		return ob_get_clean();
	}

	/**
	 * ajax
	 */
	public function emoji_reactions_ajax_save_action() {
		$object_id = $_POST['object_id'];
		$object_type = $_POST['object_type'];
		$emoji = $_POST['emoji'];
		$user_id = get_current_user_id();

		if ($_POST['unlike'] === 'true') {
			echo 'User ' . $user_id . ' unliked ' . $emoji;
		} else {
			echo 'User ' . $user_id . ' voted ' . $emoji . ' for ' . $object_id . ' / ' . $object_type;
		}

		die();
	}
}
