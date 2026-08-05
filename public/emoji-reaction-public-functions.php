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
