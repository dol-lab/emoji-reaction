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

## Notes

### One popup node per page, not one per button

The "who reacted" tooltip and the emoji picker are two single nodes appended to `<body>`, both
`popover="manual"`, placed by hand above the anchor button with their right edges aligned, and closed
on outside click, Escape, scroll or resize. One node each is deliberate: the same post is rendered more
than once on some pages, so per-container popups collided on their ids.

The popups use `data-react-aria-top-layer` so they remain interactive when opened from a React Aria
modal.

Hooks for styling and behaviour: `.emoji-reaction-popup`, `#emoji-reaction-names-popup`,
`#emoji-reaction-picker-popup`. Strings the JavaScript shows come from
`wp_localize_script( …, 'emoji_reaction', … )`. The `.inverted` class identifies the tooltip.

### Logging goes through `emoji_reaction_log`

The privacy cleanup (reactions of a deleted user) is the only thing that logs. It fires
`emoji_reaction_log` with `( $level, $message, $source )` — `$level` being a PSR-3 level — and falls
back to `error_log()` while nothing is hooked. The plugin knows no logger of its own, so a host with
one hooks that action.

### The page prints counts, the script rebuilds the state

A page shows a set of buttons per post and comment, and the emoji list, nonce and user are the same in
each. They go out once, as `emoji_reaction.page`; each `window.emojiReactionData[…]` holds only the
object, `can_react`, the non-zero `counts`, the user's `voted` emojis and whatever `display_buttons()`
arguments override (`compact_state()`). `expandState()` in the script turns that back into the full
state, the shape the AJAX responses still have. A key added to `get_state_data()` that the buttons
need on first render must go into `compact_state()` too.
