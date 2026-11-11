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

	/** @var int People per request of the list of who reacted. */
	private const REACTORS_PER_PAGE = 10;

	/** @var int Pixel size of the avatars in that list: twice their CSS size, for high-density screens. */
	private const AVATAR_SIZE = 48;

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
		if ( ! is_user_logged_in() ) {
			return; // return if user is not logged in
		}
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/emoji-reaction-public.css', array(), $this->asset_version( 'css/emoji-reaction-public.css' ), 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 */
	public function enqueue_scripts(): void {
		if ( ! is_user_logged_in() ) {
			return; // return if user is not logged in
		}
		$handle = $this->plugin_name . '-public-js';

		wp_enqueue_script( $handle, plugin_dir_url( __FILE__ ) . 'js/emoji-reaction-public.js', array(), $this->asset_version( 'js/emoji-reaction-public.js' ), false );

		wp_localize_script(
			$handle,
			'emoji_reaction',
			array(
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'thumbs_down_alert'      => __( "We love constructive feedback! How about a comment on what can be improved instead? Pro tip: start with something positive 😉 \nStill want to continue?", 'emoji-reaction' ),
				'confirm_remove_no_perm' => __( "Are you sure you want to remove your reaction? You won't be able to add it back later.", 'emoji-reaction' ),
				'members_only'           => __( 'Only members see who reacted.', 'emoji-reaction' ),
				'names_failed'           => __( 'Could not load who reacted.', 'emoji-reaction' ),
				'who_reacted'            => __( 'Who reacted', 'emoji-reaction' ),
				'all'                    => __( 'All', 'emoji-reaction' ),
				'all_reactions'          => __( 'All reactions', 'emoji-reaction' ),
				'you'                    => __( 'You', 'emoji-reaction' ),
				'anonymous'              => __( 'Anonymous', 'emoji-reaction' ),
				'click_to_remove'        => __( 'Click to remove', 'emoji-reaction' ),
				'tap_to_remove'          => __( 'Tap to remove', 'emoji-reaction' ),
				/* translators: %s: emoji name. */
				'remove_reaction'        => __( 'Remove your reaction %s', 'emoji-reaction' ),
				'close'                  => __( 'Close', 'emoji-reaction' ),
				'no_permission'          => __( 'Sorry, you do not have permission to react to this content.', 'emoji-reaction' ),
				'request_failed'         => __( 'Unable to update reaction. Please try again.', 'emoji-reaction' ),
				'add_reaction'           => __( 'Add new reaction', 'emoji-reaction' ),
				'choose_reaction'        => __( 'Choose reaction', 'emoji-reaction' ),
				/* translators: 1: name of the emoji, 2: how many people reacted with it. */
				'button_label'           => __( '%1$s (%2$d)', 'emoji-reaction' ),
				'you_reacted'            => __( 'You reacted with this', 'emoji-reaction' ),
				/* translators: %s: emoji name. */
				'react_with'             => __( 'React with %s', 'emoji-reaction' ),
				'currently_selected'     => __( 'currently selected', 'emoji-reaction' ),
				'page'                   => $this->get_page_state(),
				/**
				 * Classes a host adds to parts of the "who reacted" sheet (the dialog it is on a touch screen),
				 * to make it one of its own modals.
				 *
				 * @param array $classes Space-separated classes for 'sheet' (the `<dialog>`), 'sheet_header'
				 *                       (the tab bar) and 'sheet_close' (the close button).
				 */
				'classes'                => apply_filters(
					'emoji_reaction_part_classes',
					array(
						'sheet'        => '',
						'sheet_header' => '',
						'sheet_close'  => '',
					)
				),
			)
		);
	}

	/**
	 * Version for an asset: its modification time, so a changed file gets a new URL without a version bump.
	 *
	 * @param string $relative_path Path of the asset, relative to this directory.
	 *
	 * @return string Version to append to the asset's URL.
	 */
	private function asset_version( string $relative_path ): string {
		$mtime = @filemtime( plugin_dir_path( __FILE__ ) . $relative_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file simply falls back to the version.

		return $mtime ? (string) $mtime : $this->version;
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
			'emojis'          => apply_filters( 'emoji_reaction_emojis', Emoji_Reaction::get_default_emojis() ),
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
		$this->render_simple_display( $this->compact_state( $state_data, $args, $defaults ) );
	}

	/**
	 * The state of one set of buttons, without what the page already has.
	 *
	 * A page shows many sets, and the emoji list, nonce and user are the same in each: they go out
	 * once in `emoji_reaction.page` and the script rebuilds the full state (`expandState()`). Only
	 * reactions with a count are listed; settings are repeated only where `$args` override them.
	 *
	 * @param array $state_data Complete state data, see get_state_data().
	 * @param array $args       Arguments the buttons were rendered with.
	 * @param array $defaults   Default arguments, as in `emoji_reaction.page`.
	 *
	 * @return array Compact state data.
	 */
	private function compact_state( array $state_data, array $args, array $defaults ): array {
		$compact = array(
			'object_id'   => $state_data['object_id'],
			'object_type' => $state_data['object_type'],
			'align'       => $state_data['align'],
			'can_react'   => $state_data['can_react'],
			'counts'      => (object) array(),
			'voted'       => array(),
		);
		foreach ( $state_data['emojis'] as $emoji ) {
			if ( $emoji['count'] > 0 ) {
				$compact['counts']->{$emoji['emoji']} = $emoji['count'];
			}
			if ( $emoji['user_voted'] ) {
				$compact['voted'][] = $emoji['emoji'];
			}
		}
		foreach ( array( 'emojis', 'nonce', 'current_user_id' ) as $key ) {
			if ( $args[ $key ] !== $defaults[ $key ] ) {
				$compact[ $key ] = $args[ $key ];
			}
		}
		return $compact;
	}

	/**
	 * Settings every set of buttons on the page shares, for `emoji_reaction.page`.
	 *
	 * @return array The emoji list, nonce and user.
	 */
	private function get_page_state(): array {
		$defaults = $this->get_default_args();
		return array(
			'emojis'          => $defaults['emojis'],
			'nonce'           => $defaults['nonce'],
			'current_user_id' => $defaults['current_user_id'],
		);
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
		$emojis          = is_array( $args['emojis'] ) ? $args['emojis'] : array();
		$current_user_id = $args['current_user_id'];

		$emoji_data = array();
		foreach ( $emojis as $emoji ) {
			$user_ids     = isset( $likes[ $emoji[0] ] ) ? array_values( $likes[ $emoji[0] ] ) : array();
			$emoji_data[] = $this->build_emoji_entry( $emoji[0], $emoji[1], $user_ids, $current_user_id );
		}

		// Show legacy reactions that exist in the DB but are no longer in the active emoji list
		$processed_emojis = array_column( $emojis, 0 );
		foreach ( $likes as $emoji_char => $user_ids_map ) {
			if ( in_array( $emoji_char, $processed_emojis, true ) ) {
				continue;
			}

			$entry           = $this->build_emoji_entry( $emoji_char, $emoji_char, array_values( $user_ids_map ), $current_user_id );
			$entry['legacy'] = true;
			$emoji_data[]    = $entry;
		}

		return array(
			'object_id'       => $object_id,
			'object_type'     => $object_type,
			'nonce'           => $args['nonce'],
			'align'           => $args['align'],
			'total_count'     => $this->get_reaction_count( $likes ),
			'emojis'          => $emoji_data,
			'current_user_id' => $current_user_id,
			'can_react'       => $this->user_can_react_to_object( $object_id, $object_type ),
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
		echo '<section class="emoji-reaction-wrapper ' . esc_attr( $state_data['align'] ) . '" id="' . esc_attr( $container_id ) . '" role="group" aria-label="' . esc_attr( __( 'Emoji reactions', 'emoji-reaction' ) ) . '" ></section>';

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
	 * Who reacted, one page per tab: 'all' and each emoji.
	 *
	 * Without `tab` it answers the first page of every tab, so switching tabs needs no request; with
	 * `tab` and `offset` the next page of that one.
	 *
	 * @return void
	 */
	public function emoji_reaction_ajax_get_reactors() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'You must be logged in to view reactions.' ), 401 );
		}
		if ( ! isset( $_POST['nonce'], $_POST['object_id'], $_POST['object_type'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required data.' ), 400 );
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), '_emoji_reaction_action' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ), 401 );
		}

		$object_id   = intval( $_POST['object_id'] );
		$object_type = sanitize_text_field( wp_unslash( $_POST['object_type'] ) );
		$tab         = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : null;
		$offset      = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		if ( ! in_array( $object_type, array( 'post', 'comment' ), true ) || $object_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters.' ), 400 );
		}
		if ( ! $this->user_can_react_to_object( $object_id, $object_type ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$likes = $this->get_reactions( $object_id, $object_type );
		$lists = $this->get_reactor_lists( $likes, get_current_user_id() );
		if ( null !== $tab ) {
			$lists = array_intersect_key( $lists, array( $tab => true ) );
		}

		$pages = array();
		foreach ( $lists as $key => $user_ids ) {
			$pages[ $key ] = array_slice( $user_ids, null === $tab ? 0 : $offset, self::REACTORS_PER_PAGE );
		}
		$users = $this->get_reactor_users( array_unique( array_merge( array(), ...array_values( $pages ) ) ) );

		$emojis_of = array();
		foreach ( $likes as $emoji => $user_ids ) {
			foreach ( $user_ids as $user_id ) {
				$emojis_of[ (int) $user_id ][] = (string) $emoji;
			}
		}

		$tabs = array();
		foreach ( $pages as $key => $user_ids ) {
			$tabs[ $key ] = array(
				'total' => count( $lists[ $key ] ),
				'users' => array_map(
					fn( $user_id ) => $users[ $user_id ] + array( 'emojis' => 'all' === $key ? $emojis_of[ $user_id ] : array( (string) $key ) ),
					$user_ids
				),
			);
		}

		wp_send_json_success( array( 'tabs' => $tabs ) );
	}

	/**
	 * Who reacted, in the order the list shows them: the current user first, then the most recent.
	 *
	 * @param array $likes           Reactions as get_reactions() returns them, newest first.
	 * @param int   $current_user_id The user who asks.
	 *
	 * @return array<string, int[]> User ids per tab: 'all' (each person once) and each emoji.
	 */
	private function get_reactor_lists( array $likes, int $current_user_id ): array {
		$latest = array();
		$lists  = array();
		foreach ( $likes as $emoji => $user_ids ) {
			$lists[ (string) $emoji ] = array_values( $this->uid_to_first_position( array_values( $user_ids ), $current_user_id ) );
			foreach ( $user_ids as $time => $user_id ) {
				$latest[ (int) $user_id ] = max( $latest[ (int) $user_id ] ?? 0, (int) $time );
			}
		}
		arsort( $latest );

		return array( 'all' => array_values( $this->uid_to_first_position( array_keys( $latest ), $current_user_id ) ) ) + $lists;
	}

	/**
	 * Name and avatar of each user, looked up network-wide: somebody who reacted and has since left the site still reacted.
	 *
	 * A user who no longer exists gets no name (the script says "Anonymous") and no avatar.
	 *
	 * @param int[] $user_ids Users to look up.
	 *
	 * @return array<int, array{id: int, name: string, avatar: string}> Users by id.
	 */
	private function get_reactor_users( array $user_ids ): array {
		$users = array();
		foreach ( $user_ids as $user_id ) {
			$users[ $user_id ] = array(
				'id'     => $user_id,
				'name'   => '',
				'avatar' => '',
			);
		}
		if ( ! $user_ids ) {
			return $users;
		}

		/**
		 * Fires before the avatars of these users are looked up, so a host can load them in one go.
		 *
		 * @param int[] $user_ids The users.
		 * @param int   $size     Pixel size of the avatars.
		 */
		do_action( 'emoji_reaction_reactors', $user_ids, self::AVATAR_SIZE );

		$show_avatars = get_option( 'show_avatars' );
		$found        = get_users(
			array(
				'blog_id' => 0,
				'include' => $user_ids,
			)
		);
		foreach ( $found as $user ) {
			$users[ $user->ID ]['name'] = sanitize_text_field( $user->display_name );
			if ( $show_avatars ) {
				$users[ $user->ID ]['avatar'] = (string) get_avatar_url( $user, array( 'size' => self::AVATAR_SIZE ) );
			}
		}

		return $users;
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
		}

		// Check if required POST data exists
		if ( ! isset( $_POST['nonce'], $_POST['object_id'], $_POST['object_type'], $_POST['emoji'], $_POST['unlike'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required data.' ), 400 );
		}

		// Verify nonce for CSRF protection
		if ( ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), '_emoji_reaction_action' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ), 401 );
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
		}

		// Validate object ID
		if ( $object_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid object ID.' ), 400 );
		}

		// Validate emoji (check if it's in allowed list, unless we are unliking)
		$allowed_emojis = array_column( apply_filters( 'emoji_reaction_emojis', Emoji_Reaction::get_default_emojis() ), 0 );
		if ( 'true' !== $unlike && ! in_array( $emoji, $allowed_emojis, true ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'This emoji is no longer available. Use the 👍+ button to see all available reactions.', 'emoji-reaction' ) ), 400 );
		}

		// Check user permissions for the object (if NOT unliking)
		$can_react = $this->user_can_react_to_object( $object_id, $object_type );
		if ( 'true' !== $unlike && ! $can_react ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Sorry, you do not have permission to react to this content.' ) ), 403 );
		}

		$state = 'true' === $unlike ? 'unliked' : 'liked';

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
			$state_data    = $this->get_state_data( $object_id, $object_type, $default_args );
			$response_data = array(
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
					// translators: %s: emoji that was removed
					esc_html__( 'Your oldest reaction (%s) was automatically removed to stay within the limit.', 'emoji-reaction' ),
					$removed_reaction['emoji']
				);
			}
			do_action( 'emoji_reaction_saved', $response_data );

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
		if ( ! is_user_logged_in() ) {
			return false;
		}

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

			$object_id = $post_id;
		} else {
			$post = get_post( $object_id );
			if ( ! $post ) {
				return false; // Post doesn't exist
			}
		}

		if ( post_password_required( $post ) ) {
			return false;
		}

		// Check if user can read the post (handles private posts properly).
		return current_user_can( 'read_post', $object_id );
	}

	/**
	 * Build a single emoji entry for state data.
	 *
	 * @param string $emoji_char      The emoji character.
	 * @param string $emoji_name      Display name for the emoji.
	 * @param int[]  $user_ids        User IDs who reacted with this emoji.
	 * @param int    $current_user_id Current user's ID.
	 *
	 * @return array Emoji entry data.
	 */
	private function build_emoji_entry( $emoji_char, $emoji_name, $user_ids, $current_user_id ) {
		return array(
			'emoji'      => $emoji_char,
			'name'       => $emoji_name,
			'count'      => count( $user_ids ),
			'user_voted' => in_array( (int) $current_user_id, array_map( 'intval', $user_ids ), true ),
		);
	}

	/**
	 * Get user IDs associated to an emoji out of post or comment meta.
	 *
	 *
	 * @param   int    $object_id      Post or comment id.
	 * @param   string $object_type    Type of object. Accepts 'post' or 'comment'.
	 *
	 * @return  array  Array of user IDs associated to emojis.
	 */
	private function get_reactions( $object_id, $object_type ): array {
		if ( 'comment' === $object_type ) {
			$likes = get_comment_meta( $object_id, $this->meta_key, true );
		} else {
			$likes = get_post_meta( $object_id, $this->meta_key, true );
		}

		if ( ! is_array( $likes ) ) {
			return array();
		}

		// Normalize: keep only array entries, sort each by timestamp descending.
		$normalized = array();
		foreach ( $likes as $emoji => $user_ids ) {
			if ( is_array( $user_ids ) && ! empty( $user_ids ) ) {
				krsort( $user_ids );
				$normalized[ $emoji ] = $user_ids;
			}
		}

		return $normalized;
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
	public function save_reaction( int $object_id, $object_type, $emoji, $user_id ) {
		$likes            = $this->get_reactions( $object_id, $object_type );
		$time             = intval( time() );
		$removed_reaction = null;

		// Ensure unique timestamp to prevent overwriting reactions that happen at the same second.
		if ( ! empty( $likes ) && isset( $likes[ $emoji ] ) ) {
			while ( array_key_exists( $time, $likes[ $emoji ] ) ) {
				++$time;
			}
		}

		if ( empty( $emoji ) ) {
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
	public function delete_reaction( $object_id, $object_type, $emoji, $user_id ) {
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
			if ( 'comment' === $object_type ) {
				$update = delete_comment_meta( $object_id, $this->meta_key );
			} else {
				$update = delete_post_meta( $object_id, $this->meta_key );
			}
		} elseif ( 'comment' === $object_type ) {
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

		foreach ( $likes as $emoji => $like ) {
			if ( ! empty( $emoji ) ) {
				$count += count( $like );
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

		foreach ( $likes as $emoji => $emoji_likes ) {
			if ( ! empty( $emoji ) && in_array( $user_id, $emoji_likes ) ) {
				++$count;
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
		$oldest_timestamp = null;
		$oldest_emoji     = null;

		// Find the oldest reaction by this user
		foreach ( $likes as $emoji => $emoji_likes ) {
			if ( ! empty( $emoji ) ) {
				foreach ( $emoji_likes as $timestamp => $uid ) {
					if ( $uid === $user_id && ( null === $oldest_timestamp || $timestamp < $oldest_timestamp ) ) {
						$oldest_timestamp = $timestamp;
						$oldest_emoji     = $emoji;
					}
				}
			}
		}

		// Remove the oldest reaction if found
		if ( null !== $oldest_emoji ) {
			unset( $likes[ $oldest_emoji ][ $oldest_timestamp ] );

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
