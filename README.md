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

None. The buttons, the list of who reacted and the emoji picker are plain JavaScript (no jQuery, no UI framework), using the [Popover API](https://developer.mozilla.org/en-US/docs/Web/API/Popover_API) for the popups and a `<dialog>` for the sheet. Where the Popover API is missing, the buttons keep working and only the popups stay closed.

---

## Notes

### One popup node per page, not one per button

Who reacted (the panel `#emoji-reaction-names-popup` and the sheet `#emoji-reaction-names-sheet`) and the emoji picker (`#emoji-reaction-picker-popup`) are single nodes appended to `<body>`. The panel and the picker are `popover="manual"`, placed by hand above their anchor with the right edges aligned, and closed on outside click, Escape, page scroll or resize; the sheet is a `<dialog>` opened with `showModal()`. One node each is deliberate: the same post is rendered more than once on some pages, so per-container popups collided on their ids. All three carry `data-react-aria-top-layer`, so they remain interactive when opened from a React Aria modal.

Hooks for styling: `.emoji-reaction-popup` (panel and picker), `.emoji-reaction-names` (panel and sheet, which share their content) and the ids above. Strings the JavaScript shows come from `wp_localize_script( …, 'emoji_reaction', … )`.

### Who reacted: a panel on hover, a sheet on touch

Hovering a reaction (or focusing it with the keyboard) opens the panel at that reaction's tab: "All" and one tab per reaction, the most frequent first; more tabs than fit wrap onto the next line. While it has not been clicked into, moving to another reaction switches the tab; a click inside pins it until a click elsewhere or Escape. The list is as tall as the open tab needs (at most 8 rows), and the script places the panel again on a tab change, so its bottom edge stays on the row and only its top moves. Keyboard: ↑/↓ on a reaction moves into the tab bar, the arrow keys switch tabs, Escape or Tab out of the panel returns to the reaction. Hover is `pointerover` with `pointerType === 'mouse'`, and a mouse click on a reaction toggles it.

On touch a tap on a reaction does not react: it opens the same content as the sheet (a long press too), because one reaction per user is the default (`emoji_reaction_max_reactions_per_user`) and small pills in a feed get tapped by accident. Reacting happens in the sheet: an emoji's tab starts with "React with ❤️" while the user has not, and their "You" row takes it back. Whether a click is a tap is its `pointerType`, or the last `pointerdown`'s where a click is no `PointerEvent`. After a long press the `touchend` is cancelled, or its click would land on the sheet's backdrop and close it again.

The current user is first and reads "You"; each of their reactions there is a button that removes it. Where the list is not shown (those who may not react), the "You" row still is, so a reaction made before can be taken back. Any reaction replaces the state, which drops what was loaded, so the open panel asks again.

### Who reacted: only for those who may react, ten at a time

The counts are printed for everyone who sees the post, but `emoji_reaction_ajax_get_reactors` answers only users who pass `user_can_react_to_object()` (`read_post`: on a multisite, members of the site). The script knows this from `can_react` and does not ask: the tabs show the counts and the list says "Only members see who reacted." Widening that is a privacy decision, not a bug fix.

The first request (`prefetch()`, as soon as the pointer enters a set of buttons) answers the first 10 of every tab, so switching tabs needs none. A placeholder row holds the place of each of the next page; an `IntersectionObserver` on the first of them asks for the next 10 of that tab once it scrolls within about a row, so the two rows loaded beyond the visible eight are scrolled to first. A failed request is said in the list and not repeated on every hover. Users are looked up network-wide (`get_users( 'blog_id' => 0 )`): somebody who reacted and has since left the site is still named; a deleted user is "Anonymous" without an avatar. Avatars come from `get_avatar_url()` (only with `show_avatars` on), which costs about two queries per user unless a host primes them: the plugin fires `emoji_reaction_reactors` ( `$user_ids`, `$size` ) first for that (Spaces hooks `spaces_prime_users()`).

### Theming: custom properties and part classes

The stylesheet reads `--emoji-reaction-surface`, `-text`, `-muted`, `-border`, `-hover`, `-accent`, `-radius`, `-shadow`, `-avatar-size` and `-avatar-radius`, with plain defaults (`light-dark()`, so a site that declares `color-scheme` gets a dark default). Set them on `.emoji-reaction-wrapper, .emoji-reaction-popup, .emoji-reaction-sheet` when their values are themselves custom properties that change below `:root`: a custom property resolves where it is declared.

The filter `emoji_reaction_part_classes` adds classes to the sheet (`sheet`, `sheet_header` for the tab bar, `sheet_close`), so a host can make it one of its own modals. The sheet's own box rules are behind `:where()`, so those classes win. Its content is laid out for that: the tab bar first, the list (the scroll container the observer watches) as its sibling, the close button last.

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

### Asset URLs carry the file's modification time

The public script and stylesheet are versioned by `filemtime()` (`asset_version()`), not by `EMOJI_REACTION_VERSION`, in every environment. A script change that is not paired with a version bump would otherwise stay cached in browsers while the page already prints data in a new shape (as with the compact state above: an old script fails with `Cannot read properties of undefined (reading 'filter')`).
