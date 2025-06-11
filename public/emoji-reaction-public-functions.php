<?php
/**
 * Public functions accessible for theme developers.
 *
 * This file contains template tags and utility functions that can be used
 * in WordPress themes to display emoji reactions.
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/public
 */

/**
 * Return HTML of the emoji buttons.
 *
 * @param array $args {
 *   Required. An array of elements, that identify the object to get liked or null.
 *
 *     @type int 'ID' The post or comment ID. Default is the value of 'get_the_ID' function.
 *     @type string 'type' The type of object. Accepts 'post' or 'comment'. Default 'post'.
 *     @type string 'align' Alignment of emoji buttons. Accepts 'left' or 'right'. Default 'left'.
 *     @type array 'emojis' List of emojis. Default is return of Emoji_Reaction::get_default_emojis().
 *  }
 * @return string HTML of emoji buttons.
 */
function emoji_reaction_get_buttons( array $args ): string {
	ob_start();
	do_action( 'emoji_reaction_display_buttons', $args );
	return ob_get_clean();
}

/**
 * Display emoji buttons.
 *
 * @param array $args {
 *   Required. An array of elements, that identify the object to get liked or null.
 *
 *     @type int 'ID' The post or comment ID. Default is the value of 'get_the_ID' function.
 *     @type string 'type' The type of object. Accepts 'post' or 'comment'. Default 'post'.
 *     @type string 'align' Alignment of emoji buttons. Accepts 'left' or 'right'. Default 'left'.
 *     @type array 'emojis' List of emojis. Default is return of Emoji_Reaction::get_default_emojis().
 *  }
 */
function emoji_reaction_display_buttons( array $args ): void {
	do_action( 'emoji_reaction_display_buttons', $args );
}

/**
 * Generate a link to add emoji reaction chart to a post.
 *
 * @param int $post_id The post ID to add the chart to.
 * @return string The URL to add the chart to the post.
 */
function emoji_reaction_get_add_chart_link( int $post_id ): string {
	return add_query_arg(
		array(
			'action'   => 'emoji_reaction_add_chart',
			'post_id'  => $post_id,
			'_wpnonce' => wp_create_nonce( 'emoji_reaction_add_chart_' . $post_id ),
		),
		admin_url( 'admin-post.php' )
	);
}
