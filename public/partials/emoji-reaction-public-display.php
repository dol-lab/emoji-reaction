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

<div class="emoji-reaction-wrapper <?= $align ?>" data-object-id='<?= $ID ?>' data-object-type='<?= $type ?>' data-nonce='<?= wp_create_nonce('_emoji_reaction_action') ?>' data-totalcount="<?= $total_count ?>">

    <div class="emoji-reactions-container">
        <?php foreach($emojis as $emoji) : $count = array_key_exists($emoji[0], $likes) ? sizeof($likes[$emoji[0]]) : 0; ?>
            <button class="emoji-reaction-button<?= in_array(get_current_user_id(), $likes[$emoji[0]]) ? ' voted' : ' not-voted' ?> show-count" data-emoji="<?= $emoji[0] ?>" data-count="<?= $count ?>" name="<?= $emoji[1] ?>"></button>
        <?php endforeach; ?>
    </div>

    <div class="emoji-reaction-button-addnew-container ui icon bottom pointing dropdown <?= $align ?>">
        <button class="emoji-reaction-button-addnew">
            <i class="icon-thumpup-plus"></i>
        </button>
        <div class="menu">
            <div class="item-container">
                <?php foreach($emojis as $emoji) : $count = array_key_exists($emoji[0], $likes) ? sizeof($likes[$emoji[0]]) : 0; ?>
                    <button class="item emoji-reaction-button<?= in_array(get_current_user_id(), $likes[$emoji[0]]) ? ' voted' : ' not-voted' ?>" data-emoji="<?= $emoji[0] ?>" name="<?= $emoji[1] ?>"></button>
                <?php  endforeach; ?>
            </div>
        </div>
    </div>
</div>