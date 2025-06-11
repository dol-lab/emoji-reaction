<?php

/**
 * The public-facing functionality of the plugin.
 *
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
 */
class Emoji_Reaction_Public {

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
	 * The meta key to save the likes in post meta.
	 *
	 * @access   private
	 * @var      string    $meta_key    The meta key.
	 */
	private string $meta_key;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param    string $plugin_name The name of the plugin.
	 * @param    string $version     The version of this plugin.
	 */
	public function __construct( string $plugin_name, string $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->meta_key    = '_' . $plugin_name . '_likes';
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 */
	public function enqueue_styles(): void {
		wp_enqueue_style( 'fomantic-ui-transition', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-transition/transition.min.css', '2.9.4' );
		wp_enqueue_style( 'fomantic-ui-popup', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-popup/popup.min.css', '2.9.4' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/emoji-reaction-public.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name . '-chart', plugin_dir_url( __FILE__ ) . 'css/emoji-reaction-chart.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 */
	public function enqueue_scripts(): void {

		wp_enqueue_script( 'fomantic-ui-transition', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-transition/transition.min.js', array( 'jquery' ), '2.9.4', false );
		wp_enqueue_script( 'fomantic-ui-popup', plugin_dir_url( __FILE__ ) . 'lib/fomantic-ui-popup/popup.min.js', array( 'jquery' ), '2.9.4', false );

		// Use filemtime for versioning in development & local environments
		$js_file_path       = plugin_dir_path( __FILE__ ) . 'js/emoji-reaction-public.js';
		$is_dev_environment = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ||
						'development' === wp_get_environment_type() ||
						'local' === wp_get_environment_type();
		$js_version         = $is_dev_environment ? filemtime( $js_file_path ) : $this->version;

		wp_enqueue_script( $this->plugin_name . '-public-js', plugin_dir_url( __FILE__ ) . 'js/emoji-reaction-public.js', array( 'jquery', 'fomantic-ui-transition', 'fomantic-ui-popup' ), $js_version, false );

		// Enqueue Chart.js from CDN
		wp_enqueue_script( 'chart-js', plugin_dir_url( __FILE__ ) . 'lib/chart.umd.min.js', array(), '4.4.1', true );

		// Enqueue chart functionality
		wp_enqueue_script( $this->plugin_name . '-chart-js', plugin_dir_url( __FILE__ ) . 'js/emoji-reaction-chart.js', array( 'chart-js' ), $js_version, true );

		// Enqueue chart messages handling
		wp_enqueue_script( $this->plugin_name . '-chart-messages', plugin_dir_url( __FILE__ ) . 'js/emoji-reaction-chart-messages.js', array(), $js_version, true );

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
	 * Get default arguments for emoji reaction operations.
	 *
	 * @return array Default arguments array.
	 */
	private function get_default_args(): array {
		return array(
			'type'            => 'post',
			'ID'              => get_the_ID(),
			'align'           => 'left',
			'usernames'       => 10,
			'emojis'          => apply_filters( 'emoji_reaction_emojis', Emoji_Reaction::get_default_emojis() ),
			'max_usernames'   => 10,
			'current_user_id' => get_current_user_id(),
			'nonce'           => wp_create_nonce( '_emoji_reaction_action' ),
		);
	}

	/**
	 * Displays emoji buttons.
	 *
	 * The callback function added as a filter, which can be applied in the theme to display the emoji buttons.
	 *
	 * @param array $args {
	 *   Required. An array of elements, that identify the object to get liked or null.
	 *
	 *     @type int 'ID' The post or comment ID. Default is the value of 'get_the_ID' function.
	 *     @type string 'type' The type of object. Accepts 'post' or 'comment'. Default 'post'.
	 *     @type string 'align' Alignment of emoji buttons. Accepts 'left' or 'right'. Default 'left'.
	 *     @type array 'emojis' List of emojis. Default is return of Emoji_Reaction::get_default_emojis().
	 * }
	 */
	public function display_buttons( array $args ): void {
		$defaults = $this->get_default_args();
		$args     = wp_parse_args( $args, $defaults );

		// Get the complete state data
		$state_data = $this->get_state_data( $args['ID'], $args['type'], $args );

		// Render simplified container with state data
		$this->render_simple_display( $state_data );
	}

	/**
	 * Get complete state data for an object (post/comment).
	 *
	 *
	 * @param   int    $object_id      Post or comment id.
	 * @param   string $object_type    Type of object. Accepts 'post' or 'comment'.
	 * @param   array  $args           Additional arguments.
	 *
	 * @return  array  Complete state data.
	 */
	private function get_state_data( $object_id, $object_type, $args ) {
		$likes           = $this->get_reactions( $object_id, $object_type );
		$emojis          = $args['emojis'];
		$current_user_id = $args['current_user_id'];
		$max_usernames   = $args['max_usernames'];

		// Process each emoji
		$emoji_data = array();
		foreach ( $emojis as $emoji ) {
			$count      = 0;
			$user_ids   = array();
			$user_voted = false;

			if ( ! empty( $likes ) && array_key_exists( $emoji[0], $likes ) ) {
				$user_ids   = array_values( $likes[ $emoji[0] ] );
				$count      = count( $user_ids );
				$user_voted = in_array( $current_user_id, $user_ids );
			}

			// Get user names (limited)
			$user_names       = array();
			$user_ids_limited = array_slice( $user_ids, 0, $max_usernames );
			foreach ( $user_ids_limited as $user_id ) {
				$user_names[] = array(
					'id'   => $user_id,
					'name' => $this->get_user_name( $user_id ),
				);
			}

			$emoji_data[] = array(
				'emoji'       => $emoji[0],
				'name'        => $emoji[1],
				'count'       => $count,
				'user_voted'  => $user_voted,
				'user_names'  => $user_names,
				'total_users' => count( $user_ids ),
			);
		}

		// Sort emojis: user voted first, then by count (descending), then by original order
		usort(
			$emoji_data,
			function ( $a, $b ) {
				if ( $a['user_voted'] && ! $b['user_voted'] ) {
					return -1;
				} elseif ( ! $a['user_voted'] && $b['user_voted'] ) {
					return 1;
				} else {
					return $b['count'] - $a['count'];
				}
			}
		);

		return array(
			'object_id'       => $object_id,
			'object_type'     => $object_type,
			'nonce'           => $args['nonce'],
			'align'           => $args['align'],
			'max_usernames'   => $max_usernames,
			'total_count'     => $this->get_reaction_count( $likes ),
			'emojis'          => $emoji_data,
			'current_user_id' => $current_user_id,
		);
	}

	/**
	 * Render simple display container with inline state data.
	 *
	 *
	 * @param array $state_data Complete state data.
	 */
	private function render_simple_display( $state_data ) {
		$container_id = 'emoji-reaction-' . $state_data['object_type'] . '-' . $state_data['object_id'];

		// Add semantic wrapper with proper ARIA attributes
		echo '<section class="emoji-reaction-wrapper ' . esc_attr( $state_data['align'] ) . '" id="' . esc_attr( $container_id ) . '" role="group" aria-label="' . esc_attr( __( 'Emoji reactions', 'emoji-reaction' ) ) . '" aria-live="polite"></section>';

		// Output inline script with state data
		echo '<script type="text/javascript">';
		echo 'window.emojiReactionData = window.emojiReactionData || {};';
		echo 'window.emojiReactionData[' . wp_json_encode( $container_id ) . '] = ' . wp_json_encode( $state_data ) . ';';
		echo 'if (window.EmojiReaction && window.EmojiReaction.initContainer) {';
		echo '  window.EmojiReaction.initContainer(' . wp_json_encode( $container_id ) . ');';
		echo '}';
		echo '</script>';
	}

	/**
	 * Handles data send via ajax.
	 *
	 * It updates, save or delete user IDs in the post or comment meta.
	 *
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
			$success          = $this->delete_reaction( $object_id, $object_type, $emoji, $user_id );
			$error_message    = __( 'Your reaction could not be removed.', 'emoji-reaction' );
			$removed_reaction = null;
		} else {
			$success          = $this->save_reaction( $object_id, $object_type, $emoji, $user_id );
			$error_message    = __( 'Your reaction could not be saved.', 'emoji-reaction' );
			$removed_reaction = null;

			// Check if save_reaction returned info about a removed reaction
			if ( is_array( $success ) && isset( $success['removed_reaction'] ) ) {
				$removed_reaction = $success['removed_reaction'];
				$success          = true;
			}
		}

		if ( $success ) {
			// Get default arguments to build state data
			$default_args                    = $this->get_default_args();
			$default_args['current_user_id'] = $user_id;

			// Get complete updated state
			$state_data = $this->get_state_data( $object_id, $object_type, $default_args );

			// Add legacy response data for backward compatibility
			$response_data = array(
				'success'     => true,
				'state_data'  => $state_data,
				'action_info' => array(
					'state'       => $state,
					'user_id'     => $user_id,
					'user_name'   => $this->get_user_name( $user_id ),
					'object_id'   => $object_id,
					'object_type' => $object_type,
					'emoji'       => $emoji,
				),
			);

			// Add information about removed reaction if one was automatically removed
			if ( $removed_reaction ) {
				$response_data['action_info']['removed_reaction'] = $removed_reaction;
				/* translators: %s: emoji that was removed */
				$response_data['action_info']['limit_message'] = sprintf(
					__( 'Your oldest reaction (%s) was automatically removed to stay within the limit.', 'emoji-reaction' ),
					$removed_reaction['emoji']
				);
			}

			wp_send_json_success( $response_data );
		} else {
			wp_send_json_error( array( 'message' => $error_message ) );
		}
	}

	/**
	 * Check if user can react to the specified object.
	 *
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
	 *
	 * @param   int    $object_id      Post or comment id.
	 * @param   string $object_type    Type of object. Accepts 'post' or 'comment'.
	 * @param   string $emoji          Emoji the user clicked on.
	 * @param   int    $user_id        User ID to save.
	 *
	 * @return  bool|array   True on success, false on failure, array with removed reaction info if oldest was removed
	 */
	private function save_reaction( int $object_id, $object_type, $emoji, $user_id ) {
		$likes            = $this->get_reactions( $object_id, $object_type );
		$time             = intval( time() );
		$removed_reaction = null;

		if ( $emoji === '' ) {
			return false;
		}

		// Check if user already reacted to this emoji
		if ( ! empty( $likes ) && array_key_exists( $emoji, $likes ) && in_array( $user_id, $likes[ $emoji ] ) ) {
			return false;
		}

		// Check reaction limit per user and remove oldest if needed
		$max_reactions_per_user = apply_filters( 'emoji_reaction_max_reactions_per_user', 1 );
		if ( $max_reactions_per_user > 0 ) {
			$user_reaction_count = $this->get_user_reaction_count( $likes, $user_id );
			if ( $user_reaction_count >= $max_reactions_per_user ) {
				$removed_reaction = $this->remove_oldest_user_reaction( $likes, $user_id );
			}
		}

		if ( ! empty( $likes ) ) {
			$likes[ $emoji ][ $time ] = $user_id;
		} else {
			$likes = array( $emoji => array( $time => $user_id ) );
		}

		$update = update_metadata( $object_type, $object_id, $this->meta_key, $likes );

		if ( is_int( $update ) ? true : $update ) {
			return $removed_reaction ? array( 'removed_reaction' => $removed_reaction ) : true;
		}

		return false;
	}

	/**
	 * Deletes user ID associated to emoji in post or comment meta.
	 *
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
	 * Get the number of reactions a user has made to a specific post/comment.
	 *
	 *
	 * @param   array $likes      All likes of a post / comment.
	 * @param   int   $user_id    User ID to count reactions for.
	 *
	 * @return  int         Number of reactions by this user.
	 */
	private function get_user_reaction_count( $likes, $user_id ) {
		$count = 0;

		if ( ! empty( $likes ) ) {
			foreach ( $likes as $emoji => $emoji_likes ) {
				if ( $emoji !== '' && in_array( $user_id, $emoji_likes ) ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Remove the oldest reaction by a user and return information about it.
	 *
	 *
	 * @param   array $likes      All likes of a post / comment (passed by reference).
	 * @param   int   $user_id    User ID whose oldest reaction to remove.
	 *
	 * @return  array|null        Information about the removed reaction or null if none found.
	 */
	private function remove_oldest_user_reaction( &$likes, $user_id ) {
		$oldest_timestamp     = null;
		$oldest_emoji         = null;
		$oldest_timestamp_key = null;

		// Find the oldest reaction by this user
		if ( ! empty( $likes ) ) {
			foreach ( $likes as $emoji => $emoji_likes ) {
				if ( $emoji !== '' ) {
					foreach ( $emoji_likes as $timestamp => $uid ) {
						if ( $uid === $user_id ) {
							if ( $oldest_timestamp === null || $timestamp < $oldest_timestamp ) {
									$oldest_timestamp     = $timestamp;
									$oldest_emoji         = $emoji;
									$oldest_timestamp_key = $timestamp;
							}
						}
					}
				}
			}
		}

		// Remove the oldest reaction if found
		if ( $oldest_emoji && $oldest_timestamp_key ) {
			unset( $likes[ $oldest_emoji ][ $oldest_timestamp_key ] );

			// Clean up empty arrays
			if ( empty( $likes[ $oldest_emoji ] ) ) {
				unset( $likes[ $oldest_emoji ] );
			}

			return array(
				'emoji'     => $oldest_emoji,
				'timestamp' => $oldest_timestamp,
			);
		}

		return null;
	}

	/**
	 * Move an user id to first position of array.
	 *
	 *
	 * @param   array $user_ids    User ids array.
	 * @param   int   $user_id     User id.
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
