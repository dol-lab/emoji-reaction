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

<?php
//debug
var_dump($likes);
?>

<div class="emoji-reaction-wrapper" data-object-id='<?= $ID ?>' data-object-type='<?= $type ?>' data-nonce='<?= wp_create_nonce('_emoji_reaction_action') ?>'>
    <?php foreach($emojis as $emoji) : $count = $this->get_emoji_count($ID, $type, $emoji[0]); ?>
        <button class="emoji-reaction-button<?= in_array(get_current_user_id(), $likes[$emoji[0]]) ? ' voted' : ' gray' ?><?= $count > 0 ? ' show-count' : '' ?>" data-emoji="<?= $emoji[0] ?>" data-count="<?= $count ?>" name="<?= $emoji[1] ?>"></button>
    <?php endforeach; ?>
</div>