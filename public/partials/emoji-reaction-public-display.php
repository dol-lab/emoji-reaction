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

<div class="emoji-reaction-wrapper" data-object-id='<?= $ID ?>' data-object-type='<?= $type ?>'>
    <button class="emoji-reaction-button gray" data-emoji="👍" name="thumbs up"></button>
    <button class="emoji-reaction-button gray" data-emoji="❤️" name="heart"></button>
    <button class="emoji-reaction-button gray" data-emoji="🤔" name="thinking"></button>
    <button class="emoji-reaction-button gray" data-emoji="🧉" name="mate"></button>
    <button class="emoji-reaction-button gray" data-emoji="🦄" name="unicorn"></button>
</div>