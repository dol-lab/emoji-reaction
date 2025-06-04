<?php

/**
 * Return HTML of the emoji buttons.
 *
 * @since 0.3.0
 *
 * @param   array $args {
 *   Required. An array of elements, that identify the object to get liked or null.
 *
 *     @type int 'ID' The post or comment ID. Default is the value of 'get_the_ID' function.
 *     @type string 'type' The type of object. Accepts 'post' or 'comment'. Default 'post'.
 *     @type string 'align' Alignment of emoji buttons. Accepts 'left' or 'right'. Default 'left'.
 *     @type array 'emojis' List of emojis. Default is return of Emoji_Reaction::get_default_emojis().
 *  }
 *
 * @return  html    HTML of emoji buttons.
 */
function emoji_reaction_get_buttons( $args ) {
	ob_start();
	do_action( 'emoji_reaction_display_buttons', $args );
	return ob_get_clean();
}

/**
 * Display emoji buttons.
 *
 * @since 0.3.0
 *
 * @param   array $args {
 *   Required. An array of elements, that identify the object to get liked or null.
 *
 *     @type int 'ID' The post or comment ID. Default is the value of 'get_the_ID' function.
 *     @type string 'type' The type of object. Accepts 'post' or 'comment'. Default 'post'.
 *     @type string 'align' Alignment of emoji buttons. Accepts 'left' or 'right'. Default 'left'.
 *     @type array 'emojis' List of emojis. Default is return of Emoji_Reaction::get_default_emojis().
 *  }
 */
function emoji_reaction_display_buttons( $args ) {
	do_action( 'emoji_reaction_display_buttons', $args );
}

/**
 * Generate a link to add emoji reaction chart to a post.
 *
 * @since 0.4.0
 *
 * @param int $post_id The post ID to add the chart to.
 * @return string The URL to add the chart to the post.
 */
function emoji_reaction_get_add_chart_link( $post_id ) {
	return add_query_arg(
		array(
			'action'   => 'emoji_reaction_add_chart',
			'post_id'  => $post_id,
			'_wpnonce' => wp_create_nonce( 'emoji_reaction_add_chart_' . $post_id ),
		),
		admin_url( 'admin-post.php' )
	);
}
