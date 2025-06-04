<?php

/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       Author uri
 * @since      0.0.1
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/public/partials
 */
?>

<div class="emoji-reaction-wrapper <?php echo esc_attr( $align ); ?>" data-object-id='<?php echo esc_attr( $ID ); ?>' data-object-type='<?php echo esc_attr( $type ); ?>' data-nonce='<?php echo esc_attr( wp_create_nonce( '_emoji_reaction_action' ) ); ?>' data-totalcount="<?php echo esc_attr( $total_count ); ?>">

	<div class="emoji-reactions-container">
		<?php
		foreach ( $emojis as $emoji ) :
			$user_ids        = array();
			$user_ids_max    = array();
			$count           = 0;
			$classname_voted = 'not-voted';
			if ( ! empty( $likes ) ) {
				if ( array_key_exists( $emoji[0], $likes ) ) {
					$user_ids        = $this->userid_to_first_position( $likes[ $emoji[0] ], get_current_user_id() );
					$count           = count( $user_ids );
					$classname_voted = in_array( get_current_user_id(), $user_ids ) ? ' voted' : ' not-voted';
					$user_ids_max    = array_slice( $user_ids, 0, $max_usernames );
				}
			}

			?>
		<button class="emoji-reaction-button emoji-reaction-button-popup show-count <?php echo esc_attr( $classname_voted ); ?>" data-emoji="<?php echo esc_attr( $emoji[0] ); ?>" data-count="<?php echo esc_attr( $count ); ?>" name="<?php echo esc_attr( $emoji[1] ); ?>"></button>
		<div class="ui popup emoji-reaction-popup-container">
			<ul class="emoji-reaction-usernames">
						<?php foreach ( $user_ids_max as $user_id ) : ?>
			<li data-user-id="<?php echo esc_attr( $user_id ); ?>"><?php echo esc_html( $this->get_user_name( $user_id ) ); ?></li>
						<?php endforeach; ?>
			</ul>
						<?php if ( $count > $max_usernames ) : ?>
							<?php
							/* translators: %s: number of additional users who reacted */
							printf( '<p>%s</p>', esc_html( sprintf( __( 'And %s more ...', 'emoji-reaction' ), ( $count - $max_usernames ) ) ) );
							?>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>

	<div class="emoji-reaction-button-addnew-container ui icon top pointing dropdown <?php echo esc_attr( $align ); ?>">
	<button class="emoji-reaction-button-addnew">
	<i class="icon-thumpup-plus"></i>
	</button>
		<div class="menu">
			<div class="item-container">
			<?php
			foreach ( $emojis as $emoji ) :
				$classname_voted = 'not-voted';
				if ( ! empty( $likes ) ) {
					if ( array_key_exists( $emoji[0], $likes ) ) {
						$classname_voted = in_array( get_current_user_id(), $likes[ $emoji[0] ] ) ? ' voted' : ' not-voted';
					}
				}
				?>
			<button class="item emoji-reaction-button <?php echo esc_attr( $classname_voted ); ?>" data-emoji="<?php echo esc_attr( $emoji[0] ); ?>" name="<?php echo esc_attr( $emoji[1] ); ?>"></button>
			<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
