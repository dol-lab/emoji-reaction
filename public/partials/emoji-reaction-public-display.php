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

<div class="emoji-reaction-wrapper" data-object-id='<?= $ID ?>' data-object-type='<?= $type ?>' data-nonce='<?= wp_create_nonce('_emoji_reaction_action') ?>'>
    <ul>
    <?php foreach($emojis as $emoji) : $count = $this->get_emoji_count($ID, $type, $emoji[0]); ?>
        <li>    
            <button class="emoji-reaction-button<?= in_array(get_current_user_id(), $likes[$emoji[0]]) ? ' voted' : ' gray' ?><?= $count > 0 ? ' show-count' : '' ?>" data-emoji="<?= $emoji[0] ?>" data-count="<?= $count ?>" name="<?= $emoji[1] ?>"></button>
            <ul class="emoji-reaction-usernames">
            <?php foreach($likes[$emoji[0]] as $user_id) : ?>
                <li data-user-id=<?= $user_id ?>><?= $this->get_user_name($user_id); ?></li>
            <?php endforeach; ?>
            </ul>
        </li>
    <?php endforeach; ?>
    </ul>
</div>