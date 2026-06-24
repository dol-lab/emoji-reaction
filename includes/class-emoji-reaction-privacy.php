<?php
/**
 * Erase a deleted user's reactions across the network (GDPR Art. 17).
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/includes
 */

/**
 * Removes every reference to a just-deleted user from the plugin's own storage.
 *
 * WordPress core reassigns or deletes a user's posts and comments, but the
 * reactions this plugin records in post and comment meta keep dangling user IDs
 * that survive the deletion. Hooked to the universal `deleted_user` action,
 * this scrubs them on every site of the network so the plugin cleans up after
 * itself.
 *
 * The `deleted_user` action is fired in two very different situations on
 * multisite, and only one of them is an actual account deletion:
 *   - wpmu_delete_user() removes the account network-wide (the row is gone
 *     from wp_users before the action fires) — we must purge.
 *   - wp_delete_user() on multisite only removes the user from the *current*
 *     site via remove_user_from_blog(); the account survives — we must NOT
 *     purge their reactions on every other site they still belong to.
 * We tell them apart by checking whether the account still exists.
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/includes
 */
class Emoji_Reaction_Privacy {

	/**
	 * Meta key under which reactions are stored on posts and comments.
	 *
	 * @var string
	 */
	private string $meta_key;

	/**
	 * The blogs a user belonged to, captured before deletion removes them.
	 *
	 * @var array<int, int[]> Map of user ID => list of their blog IDs.
	 */
	private array $user_blogs = array();

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name The unique plugin identifier.
	 */
	public function __construct( string $plugin_name ) {
		$this->meta_key = '_' . $plugin_name . '_likes';
	}

	/**
	 * Remember which blogs a user belongs to before the account is deleted.
	 *
	 * Fired on `wpmu_delete_user`, before core removes the user from their
	 * blogs, so `deleted_user` can scrub exactly those blogs instead of sweeping
	 * the whole network. Mirrors how core itself scopes its deletion work.
	 *
	 * @param int $user_id ID of the user about to be deleted.
	 * @return void
	 */
	public function capture_user_blogs( int $user_id ): void {
		$this->user_blogs[ $user_id ] = array_map(
			static fn( $blog ) => (int) $blog->userblog_id,
			get_blogs_of_user( $user_id )
		);
	}

	/**
	 * Strip a just-deleted user from reactions on the blogs they belonged to.
	 *
	 * @param int $user_id ID of the user that was deleted.
	 * @return void
	 */
	public function purge_deleted_user( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		// On multisite, wp_delete_user() fires `deleted_user` when a user is
		// merely removed from a single site while their account lives on. Core
		// has already cleared the user cache by this point, so a lookup that
		// still finds the account means this was a per-site removal, not a real
		// deletion — leave their reactions on the rest of the network alone.
		if ( is_multisite() && false !== get_userdata( $user_id ) ) {
			return;
		}

		if ( ! is_multisite() ) {
			$removed = $this->purge_current_blog( $user_id );
			if ( $removed > 0 ) {
				$this->log( 'info', "Removed {$removed} emoji reaction(s) of deleted user {$user_id}." );
			}
			return;
		}

		// Only the blogs the user belonged to can hold their reactions, so we
		// purge those rather than all 1000s of sites on the network. The list
		// was captured on wpmu_delete_user, before core unlinked the blogs.
		$blog_ids = $this->user_blogs[ $user_id ] ?? array();
		unset( $this->user_blogs[ $user_id ] );
		if ( empty( $blog_ids ) ) {
			return;
		}

		// Bulk meta edits across many blogs would otherwise balloon the
		// in-memory object cache; stop it growing for the duration of the sweep.
		$was_suspended = wp_suspend_cache_addition();
		wp_suspend_cache_addition( true );

		$removed = 0;
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			try {
				$removed += $this->purge_current_blog( $user_id );
			} catch ( \Throwable $e ) {
				$this->log( 'error', "Emoji reaction cleanup failed on blog {$blog_id} for user {$user_id}: " . $e->getMessage() );
			} finally {
				restore_current_blog();
			}
		}

		wp_suspend_cache_addition( $was_suspended );

		if ( $removed > 0 ) {
			$this->log( 'info', "Removed {$removed} emoji reaction(s) of deleted user {$user_id}." );
		}
	}

	/**
	 * Strip the user from reaction meta on the current blog.
	 *
	 * @param int $user_id The deleted user's ID.
	 * @return int Number of reactions removed.
	 */
	private function purge_current_blog( int $user_id ): int {
		global $wpdb;
		$removed = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id AS object_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				$this->meta_key
			)
		);
		foreach ( $post_rows as $row ) {
			$removed += $this->strip_user( 'post', (int) $row->object_id, $row->meta_value, $user_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$comment_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_id AS object_id, meta_value FROM {$wpdb->commentmeta} WHERE meta_key = %s",
				$this->meta_key
			)
		);
		foreach ( $comment_rows as $row ) {
			$removed += $this->strip_user( 'comment', (int) $row->object_id, $row->meta_value, $user_id );
		}

		return $removed;
	}

	/**
	 * Remove a user's IDs from a single reaction meta value and persist it.
	 *
	 * The value is structured as `[ emoji_char => [ timestamp => user_id ] ]`,
	 * matching this plugin's own save/delete logic.
	 *
	 * @param string $object_type 'post' or 'comment'.
	 * @param int    $object_id   Post or comment ID.
	 * @param mixed  $raw_value   The stored meta value.
	 * @param int    $user_id     The deleted user's ID.
	 * @return int Number of reactions removed from this object.
	 */
	private function strip_user( string $object_type, int $object_id, $raw_value, int $user_id ): int {
		$likes = maybe_unserialize( $raw_value );
		if ( ! is_array( $likes ) ) {
			return 0;
		}

		$removed = 0;
		foreach ( $likes as $emoji => $reactions ) {
			if ( ! is_array( $reactions ) ) {
				continue;
			}
			foreach ( $reactions as $timestamp => $uid ) {
				if ( (int) $uid === $user_id ) {
					unset( $likes[ $emoji ][ $timestamp ] );
					++$removed;
				}
			}
			if ( empty( $likes[ $emoji ] ) ) {
				unset( $likes[ $emoji ] );
			}
		}

		if ( 0 === $removed ) {
			return 0;
		}

		if ( empty( $likes ) ) {
			delete_metadata( $object_type, $object_id, $this->meta_key );
		} else {
			update_metadata( $object_type, $object_id, $this->meta_key, $likes );
		}

		return $removed;
	}

	/**
	 * Log through spaces-core if available, falling back to error_log().
	 *
	 * @param string $level   PSR-3 log level.
	 * @param string $message The message to log.
	 * @return void
	 */
	private function log( string $level, string $message ): void {
		if ( function_exists( 'spaces_log' ) ) {
			spaces_log( $level, $message, array( '_source' => 'emoji-reaction' ) );
		} else {
			error_log( "[emoji-reaction] {$level}: {$message}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
