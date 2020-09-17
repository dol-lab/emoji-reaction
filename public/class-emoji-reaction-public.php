<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       Author uri
 * @since      0.0.1
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/public
 * @author     Author name <Author mail>
 */
class Emoji_Reaction_Public {

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
	 * The meta key to save the likes in post meta.
	 *
	 * @since    0.0.2
	 * @access   private
	 * @var      string    $meta_key    The meta key.
	 */
	private $meta_key;

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

		$this->meta_key = '_' . $plugin_name . '_likes';

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/emoji-reaction-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name . '-public-js', plugin_dir_url( __FILE__ ) . 'js/emoji-reaction-public.js', array( 'jquery' ), $this->version, false );

		wp_localize_script( $this->plugin_name . '-public-js' , 'emoji_reaction', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ));

	}

	/**
 	 * Displays emoji buttons.
 	 *
 	 * The callback function added as a filter, which can be applied in the theme to display the emoji buttons.
 	 *
 	 * @since 0.0.1
 	 *
 	 * @param 	array 	$args {
 	 *     Required. An array of elements, that identify the object to get liked or null.
 	 *
 	 *     @type int 'ID' The post or comment ID. Default is the value of 'get_the_ID' function.
 	 *     @type string 'type' The type of object. Accepts 'post' or 'comment'. Default 'post'.
	  * }
	  
 	 * @return 	html 	HTML of emoji buttons.
 	 */
	public function display_buttons($args) {
		$emojis = Emoji_Reaction::get_emojis();

		$type = !empty($args['type']) ? $args['type'] : 'post';
		$ID = !empty($args['ID']) ? $args['ID'] : get_the_ID();
		$likes = $this->get_likes($ID, $type);

		ob_start();
		require plugin_dir_path( dirname( __FILE__ ) ) . 'public/partials/emoji-reaction-public-display.php';
		return ob_get_clean();
	}

	/**
 	 * Handles data send via ajax.
 	 *
 	 * It updates, save or delete user IDs in the post or comment meta.
 	 *
 	 * @since 0.0.1
 	 *
	 * @return 	string 	echo status for the ajax call.
	 * 
	 * @todo
	 * - return new count
	 * - return new user list
	 * - json?
	 * 
 	 */
	public function emoji_reaction_ajax_save_action() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_emoji_reaction_action' ) ) {
			header('HTTP/1.1 500 Internal Server Error');
        	die();
		}

		$object_id = $_POST['object_id'];
		$object_type = $_POST['object_type'];
		$emoji = $_POST['emoji'];
		$user_id = get_current_user_id();

		if ($_POST['unlike'] === 'true') {
			$this->delete_like($object_id, $object_type, $emoji, $user_id);
			wp_send_json_success(['state' => 'unliked']);
		} else {
			$this->save_like($object_id, $object_type, $emoji, $user_id);
			wp_send_json_success(['state' => 'liked']);
		}

		
		die();
	}

	/**
	 * Get user IDs associated to an emoji out of post or comment meta.
	 *
	 * @since 0.0.2
	 *
	 * @param 	int 		$object_id 		Post or comment id.
	 * @param 	string 		$object_type 	Type of object. Accepts 'post' or 'comment'.
	 * 
	 * @return 	array|bool 	Array of user IDs associated to emojis.
	 */
	private function get_likes($object_id, $object_type) {
		$likes = false;

		if ($object_type == 'comment') {
			$likes = get_comment_meta( $object_id, $this->meta_key, true );
		} else {
			$likes = get_post_meta( $object_id, $this->meta_key, true );
		}

		return $likes;
	}

	/**
	 * Saves user ID associated to emoji in post or comment meta.
	 *
	 * @since 0.0.2
	 *
	 * @param 	int 		$object_id 		Post or comment id.
	 * @param 	string 		$object_type 	Type of object. Accepts 'post' or 'comment'.
	 * @param	string		$emoji			Emoji the user clicked on.
	 * @param	int			$user_id		User ID to save.
	 * 
	 * @return 	int|bool 	Meta ID if the key didn't exist, true on successful update, false on failure or if user ID already existed.
	 */
	private function save_like($object_id, $object_type, $emoji, $user_id) {
		$likes = !empty($this->get_likes($object_id, $object_type)) ? $this->get_likes($object_id, $object_type) : [];

		if ( in_array($user_id, $likes[$emoji]) )
			return false;

		$likes[$emoji][] = $user_id;

		if ($object_type == 'comment') {
			$update = update_comment_meta( $object_id, $this->meta_key, $likes );
		} else {
			$update = update_post_meta( $object_id, $this->meta_key, $likes );
		}

		return $update;
	}

	/**
	 * Deletes user ID associated to emoji in post or comment meta.
	 *
	 * @since 0.0.3
	 *
	 * @param 	int 		$object_id 		Post or comment id.
	 * @param 	string 		$object_type 	Type of object. Accepts 'post' or 'comment'.
	 * @param	string		$emoji			Emoji the user clicked on.
	 * @param	int			$user_id		User ID to delete.
	 * 
	 * @return 	bool 		True on success, false on failure.
	 */
	private function delete_like($object_id, $object_type, $emoji, $user_id) {
		$likes = !empty($this->get_likes($object_id, $object_type)) ? $this->get_likes($object_id, $object_type) : [];

		if (($key = array_search($user_id, $likes[$emoji])) !== false) {
			unset($likes[$emoji][$key]);
		} else {
			return false;
		}

		// clean up empty arrays
		if (empty($likes[$emoji])) {
			unset($likes[$emoji]);
		}

		if (empty($likes)) {
			if ($object_type == 'comment') {
				$update = delete_comment_meta( $object_id, $this->meta_key );
			} else {
				$update = delete_post_meta( $object_id, $this->meta_key );
			}
		} else {
			if ($object_type == 'comment') {
				$update = update_comment_meta( $object_id, $this->meta_key, $likes );
			} else {
				$update = update_post_meta( $object_id, $this->meta_key, $likes );
			}
		}

		return $update;
	}

	/**
	 * Get number of likes of an emoji for a post / comment.
	 *
	 * @since 0.0.4
	 *
	 * @param 	int 		$object_id 		Post or comment id.
	 * @param 	string 		$object_type 	Type of object. Accepts 'post' or 'comment'.
	 * @param	string		$emoji			Emoji the user clicked on.
	 * 
	 * @return 	int 		Number of likes of an emoji.
	 */
	public function get_emoji_count($object_id, $object_type, $emoji) {
		$likes = !empty($this->get_likes($object_id, $object_type)) ? $this->get_likes($object_id, $object_type) : [];

		return sizeof($likes[$emoji]);
	}
}
