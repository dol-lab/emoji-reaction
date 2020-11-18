# Emoji Reaction

**Important:** This is work in progress. Check issues for todos.

A Wordpress plugin which allows emoji reactions on posts and comments by logged in users.

---

Apply this filter in your theme to get the emoji buttons:

```
apply_filters('emoji_reaction_buttons', array $args);
```

**$args**
(array) (Required) An array of settings.

- **'ID'** (int) The post or comment ID. Default is the value of 'get_the_ID' function.
- **'type'** (string) The type of object. Accepts 'post' or 'comment'. Default 'post'.
- **'align'** (string) Alignment of emoji buttons. Accepts 'left' or 'right'. Default 'left'.
- **'usernames'** (int) Max number of usernames shown in tooltip. Default 10.
- **'emojis'** (array) List of emojis. Default:

```
$args['emojis'] => array(
	array( '👍', 'thumbs up' ),
	array( '❤️', 'heart' ),
);
```


