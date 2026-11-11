/**
 * Emoji reactions: the buttons, who reacted, and the picker.
 *
 * Plain JavaScript, no library. The popups (who reacted, and the picker of the
 * "add a reaction" button) are single nodes in the top layer, opened with the
 * Popover API and placed by hand; on a touch screen "who reacted" is a sheet,
 * a `<dialog>` opened with `showModal()`:
 *
 * - **One node each, not one per button.** The same post is on the page more
 *   than once (a card and its comments, a list and a modal) and its reactions
 *   are rendered again after every change, so popups that live inside a
 *   container are built and thrown away by the dozen, and their ids collide.
 * - **In the top layer**, so an `overflow: hidden` card cannot cut them off and
 *   nothing has to out-guess a z-index.
 *
 * Without the Popover API the buttons still react; only the popups stay shut.
 */
(() => {
	'use strict';

	const LONG_PRESS_MS = 500;
	const HOVER_SHOW_MS = 200;
	const HOVER_HIDE_MS = 300; // Long enough to cross from a button into the panel.
	const VISIBLE_ROWS = 8;
	const NOTIFICATION_TIMEOUT_MS = 5000;
	const DISMISS_ANIMATION_MS = 300;
	const NOTIFICATION_ID = 'emoji-reaction-limit-notification';
	const TOAST_CONTAINER_ID = 'toast-container';
	const NAMES_POPUP_ID = 'emoji-reaction-names-popup';
	const NAMES_SHEET_ID = 'emoji-reaction-names-sheet';
	const PICKER_POPUP_ID = 'emoji-reaction-picker-popup';
	const ALL = 'all';

	const GAP = 6; // Between a button and its popup.
	const EDGE = 8; // How close to the edge of the window a popup may come.

	const hasPopover = typeof HTMLElement !== 'undefined' && typeof HTMLElement.prototype.showPopover === 'function';

	// --- Helpers ---

	const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];
	const qs = (selector, root = document) => root.querySelector(selector);

	/** Text into markup: everything here is a display name or a translation. */
	const esc = (value) =>
		String(value).replace(
			/[&<>"']/g,
			(char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]
		);

	/** `sprintf` for the handful of `%s` / `%d` / `%1$s` patterns we localize. */
	const sprintf = (format, ...args) =>
		String(format).replace(/%(?:(\d+)\$)?[sd]/g, (match, position) => {
			const value = args[position ? position - 1 : 0];
			return value === undefined ? match : value;
		});

	/**
	 * @param {Object} params Fields of the request.
	 * @return {Promise<Object>} The decoded response.
	 */
	async function postAjax(params) {
		const formData = new FormData();
		for (const [key, value] of Object.entries(params)) {
			formData.append(key, value);
		}
		const response = await fetch(emoji_reaction.ajax_url, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		});
		return response.json();
	}

	/**
	 * The full state of a set of buttons from what the page printed for it.
	 *
	 * The page prints only counts and what differs from `emoji_reaction.page`
	 * (see `compact_state()`); everything else is shared. An emoji with a count
	 * that is no longer in the list is a legacy one: shown, never offered.
	 *
	 * @param {Object} compact State as printed in `window.emojiReactionData`.
	 * @return {Object} State in the shape the AJAX responses have.
	 */
	function expandState(compact) {
		const { counts = {}, voted = [], ...state } = { ...emoji_reaction.page, ...compact };
		const entry = (emoji, name) => ({
			emoji,
			name,
			count: counts[emoji] ?? 0,
			user_voted: voted.includes(emoji),
		});
		const listed = state.emojis.map(([emoji, name]) => entry(emoji, name));
		const legacy = Object.keys(counts)
			.filter((emoji) => !state.emojis.some(([listedEmoji]) => listedEmoji === emoji))
			.map((emoji) => ({ ...entry(emoji, emoji), legacy: true }));
		const emojis = [...listed, ...legacy];

		return { ...state, emojis, total_count: emojis.reduce((sum, emoji) => sum + emoji.count, 0) };
	}

	/**
	 * @param {Object} emoji An emoji of the state.
	 * @return {string} Class for whether the user reacted with it.
	 */
	function votedClass(emoji) {
		return emoji.user_voted ? 'voted' : 'not-voted';
	}

	/**
	 * Scrolls a row that scrolls sideways so that one of its children is in view, and nothing else.
	 *
	 * @param {HTMLElement} el A child of the row (the row is its offset parent).
	 */
	function keepInView(el) {
		const row = el.parentElement;
		const start = el.offsetLeft;
		const end = start + el.offsetWidth;
		if (start < row.scrollLeft) {
			row.scrollLeft = start;
		} else if (end > row.scrollLeft + row.clientWidth) {
			row.scrollLeft = end - row.clientWidth;
		}
	}

	/**
	 * "I am in the top layer, do not hide me." A react-aria modal marks every
	 * other child of `<body>` `inert` while it is open, and watches for new ones,
	 * so a popup opened over one would be painted and then take no clicks at
	 * all. This is the attribute its `ariaHideOutside()` checks for, the same way
	 * it exempts its own overlays.
	 *
	 * @param {HTMLElement} node A popup.
	 * @return {HTMLElement} The same node, appended to `<body>`.
	 */
	function appendToTopLayer(node) {
		node.dataset.reactAriaTopLayer = 'true';
		document.body.append(node);
		return node;
	}

	// --- Popups ---

	/**
	 * A popup in the top layer, made once and filled in whenever it opens.
	 *
	 * `manual` popovers: the picker is opened by a click that the browser would
	 * otherwise take as the click that dismisses it, and who reacted follows the
	 * pointer, which is not a dismissal either. What closes them is in the
	 * handlers below.
	 */
	class Popup {
		/**
		 * @param {string} id        Id of the node.
		 * @param {string} className Classes of the node.
		 * @param {Object} attrs     Further attributes.
		 */
		constructor(id, className, attrs = {}) {
			this.node = document.createElement('div');
			this.node.id = id;
			this.node.className = className;
			this.node.setAttribute('popover', 'manual');
			for (const [name, value] of Object.entries(attrs)) {
				this.node.setAttribute(name, value);
			}
			this.anchor = null;
			appendToTopLayer(this.node);
		}

		/** @return {boolean} Whether it is open. */
		get isOpen() {
			return this.node.classList.contains('is-open');
		}

		/**
		 * Shows the popup with the given markup, over (or under) an anchor.
		 *
		 * @param {HTMLElement} anchor What the popup belongs to.
		 * @param {string}      html   Content of the popup.
		 */
		show(anchor, html) {
			if (!anchor?.isConnected) {
				this.hide();
				return;
			}
			this.anchor = anchor;
			this.node.innerHTML = html;

			if (!this.isOpen) {
				this.node.style.visibility = 'hidden'; // Placed before it is seen anywhere.
				this.node.classList.add('is-open');
				this.node.showPopover();
			}
			this.position();
			this.node.style.visibility = '';
		}

		hide() {
			if (!this.isOpen) {
				return;
			}
			this.node.classList.remove('is-open');
			this.node.hidePopover();
			this.node.innerHTML = '';
			this.anchor = null;
		}

		/**
		 * Puts the popup above its anchor, their right edges on one line.
		 *
		 * Above, because a reaction row sits at the bottom of a card and what is
		 * below it is the next card; right-aligned, because the row itself is laid
		 * out from the right. It goes below when there is no room above, and is
		 * slid sideways to stay inside the window.
		 */
		position() {
			if (!this.anchor?.isConnected) {
				this.hide();
				return;
			}
			const box = this.anchor.getBoundingClientRect();
			const width = this.node.offsetWidth;
			const height = this.node.offsetHeight;
			const room = document.documentElement.clientWidth;

			const above = box.top - GAP - height;
			const top = above >= EDGE ? above : Math.min(box.bottom + GAP, window.innerHeight - EDGE - height);
			const left = Math.max(EDGE, Math.min(box.right - width, room - width - EDGE));

			this.node.style.left = `${left}px`;
			this.node.style.top = `${Math.max(EDGE, top)}px`;
		}
	}

	// --- Manager ---

	class EmojiReactionManager {
		constructor() {
			this.containers = {};
			this.eventsSetup = false;

			/**
			 * Who reacted, if it is open: for which set of buttons and tab, as the
			 * hover panel or as the sheet, and whether it was clicked into (then it
			 * no longer follows the pointer).
			 *
			 * @type {?{containerId: string, wrapper: HTMLElement, tab: string, sheet: boolean, pinned: boolean}}
			 */
			this.panel = null;
			/** Which set of buttons the picker is open for, if any. */
			this.openPicker = null; // { containerId, wrapper }

			this.names = null;
			this.sheet = null;
			this.picker = null;
			this.observer = null;
			this.hoverTimer = null;
			/** Set while the script itself moves the focus back to a reaction. */
			this.returningFocus = false;
		}

		init() {
			if (window.emojiReactionData) {
				for (const id of Object.keys(window.emojiReactionData)) {
					this.initContainer(id);
				}
			}
			if (!this.eventsSetup) {
				this.setupPopups();
				this.setupEventListeners();
				this.eventsSetup = true;
			}
		}

		setupPopups() {
			const classes = emoji_reaction.classes ?? {};

			this.sheet = appendToTopLayer(document.createElement('dialog'));
			this.sheet.id = NAMES_SHEET_ID;
			this.sheet.className = `emoji-reaction-sheet emoji-reaction-names ${classes.sheet ?? ''}`.trim();
			this.sheet.setAttribute('aria-label', emoji_reaction.who_reacted);
			this.sheet.addEventListener('close', () => {
				if (this.panel?.sheet) {
					this.panel = null;
					this.observer?.disconnect();
				}
			});

			if (!hasPopover) {
				return;
			}
			this.names = new Popup(NAMES_POPUP_ID, 'emoji-reaction-popup emoji-reaction-names', {
				role: 'dialog',
				'aria-label': emoji_reaction.who_reacted,
			});
			this.picker = new Popup(PICKER_POPUP_ID, 'emoji-reaction-popup emoji-reaction-picker', {
				role: 'menu',
				'aria-label': emoji_reaction.choose_reaction,
			});
		}

		/** @param {string} containerId Id of the set of buttons. */
		initContainer(containerId) {
			const data = window.emojiReactionData[containerId];
			if (!data) return;
			this.containers[containerId] = expandState(data);
			this.renderContainer(containerId);
		}

		/** @param {string} containerId Id of the set of buttons. */
		renderContainer(containerId) {
			const data = this.containers[containerId];
			const elements = qsa(`[id="${containerId}"]`);
			if (!elements.length || !data) return;

			for (const el of elements) {
				el.innerHTML = this.buildContainerHTML(data);
			}

			// The buttons the open popups hang on are gone; find the new ones. The
			// wrapper they are in is still the same node, and it is the one of the
			// copies of this container the reader is actually looking at.
			if (this.panel?.containerId === containerId) {
				this.renderPanel();
			}
			if (this.openPicker?.containerId === containerId) {
				this.showPicker(containerId, this.openPicker.wrapper);
			}
		}

		// --- HTML builders ---

		/**
		 * @param {Object} data State of the set of buttons.
		 * @return {string} The buttons.
		 */
		buildContainerHTML(data) {
			const mainButtons = data.emojis
				.filter((e) => e.count > 0)
				.map((e) => this.buildEmojiButton(e))
				.join('');

			let addNewContainer = '';
			if (data.current_user_id > 0 && data.can_react) {
				addNewContainer = `
					<div class="emoji-reaction-button-addnew-container">
						<button
							class="emoji-reaction-button-addnew"
							type="button"
							aria-label="${esc(emoji_reaction.add_reaction)}"
							aria-expanded="false"
							aria-haspopup="true"
							aria-controls="${PICKER_POPUP_ID}"
						>
							<i class="icon-thumpup-plus"></i>
						</button>
					</div>`;
			}

			return `
				<div class="emoji-reaction-container"
					data-object-id="${esc(data.object_id)}"
					data-object-type="${esc(data.object_type)}"
					data-nonce="${esc(data.nonce)}"
					data-totalcount="${esc(data.total_count)}"
				>${mainButtons}</div>
				${addNewContainer}`;
		}

		/**
		 * @param {Object} emoji An emoji of the state.
		 * @return {string} Its button.
		 */
		buildEmojiButton(emoji) {
			let label = sprintf(emoji_reaction.button_label, emoji.name, emoji.count);
			if (emoji.user_voted) label += ` – ${emoji_reaction.you_reacted}`;

			return `
				<button class="emoji-reaction-button emoji-reaction-button-popup ${votedClass(emoji)}"
					data-emoji="${esc(emoji.emoji)}"
					data-count="${esc(emoji.count)}"
					name="${esc(emoji.name)}"
					type="button"
					aria-label="${esc(label)}"
					aria-pressed="${emoji.user_voted ? 'true' : 'false'}"
				></button>`;
		}

		/**
		 * @param {Object} data State of the set of buttons.
		 * @return {string} Content of the picker.
		 */
		buildPickerHTML(data) {
			const items = data.emojis
				.filter((emoji) => !emoji.legacy)
				.map((emoji) => {
					let label = sprintf(emoji_reaction.react_with, emoji.name);
					if (emoji.user_voted) label += ` (${emoji_reaction.currently_selected})`;

					return `<button class="item emoji-reaction-button ${votedClass(emoji)}"
						data-emoji="${esc(emoji.emoji)}"
						name="${esc(emoji.name)}"
						type="button"
						role="menuitem"
						aria-label="${esc(label)}"
						aria-pressed="${emoji.user_voted ? 'true' : 'false'}"></button>`;
				})
				.join('');

			return `<div class="item-container" role="group">${items}</div>`;
		}

		/**
		 * The tabs of who reacted: everybody, then each reaction, the most frequent first.
		 *
		 * Counts are the server's once it has answered (a person who reacted twice
		 * is one in "All"), the page's until then.
		 *
		 * @param {Object} data State of the set of buttons.
		 * @return {Array<{key: string, count: number, label: string, emoji: ?string}>} The tabs.
		 */
		tabsOf(data) {
			const totals = data.reactors?.tabs ?? {};
			const all = totals[ALL]?.total ?? data.total_count;
			const emojis = data.emojis
				.filter((e) => e.count > 0)
				.map((e) => {
					const count = totals[e.emoji]?.total ?? e.count;
					return { key: e.emoji, count, label: sprintf(emoji_reaction.button_label, e.name, count), emoji: e.emoji };
				})
				.sort((a, b) => b.count - a.count);

			return [
				{ key: ALL, count: all, label: sprintf(emoji_reaction.button_label, emoji_reaction.all_reactions, all), emoji: null },
				...emojis,
			];
		}

		/**
		 * The tab bar and the list below it, as tall as the open tab needs (up to
		 * VISIBLE_ROWS rows), placeholders included, so it keeps its size while the
		 * people arrive.
		 *
		 * @param {Object} data  State of the set of buttons.
		 * @param {Array}  tabs  See tabsOf().
		 * @param {Object} panel See `this.panel`.
		 * @param {string} id    Id of the node the content goes into.
		 * @return {string} Markup.
		 */
		buildPanelHTML(data, tabs, panel, id) {
			const headerClass = panel.sheet ? (emoji_reaction.classes?.sheet_header ?? '') : '';
			const tabButtons = tabs
				.map((tab, i) => {
					const selected = tab.key === panel.tab;
					const title = tab.emoji
						? `<span class="emoji" aria-hidden="true">${esc(tab.emoji)}</span>`
						: `<span aria-hidden="true">${esc(emoji_reaction.all)}</span>`;
					return `<button type="button" role="tab" class="emoji-reaction-tab" id="${id}-tab-${i}"
						data-tab="${esc(tab.key)}" aria-label="${esc(tab.label)}"
						aria-selected="${selected}" aria-controls="${id}-list" tabindex="${selected ? 0 : -1}"
						${selected && panel.sheet ? 'autofocus' : ''}
					>${title}<span class="count" aria-hidden="true">${esc(tab.count)}</span></button>`;
				})
				.join('');
			const selectedIndex = tabs.findIndex((tab) => tab.key === panel.tab);

			const note = this.noteOf(data);
			const list = note
				? `<p class="emoji-reaction-names-note">${esc(note)}</p>`
				: `<ul class="emoji-reaction-reactors" id="${id}-list" role="tabpanel" tabindex="0"
					aria-labelledby="${id}-tab-${selectedIndex}" style="--rows: ${Math.min(tabs[selectedIndex].count, VISIBLE_ROWS)}"
				>${this.buildListBody(data, panel)}</ul>`;

			return `<div class="emoji-reaction-tabs ${esc(headerClass)}" role="tablist" aria-label="${esc(emoji_reaction.who_reacted)}">${tabButtons}</div>${list}`;
		}

		/**
		 * What to say instead of the list: to those who are not told, and when loading failed.
		 *
		 * @param {Object} data State of the set of buttons.
		 * @return {string} The note, empty for the list.
		 */
		noteOf(data) {
			if (!data.can_react) return emoji_reaction.members_only;
			if (data.reactors?.status === 'failed') return emoji_reaction.names_failed;
			return '';
		}

		/**
		 * The rows of the open tab: who has been loaded, then a placeholder for each
		 * of the next page (the first of them asks for it once it scrolls near).
		 *
		 * @param {Object} data  State of the set of buttons.
		 * @param {Object} panel See `this.panel`.
		 * @return {string} `<li>`s.
		 */
		buildListBody(data, panel) {
			const reactors = data.reactors;
			const tab = reactors?.tabs?.[panel.tab];
			if (reactors?.status !== 'loaded' || !tab) {
				const count = this.tabsOf(data).find((t) => t.key === panel.tab)?.count ?? 0;
				return this.buildPlaceholders(Math.min(count, VISIBLE_ROWS));
			}
			return tab.users.map((user) => this.buildReactorRow(data, user, panel.sheet)).join('') + this.buildListTail(tab);
		}

		/**
		 * @param {Object} tab A tab as the server answers it, with the users loaded so far.
		 * @return {string} Placeholders for its next page, or the note that it failed.
		 */
		buildListTail(tab) {
			if (tab.failed) {
				return `<li class="emoji-reaction-names-note">${esc(emoji_reaction.names_failed)}</li>`;
			}
			return this.buildPlaceholders(Math.min(tab.total - tab.users.length, VISIBLE_ROWS));
		}

		/**
		 * @param {number} count How many.
		 * @return {string} Rows that hold the place of people still to come.
		 */
		buildPlaceholders(count) {
			return '<li class="placeholder" aria-hidden="true"></li>'.repeat(Math.max(0, count));
		}

		/**
		 * One person: avatar, name and their reactions. The current user is "You",
		 * and each of their reactions is a button that takes it back.
		 *
		 * @param {Object}  data  State of the set of buttons.
		 * @param {Object}  user  `{id, name, avatar, emojis}` from the server.
		 * @param {boolean} sheet Whether this is the sheet (touch) rather than the panel.
		 * @return {string} An `<li>`.
		 */
		buildReactorRow(data, user, sheet) {
			const you = Number(user.id) === Number(data.current_user_id);
			const name = you ? emoji_reaction.you : user.name || emoji_reaction.anonymous;
			const avatar = user.avatar
				? `<img class="emoji-reaction-avatar" src="${esc(user.avatar)}" alt="" width="24" height="24" loading="lazy">`
				: `<span class="emoji-reaction-avatar" aria-hidden="true">${esc(user.name ? [...user.name][0] : '')}</span>`;

			if (!you) {
				return `<li class="emoji-reaction-reactor">${avatar}<span class="name">${esc(name)}</span><span class="emojis">${esc(user.emojis.join(''))}</span></li>`;
			}

			const nameOf = (char) => data.emojis.find((e) => e.emoji === char)?.name ?? char;
			const buttons = user.emojis
				.map(
					(char) =>
						`<button type="button" class="emoji-reaction-remove" data-emoji="${esc(char)}"
							aria-label="${esc(sprintf(emoji_reaction.remove_reaction, nameOf(char)))}">${esc(char)}</button>`
				)
				.join('');
			const hint = sheet ? emoji_reaction.tap_to_remove : emoji_reaction.click_to_remove;

			return `<li class="emoji-reaction-reactor is-you">${avatar}<span class="name">${esc(name)}<small>${esc(hint)}</small></span><span class="emojis">${buttons}</span></li>`;
		}

		// --- Who reacted ---

		/**
		 * Opens who reacted, at a tab: as the panel over the buttons, or as the sheet.
		 *
		 * @param {{containerId: string, wrapper: HTMLElement}} context The set of buttons.
		 * @param {string}                                     tab     ALL or an emoji.
		 * @param {boolean}                                    sheet   As the sheet (touch).
		 */
		openPanel({ containerId, wrapper }, tab, sheet = false) {
			if (!sheet && !this.names) return;
			this.hidePicker();
			if (this.panel && this.panel.sheet !== sheet) this.hidePanel();
			this.panel = { containerId, wrapper, tab, sheet, pinned: false };
			this.renderPanel();
			if (sheet && this.panel && !this.sheet.open) this.sheet.showModal();
		}

		/**
		 * Draws the open panel or sheet again from the state as it is now, and asks
		 * for who reacted if nobody has yet.
		 */
		renderPanel() {
			const panel = this.panel;
			const data = panel && this.containers[panel.containerId];
			if (!data?.total_count) {
				this.hidePanel();
				return;
			}
			this.prefetch(panel.containerId);

			const tabs = this.tabsOf(data);
			if (!tabs.some((tab) => tab.key === panel.tab && tab.count > 0)) {
				panel.tab = ALL;
			}

			const host = panel.sheet ? this.sheet : this.names.node;
			const anchor = panel.sheet || qs('.emoji-reaction-container', panel.wrapper);
			if (!anchor?.isConnected && !panel.sheet) {
				this.hidePanel(); // Its reactions have left the page.
				return;
			}
			const hadFocus = host.contains(document.activeElement);
			const html = this.buildPanelHTML(data, tabs, panel, host.id);

			if (panel.sheet) {
				const closeClass = emoji_reaction.classes?.sheet_close ?? '';
				host.innerHTML = `${html}<button type="button" class="emoji-reaction-sheet-close ${esc(closeClass)}" data-close
					aria-label="${esc(emoji_reaction.close)}"><span aria-hidden="true">&times;</span></button>`;
			} else {
				this.names.show(anchor, html);
			}

			const selected = qs('[role="tab"][aria-selected="true"]', host);
			if (selected) keepInView(selected);
			if (hadFocus) selected?.focus();
			this.observeMore(host);
		}

		/**
		 * Switches the open panel to another tab. Only the list is drawn again, so
		 * the tab bar keeps its scroll position and the focus; the panel takes the
		 * new tab's height and is placed again, its bottom edge staying on the row.
		 *
		 * @param {string} key ALL or an emoji.
		 */
		selectTab(key) {
			const panel = this.panel;
			const data = panel && this.containers[panel.containerId];
			if (!data || panel.tab === key) return;
			panel.tab = key;

			const host = panel.sheet ? this.sheet : this.names.node;
			let selected = null;
			for (const tab of qsa('[role="tab"]', host)) {
				const isSelected = tab.dataset.tab === key;
				tab.setAttribute('aria-selected', String(isSelected));
				tab.tabIndex = isSelected ? 0 : -1;
				if (isSelected) selected = tab;
			}
			const list = qs('.emoji-reaction-reactors', host);
			if (list && selected) {
				const count = this.tabsOf(data).find((tab) => tab.key === key)?.count ?? 0;
				list.style.setProperty('--rows', Math.min(count, VISIBLE_ROWS));
				list.setAttribute('aria-labelledby', selected.id);
				list.innerHTML = this.buildListBody(data, panel);
				list.scrollTop = 0;
				keepInView(selected);
			}
			if (!panel.sheet) this.names.position();
			this.observeMore(host);
		}

		hidePanel() {
			const panel = this.panel;
			if (!panel) return;
			this.panel = null;
			clearTimeout(this.hoverTimer);
			this.observer?.disconnect();
			if (panel.sheet) {
				this.sheet.close();
			} else {
				this.names.hide();
			}
		}

		/**
		 * The pointer (or the keyboard focus) is on a reaction: open who reacted at
		 * its tab, or switch to it while the panel still follows the pointer.
		 *
		 * @param {{containerId: string, wrapper: HTMLElement}} context The set of buttons.
		 * @param {string}                                     emoji   The reaction.
		 * @param {number}                                     delay   Before a closed panel opens.
		 */
		pointAt(context, emoji, delay = HOVER_SHOW_MS) {
			const panel = this.panel;
			if (panel?.sheet || panel?.pinned) return;
			if (panel?.wrapper === context.wrapper) {
				this.selectTab(emoji);
				return;
			}
			this.hoverTimer = setTimeout(() => this.openPanel(context, emoji), delay);
		}

		/**
		 * Closes the panel and puts the focus back on its reaction (or the first
		 * one), without that focus opening it again.
		 */
		closePanelToButton() {
			const panel = this.panel;
			const button =
				qs(`.emoji-reaction-button-popup[data-emoji="${CSS.escape(panel.tab)}"]`, panel.wrapper) ??
				qs('.emoji-reaction-button-popup', panel.wrapper);
			this.hidePanel();
			this.returningFocus = true;
			button?.focus();
			this.returningFocus = false;
		}

		/**
		 * Watches the first placeholder of the open list: once it scrolls near, the
		 * next page is asked for. The margin is about one row, so the rows loaded
		 * beyond the visible ones are scrolled to before anything new is asked for.
		 *
		 * @param {HTMLElement} host The panel or the sheet.
		 */
		observeMore(host) {
			this.observer?.disconnect();
			const list = qs('.emoji-reaction-reactors', host);
			const next = list && qs('.placeholder', list);
			if (!next || !('IntersectionObserver' in window)) return;

			this.observer = new IntersectionObserver((entries) => entries.some((e) => e.isIntersecting) && this.loadMore(), {
				root: list,
				rootMargin: '0px 0px 40px 0px',
			});
			this.observer.observe(next);
		}

		/**
		 * Asks once for the first page of every tab, if there is anybody and the
		 * server would say. Called as soon as the pointer is over a set of buttons,
		 * so the people are usually here before the panel opens.
		 *
		 * The answer lives on the state; a reaction replaces the state, which drops
		 * it, so the next look asks again.
		 *
		 * @param {string} containerId Id of the set of buttons.
		 */
		async prefetch(containerId) {
			const data = this.containers[containerId];
			if (!data?.can_react || !data.total_count || data.reactors) return;

			const reactors = (data.reactors = { status: 'loading', tabs: {} });
			try {
				const result = await postAjax({
					action: 'emoji_reaction_ajax_get_reactors',
					object_id: data.object_id,
					object_type: data.object_type,
					nonce: data.nonce,
				});
				if (!result.success || !result.data?.tabs) {
					throw new Error(result.data?.message ?? 'No tabs in the response');
				}
				reactors.tabs = result.data.tabs;
				reactors.status = 'loaded';
			} catch (error) {
				console.error('emoji_reaction: loading who reacted failed', error);
				reactors.status = 'failed'; // Said in the panel, not asked again on every hover.
			}
			if (this.panel?.containerId === containerId && this.containers[containerId] === data) {
				this.renderPanel();
			}
		}

		/** Loads the next page of the open tab and appends it to the list. */
		async loadMore() {
			const panel = this.panel;
			const data = panel && this.containers[panel.containerId];
			const reactors = data?.reactors;
			const key = panel?.tab;
			const tab = reactors?.status === 'loaded' && reactors.tabs[key];
			if (!tab || tab.loading || tab.failed || tab.users.length >= tab.total) return;

			tab.loading = true;
			try {
				const result = await postAjax({
					action: 'emoji_reaction_ajax_get_reactors',
					object_id: data.object_id,
					object_type: data.object_type,
					nonce: data.nonce,
					tab: key,
					offset: tab.users.length,
				});
				const page = result.success && result.data?.tabs?.[key];
				if (!page) {
					throw new Error(result.data?.message ?? 'No page in the response');
				}
				tab.users.push(...page.users);
				tab.total = page.total;
			} catch (error) {
				console.error('emoji_reaction: loading more of who reacted failed', error);
				tab.failed = true;
			}
			tab.loading = false;

			if (this.panel !== panel || panel.tab !== key || data.reactors !== reactors) return;
			const host = panel.sheet ? this.sheet : this.names.node;
			const list = qs('.emoji-reaction-reactors', host);
			if (!list) return;
			const shown = list.querySelectorAll('.emoji-reaction-reactor').length;
			for (const el of qsa('.placeholder, .emoji-reaction-names-note', list)) el.remove();
			list.insertAdjacentHTML(
				'beforeend',
				tab.users
					.slice(shown)
					.map((user) => this.buildReactorRow(data, user, panel.sheet))
					.join('') + this.buildListTail(tab)
			);
			this.observeMore(host);
		}

		// --- Picker ---

		/**
		 * @param {string}      containerId Id of the set of buttons.
		 * @param {HTMLElement} wrapper     Which copy of the container it is for.
		 */
		showPicker(containerId, wrapper) {
			const data = this.containers[containerId];
			const anchor = qs('.emoji-reaction-button-addnew', wrapper);
			if (!data || !anchor || !this.picker) {
				this.hidePicker();
				return;
			}

			this.hidePanel();
			this.openPicker = { containerId, wrapper };
			this.picker.show(anchor, this.buildPickerHTML(data));
			anchor.setAttribute('aria-expanded', 'true');
		}

		/** @param {{restoreFocus: boolean}} options Whether the focus goes back to the button. */
		hidePicker({ restoreFocus = false } = {}) {
			if (!this.openPicker) {
				return;
			}
			const anchor = this.picker.anchor;
			anchor?.setAttribute('aria-expanded', 'false');
			this.openPicker = null;

			const takeFocusBack = restoreFocus && this.picker.node.contains(document.activeElement);
			this.picker.hide();
			if (takeFocusBack && anchor?.isConnected) {
				anchor.focus();
			}
		}

		/** Closes what floats over the page: the panel and the picker, not the sheet (it closes itself). */
		hideAllPopups() {
			if (this.panel && !this.panel.sheet) this.hidePanel();
			this.hidePicker({ restoreFocus: true });
		}

		/**
		 * Which set of buttons an element belongs to.
		 *
		 * For a button in the picker that is the set the picker was opened from:
		 * the picker itself is a child of `<body>`, next to no reactions at all.
		 *
		 * @param {HTMLElement} element A button.
		 * @return {?{containerId: string, wrapper: HTMLElement}} Its container, if it has one.
		 */
		contextOf(element) {
			const wrapper = element.closest('.emoji-reaction-wrapper');
			if (wrapper) {
				return { containerId: wrapper.id, wrapper };
			}
			if (element.closest(`#${PICKER_POPUP_ID}`) && this.openPicker) {
				return this.openPicker;
			}
			return null;
		}

		// --- Event listeners ---

		setupEventListeners() {
			let longPressTimer;
			let isLongPress = false;
			let touching = false;
			const inFloating = (el) => el.closest?.(`.emoji-reaction-popup, #${NAMES_SHEET_ID}`);

			document.addEventListener('click', (event) => {
				const target = event.target;
				const button = target.closest('.emoji-reaction-button');

				if (button) {
					if (isLongPress) {
						isLongPress = false;
						return;
					}
					event.preventDefault();
					event.stopPropagation();
					this.handleEmojiClick(button);
					return;
				}

				const addNew = target.closest('.emoji-reaction-button-addnew');
				if (addNew) {
					event.preventDefault();
					event.stopPropagation();
					this.handleAddNewToggle(addNew);
					return;
				}

				const tab = target.closest('.emoji-reaction-names [role="tab"]');
				if (tab) {
					this.selectTab(tab.dataset.tab);
					return;
				}

				const remove = target.closest('.emoji-reaction-remove');
				if (remove && this.panel) {
					this.sendReaction(this.panel.containerId, remove.dataset.emoji, true);
					return;
				}

				// A click on the backdrop of the sheet: the dialog itself, outside its box.
				if (target === this.sheet) {
					const box = this.sheet.getBoundingClientRect();
					const inside =
						event.clientX >= box.left &&
						event.clientX <= box.right &&
						event.clientY >= box.top &&
						event.clientY <= box.bottom;
					if (!inside) this.hidePanel();
					return;
				}
				if (target.closest('.emoji-reaction-sheet-close')) {
					this.hidePanel();
					return;
				}

				if (!inFloating(target)) {
					this.hideAllPopups();
				}
			});

			// Clicked into, the panel stays until a click elsewhere or Escape.
			document.addEventListener('pointerdown', (event) => {
				if (this.panel && !this.panel.sheet && this.names.node.contains(event.target)) {
					this.panel.pinned = true;
				}
			});

			// Hover over a reaction: who reacted, at its tab. The pointer may travel
			// into the panel itself, which is why it is not closed until it has left both.
			document.addEventListener('pointerover', (event) => {
				if (event.pointerType !== 'mouse') return;

				const wrapper = event.target.closest('.emoji-reaction-wrapper');
				if (wrapper) {
					this.prefetch(wrapper.id);
				}

				const button = event.target.closest('.emoji-reaction-button-popup');
				clearTimeout(this.hoverTimer);

				if (button) {
					const context = this.contextOf(button);
					if (context) this.pointAt(context, button.dataset.emoji);
					return;
				}

				if (this.panel && !this.panel.sheet && !this.panel.pinned && !event.target.closest(`#${NAMES_POPUP_ID}`)) {
					this.hoverTimer = setTimeout(() => this.hidePanel(), HOVER_HIDE_MS);
				}
			});

			// Keyboard: focus on a reaction shows who reacted like hovering does; leaving the reactions closes it.
			document.addEventListener('focusin', (event) => {
				const button = event.target.closest?.('.emoji-reaction-button-popup');
				if (button?.matches(':focus-visible') && !this.returningFocus) {
					const context = this.contextOf(button);
					if (context) {
						clearTimeout(this.hoverTimer);
						this.pointAt(context, button.dataset.emoji, 0);
					}
					return;
				}
				if (
					this.panel &&
					!this.panel.sheet &&
					!this.names.node.contains(event.target) &&
					event.target.closest?.('.emoji-reaction-wrapper') !== this.panel.wrapper
				) {
					this.hidePanel();
				}
			});

			// Touch: a long press asks who reacted, a tap reacts.
			document.addEventListener(
				'touchstart',
				(event) => {
					const button = event.target.closest('.emoji-reaction-button-popup');
					isLongPress = false;
					if (!button) return;

					const context = this.contextOf(button);
					if (!context) return;
					touching = true;
					this.prefetch(context.containerId);
					longPressTimer = setTimeout(() => {
						isLongPress = true;
						this.openPanel(context, button.dataset.emoji, true);
					}, LONG_PRESS_MS);
				},
				{ passive: true }
			);

			// The finger that opened the sheet lifts over it: no click on what it now covers.
			document.addEventListener('touchend', (event) => {
				clearTimeout(longPressTimer);
				touching = false;
				if (isLongPress && event.cancelable) event.preventDefault();
			});
			for (const type of ['touchmove', 'touchcancel']) {
				document.addEventListener(
					type,
					() => {
						clearTimeout(longPressTimer);
						touching = false;
					},
					{ passive: true }
				);
			}
			// A long press is ours, not the browser's context menu.
			document.addEventListener('contextmenu', (event) => {
				if (touching && event.target.closest('.emoji-reaction-button-popup')) event.preventDefault();
			});

			document.addEventListener('keydown', (event) => this.handleKeydown(event));

			// A popup is placed against a button, in window coordinates: once the page
			// has moved under it, it points at nothing. Scrolling inside one is fine.
			window.addEventListener('resize', () => this.hideAllPopups());
			window.addEventListener(
				'scroll',
				(event) => {
					if (!inFloating(event.target)) this.hideAllPopups();
				},
				{ capture: true, passive: true }
			);
		}

		// --- Interaction handlers ---

		/**
		 * Escape closes; on a reaction, ↑/↓ moves into who reacted; in its tab bar
		 * the arrow keys, Home and End switch tabs; Tab out of the panel returns to
		 * the reaction it belongs to.
		 *
		 * @param {KeyboardEvent} event The key press.
		 */
		handleKeydown(event) {
			const target = event.target;
			const panel = this.panel;
			const inPanel = panel && !panel.sheet && this.names.node.contains(target);

			if (event.key === 'Escape') {
				if (inPanel) this.closePanelToButton();
				this.hideAllPopups();
				return;
			}

			if (target.matches('.emoji-reaction-button-popup') && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
				const context = this.contextOf(target);
				if (!context) return;
				event.preventDefault();
				clearTimeout(this.hoverTimer);
				if (this.panel?.wrapper !== context.wrapper) this.openPanel(context, target.dataset.emoji);
				if (!this.panel) return;
				this.panel.pinned = true;
				qs('[role="tab"][aria-selected="true"]', this.names.node)?.focus();
				return;
			}

			if (target.matches('.emoji-reaction-names [role="tab"]')) {
				const tabs = qsa('[role="tab"]', target.parentElement);
				const i = tabs.indexOf(target);
				const next = {
					ArrowRight: tabs[(i + 1) % tabs.length],
					ArrowLeft: tabs[(i - 1 + tabs.length) % tabs.length],
					Home: tabs[0],
					End: tabs[tabs.length - 1],
				}[event.key];
				if (next) {
					event.preventDefault();
					this.selectTab(next.dataset.tab);
					next.focus();
					return;
				}
			}

			if (inPanel && event.key === 'Tab') {
				const stops = qsa('button, [tabindex]', this.names.node).filter((el) => el.tabIndex >= 0);
				const leaving = event.shiftKey ? target === stops[0] : target === stops[stops.length - 1];
				if (leaving) {
					event.preventDefault();
					this.closePanelToButton();
				}
				return;
			}

			if (event.key !== 'Enter' && event.key !== ' ') {
				return;
			}
			if (target.matches('.emoji-reaction-button-addnew')) {
				event.preventDefault();
				this.handleAddNewToggle(target);
			} else if (target.matches('.emoji-reaction-button')) {
				event.preventDefault();
				this.handleEmojiClick(target);
			}
		}

		/** @param {HTMLElement} button The "add a reaction" button. */
		handleAddNewToggle(button) {
			const context = this.contextOf(button);
			if (!context) return;

			if (this.openPicker?.wrapper === context.wrapper) {
				this.hidePicker({ restoreFocus: true });
				return;
			}
			this.hidePicker();
			this.showPicker(context.containerId, context.wrapper);
			qs('.emoji-reaction-button', this.picker.node)?.focus();
		}

		/** @param {HTMLElement} button A reaction or an emoji of the picker. */
		handleEmojiClick(button) {
			const context = this.contextOf(button);
			const data = context && this.containers[context.containerId];
			if (!data) return;

			const emoji = button.dataset.emoji;
			const isVoted = button.classList.contains('voted');

			if (!isVoted && !data.can_react) {
				this.showNotification(emoji_reaction.no_permission);
				return;
			}
			if (isVoted && !data.can_react && !confirm(emoji_reaction.confirm_remove_no_perm)) return;
			if (emoji === '👎' && !isVoted && !confirm(emoji_reaction.thumbs_down_alert)) return;

			this.hidePicker();
			this.sendReaction(context.containerId, emoji, isVoted);
		}

		// --- AJAX ---

		/**
		 * @param {string}  containerId Id of the set of buttons.
		 * @param {string}  emoji       The reaction.
		 * @param {boolean} unlike      Whether it is taken back.
		 */
		async sendReaction(containerId, emoji, unlike) {
			const data = this.containers[containerId];

			try {
				const result = await postAjax({
					action: 'emoji_reaction_ajax_save_action',
					object_id: data.object_id,
					object_type: data.object_type,
					emoji,
					unlike,
					nonce: data.nonce,
				});

				if (result.success && result.data?.state_data) {
					this.containers[containerId] = result.data.state_data;
					this.renderContainer(containerId);

					const limitMessage = result.data.action_info?.limit_message;
					if (limitMessage) this.showNotification(limitMessage);

					this.fireCustomEvent(result.data);
				} else {
					console.error('emoji_reaction request failed', result);
					this.showNotification(result.data?.message || emoji_reaction.request_failed);
				}
			} catch (error) {
				console.error('emoji_reaction request failed', error);
				this.showNotification(emoji_reaction.request_failed);
			}
		}

		/** @param {Object} responseData Response of a saved reaction. */
		fireCustomEvent(responseData) {
			const info = responseData.action_info;
			if (info?.object_type === 'post') {
				window.dispatchEvent(
					new CustomEvent('emojiReactionChanged', {
						detail: {
							postId: info.object_id,
							objectType: info.object_type,
							emoji: info.emoji,
							state: info.state,
							userId: info.user_id,
						},
					})
				);
			}
		}

		// --- Notifications ---

		/** @param {HTMLElement} el The notification. */
		dismissNotification(el) {
			el.style.animation = 'slideOutToRight 0.3s ease-in';
			setTimeout(() => el.remove(), DISMISS_ANIMATION_MS);
		}

		/** @param {string} message What to say. */
		showNotification(message) {
			document.getElementById(NOTIFICATION_ID)?.remove();

			const notification = document.createElement('div');
			notification.id = NOTIFICATION_ID;
			notification.className = 'emoji-reaction-notification';
			notification.innerHTML = `
				<div class="emoji-reaction-notification-content">
					<span class="emoji-reaction-notification-icon" aria-disabled>ℹ️</span>
					<span class="emoji-reaction-notification-text">${esc(message)}</span>
					<button class="emoji-reaction-notification-close" type="button">&times;</button>
				</div>`;

			let toast = document.getElementById(TOAST_CONTAINER_ID);
			if (!toast) {
				toast = document.createElement('div');
				toast.id = TOAST_CONTAINER_ID;
				toast.setAttribute('aria-live', 'polite');
				document.body.appendChild(toast);
			}
			toast.appendChild(notification);

			qs('.emoji-reaction-notification-close', notification).addEventListener('click', () =>
				this.dismissNotification(notification)
			);

			setTimeout(() => {
				if (notification.isConnected) {
					this.dismissNotification(notification);
				}
			}, NOTIFICATION_TIMEOUT_MS);
		}
	}

	window.EmojiReaction = new EmojiReactionManager();

	const start = () => window.EmojiReaction.init();

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
	window.addEventListener('load', start);
	window.addEventListener('new-content', start);
})();
