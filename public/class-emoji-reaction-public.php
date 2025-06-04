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
		wp_enqueue_style( 'fomantic-ui-transition', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-transition/transition.min.css', '2.9.4' );
		wp_enqueue_style( 'fomantic-ui-dropdown', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-dropdown/dropdown.min.css', '2.9.4' );
		wp_enqueue_style( 'fomantic-ui-popup', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-popup/popup.min.css', '2.9.4' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/emoji-reaction-public.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name . '-chart', plugin_dir_url( __FILE__ ) . 'css/emoji-reaction-chart.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( 'fomantic-ui-transition', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-transition/transition.min.js', array( 'jquery' ), '2.9.4', false );
		wp_enqueue_script( 'fomantic-ui-dropdown', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-dropdown/dropdown.min.js', array( 'jquery' ), '2.9.4', false );
		wp_enqueue_script( 'fomantic-ui-popup', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-popup/popup.min.js', array( 'jquery' ), '2.9.4', false );

		wp_enqueue_script( $this->plugin_name . '-public-js', plugin_dir_url( __FILE__ ) . 'js/emoji-reaction-public.js', array( 'jquery', 'fomantic-ui-transition', 'fomantic-ui-dropdown' ), $this->version, false );

		// Enqueue Chart.js from CDN
		wp_enqueue_script( 'chart-js', plugin_dir_url( __FILE__ ) . 'lib/chart.umd.min.js', array(), '4.4.1', true );

		// Enqueue chart functionality
		wp_enqueue_script( $this->plugin_name . '-chart-js', plugin_dir_url( __FILE__ ) . 'js/emoji-reaction-chart.js', array( 'chart-js' ), $this->version, true );

		// Enqueue chart messages handling
		wp_enqueue_script( $this->plugin_name . '-chart-messages', plugin_dir_url( __FILE__ ) . 'js/emoji-reaction-chart-messages.js', array(), $this->version, true );

		wp_localize_script(
			$this->plugin_name . '-public-js',
			'emoji_reaction',
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'thumbs_down_alert' => __( "We love constructive feedback! How about a comment on what can be improved instead? Pro tip: start with something positive 😉 \nStill want to continue?", 'emoji-reaction' ),
			)
		);
		wp_localize_script( $this->plugin_name . '-chart-js', 'emoji_reaction_chart', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
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
		$defaults            = array(
			'type'            => 'post',
			'ID'              => get_the_ID(),
			'align'           => 'left',
			'usernames'       => 10,
			'emojis'          => apply_filters( 'emoji_reaction_emojis', Emoji_Reaction::get_default_emojis() ),
			'max_usernames'   => 10,
			'current_user_id' => get_current_user_id(),
			'nonce'           => wp_create_nonce( '_emoji_reaction_action' ),
		);
		$args                = wp_parse_args( $args, $defaults );
		$args['likes']       = $this->get_reactions( $args['ID'], $args['type'] );
		$args['total_count'] = $this->get_reaction_count( $args['likes'] );
		$this->render_display( (object) $args );
	}

	/**
	 * Renders the emoji reaction display using prepared data.
	 *
	 * @since 0.0.1
	 *
	 * @param object $obj Display data containing all necessary variables.
	 */
	private function render_display( $obj ) {
		// Build emoji reaction buttons
		$emoji_buttons = '';
		foreach ( $obj->emojis as $emoji ) {
			$user_ids        = array();
			$user_ids_max    = array();
			$count           = 0;
			$classname_voted = 'not-voted';

			if ( ! empty( $obj->likes ) && array_key_exists( $emoji[0], $obj->likes ) ) {
				$user_ids        = $this->uid_to_first_position( $obj->likes[ $emoji[0] ], $obj->current_user_id );
				$count           = count( $user_ids );
				$classname_voted = in_array( $obj->current_user_id, $user_ids ) ? ' voted' : ' not-voted';
				$user_ids_max    = array_slice( $user_ids, 0, $obj->max_usernames );
			}

			// Build user list for popup
			$user_list = '';
			foreach ( $user_ids_max as $user_id ) {
				$user_list .= '<li data-user-id="' . esc_attr( $user_id ) . '">' . esc_html( $this->get_user_name( $user_id ) ) . '</li>';
			}

			$more_users = '';
			if ( $count > $obj->max_usernames ) {
				/* translators: %s: number of additional users who reacted */
				$more_users = '<p>' . esc_html( sprintf( __( 'And %s more ...', 'emoji-reaction' ), ( $count - $obj->max_usernames ) ) ) . '</p>';
			}

			$emoji_buttons .= '
				<button class="emoji-reaction-button emoji-reaction-button-popup show-count ' . esc_attr( $classname_voted ) . '" data-emoji="' . esc_attr( $emoji[0] ) . '" data-count="' . esc_attr( $count ) . '" name="' . esc_attr( $emoji[1] ) . '"></button>
				<div class="ui popup emoji-reaction-popup-container">
					<ul class="emoji-reaction-usernames">' . $user_list . '</ul>' . $more_users . '
				</div>
			';
		}

		// Build dropdown menu items
		$dropdown_items = '';
		foreach ( $obj->emojis as $emoji ) {
			$classname_voted = 'not-voted';
			if ( ! empty( $obj->likes ) && array_key_exists( $emoji[0], $obj->likes ) ) {
				$classname_voted = in_array( $obj->current_user_id, $obj->likes[ $emoji[0] ] ) ? ' voted' : ' not-voted';
			}
			$dropdown_items .= '
				<button class="item emoji-reaction-button ' . esc_attr( $classname_voted ) . '" data-emoji="' . esc_attr( $emoji[0] ) . '" name="' . esc_attr( $emoji[1] ) . '"></button>';
		}

		$render = '
			<div class="emoji-reaction-wrapper ' . esc_attr( $obj->align ) . '" data-object-id="' . esc_attr( $obj->ID ) . '" data-object-type="' . esc_attr( $obj->type ) . '" data-nonce="' . esc_attr( $obj->nonce ) . '" data-totalcount="' . esc_attr( $obj->total_count ) . '">
				<div class="emoji-reactions-container">
				' . $emoji_buttons . '
				</div>
				<div class="emoji-reaction-button-addnew-container ">
					<div class="emoji-reaction-button-addnew ui icon top pointing dropdown center">
						<i class="icon-thumpup-plus"></i>
						<div class="menu">
							<div class="item-container">' . $dropdown_items . '</div>
						</div>
					</div>
				</div>
			</div>
		';

		echo $render; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in to react.' ), 401 );
			return;
		}

		// Check if required POST data exists
		if ( ! isset( $_POST['nonce'], $_POST['object_id'], $_POST['object_type'], $_POST['emoji'], $_POST['unlike'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required data.' ), 400 );
			return;
		}

		// Verify nonce for CSRF protection
		if ( ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), '_emoji_reaction_action' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ), 401 );
			return;
		}

		// Sanitize and validate input data
		$object_id   = intval( $_POST['object_id'] );
		$object_type = sanitize_text_field( wp_unslash( $_POST['object_type'] ) );
		$emoji       = sanitize_text_field( wp_unslash( $_POST['emoji'] ) );
		$user_id     = get_current_user_id();
		$unlike      = sanitize_text_field( wp_unslash( $_POST['unlike'] ) );

		// Validate object type
		if ( ! in_array( $object_type, array( 'post', 'comment' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid object type.' ), 400 );
			return;
		}

		// Validate object ID
		if ( $object_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid object ID.' ), 400 );
			return;
		}

		// Validate emoji (check if it's in allowed list)
		$allowed_emojis = array_column( apply_filters( 'emoji_reaction_emojis', Emoji_Reaction::get_default_emojis() ), 0 );
		if ( ! in_array( $emoji, $allowed_emojis, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid emoji.' ), 400 );
			return;
		}

		// Check user permissions for the object
		if ( ! $this->user_can_react_to_object( $object_id, $object_type ) ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to react to this content.' ), 403 );
			return;
		}

		$state = $unlike === 'true' ? 'unliked' : 'liked';

		if ( 'unliked' === $state ) {
			$success = $this->delete_reaction( $object_id, $object_type, $emoji, $user_id );

		} else {
			$success = $this->save_reaction( $object_id, $object_type, $emoji, $user_id );
		}

		if ( $success ) {
			wp_send_json_success(
				array(
					'state'       => $state,
					'user_id'     => $user_id,
					'user_name'   => $this->get_user_name( $user_id ),
					'post_id'     => $object_id,
					'object_type' => $object_type,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Your like could not be saved.' ) );
		}
	}

	/**
	 * Check if user can react to the specified object.
	 *
	 * @since 0.3.5
	 *
	 * @param   int    $object_id      Post or comment id.
	 * @param   string $object_type    Type of object. Accepts 'post' or 'comment'.
	 *
	 * @return  bool   True if user can react, false otherwise.
	 */
	private function user_can_react_to_object( $object_id, $object_type ) {
		if ( 'comment' === $object_type ) {
			$comment = get_comment( $object_id );
			if ( ! $comment ) {
				return false; // Comment doesn't exist
			}

			// Check if comment is approved
			if ( '1' !== $comment->comment_approved ) {
				return false;
			}

			// Get the post this comment belongs to
			$post_id = $comment->comment_post_ID;
			$post    = get_post( $post_id );
			if ( ! $post ) {
				return false;
			}

			// Check if user can read the post
			return is_post_publicly_viewable( $post );
		} else {
			$post = get_post( $object_id );
			if ( ! $post ) {
				return false; // Post doesn't exist
			}

			// Check if post is published and publicly viewable
			return is_post_publicly_viewable( $post );
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
	private function get_reactions( $object_id, $object_type ) {
		$likes = array();

		if ( $object_type === 'comment' ) {
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
	private function save_reaction( int $object_id, $object_type, $emoji, $user_id ) {
		$likes = $this->get_reactions( $object_id, $object_type );
		$time  = intval( time() );

		if ( $emoji === '' ) {
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
	private function delete_reaction( $object_id, $object_type, $emoji, $user_id ) {
		$likes = $this->get_reactions( $object_id, $object_type );

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
			if ( $object_type === 'comment' ) {
				$update = delete_comment_meta( $object_id, $this->meta_key );
			} else {
				$update = delete_post_meta( $object_id, $this->meta_key );
			}
		} elseif ( $object_type === 'comment' ) {
				$update = update_comment_meta( $object_id, $this->meta_key, $likes );
		} else {
			$update = update_post_meta( $object_id, $this->meta_key, $likes );
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
	public function get_reaction_count( $likes ) {
		$count = 0;

		if ( ! empty( $likes ) ) {
			foreach ( $likes as $emoji => $like ) {
				if ( $emoji !== '' ) {
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
			$user_name = sanitize_text_field( $user_data->display_name );
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
	 * @return  int[]      User ids.
	 */
	public function uid_to_first_position( $user_ids, $user_id ): array {
		// Ensure user_id is an integer
		$user_id = intval( $user_id );

		// Ensure all user_ids are integers
		$user_ids = array_map( 'intval', $user_ids );

		if ( in_array( $user_id, $user_ids ) ) {
			array_unshift( $user_ids, $user_id );
			$user_ids = array_unique( $user_ids );
		}
		return $user_ids;
	}
}
