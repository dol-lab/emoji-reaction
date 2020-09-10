<?php

/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       Author uri
 * @since      0.0.1
 *
 * @package    Emoji_Reactions
 * @subpackage Emoji_Reactions/public/partials
 */
?>
<div class="emoji-reactions-wrapper" data-object-id='<?= $ID ?>' data-object-type='<?= $type ?>'>
    <button class="emoji-reactions-button gray" data-emoji="👍" name="thumbs up"></button>
    <button class="emoji-reactions-button gray" data-emoji="❤️" name="heart"></button>
    <button class="emoji-reactions-button gray" data-emoji="🤔" name="thinking"></button>
    <button class="emoji-reactions-button gray" data-emoji="🧉" name="mate"></button>
    <button class="emoji-reactions-button gray" data-emoji="🦄" name="unicorn"></button>
</div>