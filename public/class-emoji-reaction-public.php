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
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		$this->meta_key = '_' . $plugin_name . '_likes';

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	public function enqueue_styles() {

		wp_enqueue_style( 'fomantic-ui-transition', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-transition/transition.min.css', $this->version );
		wp_enqueue_style( 'fomantic-ui-dropdown', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-dropdown/dropdown.min.css', $this->version );
		wp_enqueue_style( 'fomantic-ui-popup', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-popup/popup.min.css', $this->version );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/emoji-reaction-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( 'fomantic-ui-transition', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-transition/transition.min.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( 'fomantic-ui-dropdown', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-dropdown/dropdown.min.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( 'fomantic-ui-popup', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-popup/popup.min.js', array( 'jquery' ), $this->version, false );

		wp_enqueue_script( $this->plugin_name . '-public-js', plugin_dir_url( __FILE__ ) . 'js/emoji-reaction-public.js', array( 'jquery', 'fomantic-ui-transition', 'fomantic-ui-dropdown' ), $this->version, false );

		wp_localize_script( $this->plugin_name . '-public-js', 'emoji_reaction', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

	}

	/**
	 * Displays emoji buttons.
	 *
	 * The callback function added as a filter, which can be applied in the theme to display the emoji buttons.
	 *
	 * @since 0.0.1
	 *
	 * @param   array $args {
	 *   Required. An array of elements, that identify the object to get liked or null.
	 *
	 *     @type int 'ID' The post or comment ID. Default is the value of 'get_the_ID' function.
	 *     @type string 'type' The type of object. Accepts 'post' or 'comment'. Default 'post'.
	 *     @type string 'align' Alignment of emoji buttons. Accepts 'left' or 'right'. Default 'left'.
	 *     @type array 'emojis' List of emojis. Default is return of Emoji_Reaction::get_default_emojis().
	 * }
	 */
	public function display_buttons( $args ) {
		$defaults = array(
			'type'      => 'post',
			'ID'        => get_the_ID(),
			'align'     => 'left',
			'usernames' => 10,
		);
		$args     = wp_parse_args( $args, $defaults );

		$max_usernames = $args['usernames'];

		$emojis = apply_filters( 'emoji_reaction_emojis', Emoji_Reaction::get_default_emojis() );

		$type  = $args['type'];
		$ID    = $args['ID'];
		$align = $args['align'];

		$likes       = $this->get_likes( $ID, $type );
		$total_count = $this->get_likes_count( $likes );

		require plugin_dir_path( dirname( __FILE__ ) ) . 'public/partials/emoji-reaction-public-display.php';
	}

	/**
	 * Handles data send via ajax.
	 *
	 * It updates, save or delete user IDs in the post or comment meta.
	 *
	 * @since 0.0.1
	 *
	 * @return  string  echo status for the ajax call.
	 */
	public function emoji_reaction_ajax_save_action() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_emoji_reaction_action' ) ) {
			wp_send_json_error( null, 401 );
		}

		$object_id   = intval( $_POST['object_id'] );
		$object_type = $_POST['object_type'];
		$emoji       = $_POST['emoji'];
		$user_id     = get_current_user_id();

		$state = $_POST['unlike'] === 'true' ? 'unliked' : 'liked';

		if ( 'unliked' === $state ) {
			$success = $this->delete_like( $object_id, $object_type, $emoji, $user_id );

		} else {
			$success = $this->save_like( $object_id, $object_type, $emoji, $user_id );
		}

		if ( $success ) {
			wp_send_json_success(
				array(
					'state'     => $state,
					'user_id'   => $user_id,
					'user_name' => $this->get_user_name( $user_id ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Your like could not be saved.' ) );
		}
	}

	/**
	 * Get user IDs associated to an emoji out of post or comment meta.
	 *
	 * @since 0.0.2
	 *
	 * @param   int    $object_id      Post or comment id.
	 * @param   string $object_type    Type of object. Accepts 'post' or 'comment'.
	 *
	 * @return  array|bool  Array of user IDs associated to emojis.
	 */
	private function get_likes( $object_id, $object_type ) {
		$likes = array();

		if ( $object_type == 'comment' ) {
			$likes = get_comment_meta( $object_id, $this->meta_key, true );
		} else {
			$likes = get_post_meta( $object_id, $this->meta_key, true );
		}

		if ( ! empty( $likes ) ) {
			foreach ( $likes as $key => $emoji_likes ) {
				krsort( $emoji_likes );
				$likes[ $key ] = $emoji_likes;
			}
		}

		return $likes;
	}

	/**
	 * Saves user ID associated to emoji in post or comment meta.
	 *
	 * @since 0.0.2
	 *
	 * @param   int    $object_id      Post or comment id.
	 * @param   string $object_type    Type of object. Accepts 'post' or 'comment'.
	 * @param   string $emoji          Emoji the user clicked on.
	 * @param   int    $user_id        User ID to save.
	 *
	 * @return  bool   True on success, false on failure (or if the value passed is the same as in db)
	 */
	private function save_like( int $object_id, $object_type, $emoji, $user_id ) {
		$likes = $this->get_likes( $object_id, $object_type );
		$time  = intval( time() );

		if ( $emoji == '' ) {
			return false;
		}

		if ( ! empty( $likes ) ) {
			if ( array_key_exists( $emoji, $likes ) ) {
				if ( in_array( $user_id, $likes[ $emoji ] ) ) {
					return false;
				}
			}
			$likes[ $emoji ][ $time ] = $user_id;
		} else {
			$likes = array( $emoji => array( $time => $user_id ) );
		}

		$update = update_metadata( $object_type, $object_id, $this->meta_key, $likes );
		// $new_data = get_metadata( $object_type, $object_id, $this->meta_key );

		return is_int( $update ) ? true : $update;
	}

	/**
	 * Deletes user ID associated to emoji in post or comment meta.
	 *
	 * @since 0.0.3
	 *
	 * @param   int    $object_id      Post or comment id.
	 * @param   string $object_type    Type of object. Accepts 'post' or 'comment'.
	 * @param   string $emoji          Emoji the user clicked on.
	 * @param   int    $user_id        User ID to delete.
	 *
	 * @return  bool        True on success, false on failure (or if the value passed is the same as in db)
	 */
	private function delete_like( $object_id, $object_type, $emoji, $user_id ) {
		$likes = $this->get_likes( $object_id, $object_type );

		$key = isset( $likes[ $emoji ] ) && is_array( $likes[ $emoji ] ) ? array_search( $user_id, $likes[ $emoji ] ) : false;
		if ( $key !== false ) {
			unset( $likes[ $emoji ][ $key ] );
		} else {
			return false;
		}

		// clean up empty arrays
		if ( empty( $likes[ $emoji ] ) ) {
			unset( $likes[ $emoji ] );
		}

		if ( empty( $likes ) ) {
			if ( $object_type == 'comment' ) {
				$update = delete_comment_meta( $object_id, $this->meta_key );
			} else {
				$update = delete_post_meta( $object_id, $this->meta_key );
			}
		} else {
			if ( $object_type == 'comment' ) {
				$update = update_comment_meta( $object_id, $this->meta_key, $likes );
			} else {
				$update = update_post_meta( $object_id, $this->meta_key, $likes );
			}
		}

		return is_int( $update ) ? true : $update; // update_post/comment_meta returns an int on successful update.
	}

	/**
	 * Get total number of likes of a post / comment.
	 *
	 * @since 0.1.1
	 *
	 * @param   array $likes      All likes of a post / comment.
	 *
	 * @return  int         Number of likes.
	 */
	public function get_likes_count( $likes ) {
		$count = 0;

		if ( ! empty( $likes ) ) {
			foreach ( $likes as $emoji => $like ) {
				if ( $emoji != '' ) {
					$count += sizeof( $like );
				}
			}
		}

		return $count;
	}

	/**
	 * Get user name by user id
	 *
	 * @since 0.0.6
	 *
	 * @param   int $user_id        User id.
	 *
	 * @return  string      User display name or anonymous.
	 */
	public function get_user_name( $user_id ) {
		$user_data = get_user_by( 'id', $user_id );
		$user_name = __( 'Anonymous', 'emoji-reaction' );
		if ( ! empty( $user_data ) ) {
			$user_name = $user_data->display_name;
		}
		return $user_name;
	}

	/**
	 * Move an user id to first position of array.
	 *
	 * @since 0.2.0
	 *
	 * @param   int $user_id        User id.
	 *
	 * @return  array      User ids.
	 */
	public function userid_to_first_position( $user_ids, $user_id ) {
		if ( in_array( $user_id, $user_ids ) ) {
			array_unshift( $user_ids, $user_id );
			$user_ids = array_unique( $user_ids );
		}
		return $user_ids;
	}
}
