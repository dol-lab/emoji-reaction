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
 *	   @type array 'emojis' List of emojis. Default is return of Emoji_Reaction::get_emojis().
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
 *	   @type array 'emojis' List of emojis. Default is return of Emoji_Reaction::get_emojis().
 *  }
 */
function emoji_reaction_display_buttons( $args ) {
    do_action( 'emoji_reaction_display_buttons', $args );
}