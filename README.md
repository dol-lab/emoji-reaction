# Emoji Reaction

A Wordpress plugin which allows emoji reactions on posts and comments by logged in users.

- Version: 0.7.0
- WordPress Version: 4.2 or higher
- Tested up to: 5.9.3
- License: GPL-2.0+
- License URI: http://www.gnu.org/licenses/gpl-2.0.txt

**Important:** This is work in progress. Check issues for todos.

---

Display emoji buttons in your theme with the function `emoji_reaction_display_buttons(array $args)` or the action `do_action('emoji_reaction_display_buttons', array $args)`.
If you don't want to echo the result use the function `emoji_reaction_get_buttons(array $args)`.

**$args**
(array) (Required) An array of settings.

- **'ID'** (int) The post or comment ID. Default is the value of 'get_the_ID' function.
- **'type'** (string) The type of object. Accepts 'post' or 'comment'. Default 'post'.
- **'align'** (string) Alignment of emoji buttons. Accepts 'left' or 'right'. Default 'left'.
- **'usernames'** (int) Max number of usernames shown in tooltip. Default 10.

---

To change the default set of emojis (👍, ❤️, 🙏) add the filter `emoji_reaction_emojis`.

Example:

```
function my_emojis() {
	return array(
		array( '👍', 'thumbs up' ),
		array( '❤️', 'heart' ),
		array( '🔥', 'fire' ),
	);
}
add_filter('emoji_reaction_emojis', 'my_emojis');
```

Be aware: if you remove an emoji, the saved likes according to this emoji will remain in the database.

---

## Dependencies

None. The buttons, the list of who reacted and the emoji picker are plain JavaScript
(no jQuery, no UI framework), using the [Popover API](https://developer.mozilla.org/en-US/docs/Web/API/Popover_API)
for the two popups. Where that is missing, the buttons keep working and only the popups stay closed.

---

## Removed in 0.7.0: charts

The chart feature (the `[emoji_reaction_chart]` shortcode, the `emoji-reaction/chart` block,
`emoji_reaction_get_add_chart_link()` and the bundled Chart.js) has been removed.
Posts that still contain the block render nothing for it; a leftover shortcode is stripped
so it does not show up as text.
