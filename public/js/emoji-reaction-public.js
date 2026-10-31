/**
 * Emoji reactions: the buttons, who reacted, and the picker.
 *
 * Plain JavaScript, no library. The two popups (the list of people behind a
 * reaction, and the picker of the "add a reaction" button) are single nodes in
 * the top layer, opened with the Popover API and placed by hand:
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
	const HOVER_HIDE_MS = 100;
	const NOTIFICATION_TIMEOUT_MS = 5000;
	const DISMISS_ANIMATION_MS = 300;
	const NOTIFICATION_ID = 'emoji-reaction-limit-notification';
	const TOAST_CONTAINER_ID = 'toast-container';
	const NAMES_POPUP_ID = 'emoji-reaction-names-popup';
	const PICKER_POPUP_ID = 'emoji-reaction-picker-popup';

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
			user_names: [],
			total_users: counts[emoji] ?? 0,
		});
		const listed = state.emojis.map(([emoji, name]) => entry(emoji, name));
		const legacy = Object.keys(counts)
			.filter((emoji) => !state.emojis.some(([listedEmoji]) => listedEmoji === emoji))
			.map((emoji) => ({ ...entry(emoji, emoji), legacy: true }));
		const emojis = [...listed, ...legacy];

		return { ...state, emojis, total_count: emojis.reduce((sum, emoji) => sum + emoji.count, 0) };
	}

	function votedClass(emoji) {
		return emoji.user_voted ? 'voted' : 'not-voted';
	}

	// --- Popups ---

	/**
	 * A popup in the top layer, made once and filled in whenever it opens.
	 *
	 * `manual` popovers, both of them: the picker is opened by a click that the
	 * browser would otherwise take as the click that dismisses it, and the list
	 * of names follows the pointer, which is not a dismissal either. What closes
	 * them is in the handlers below.
	 */
	class Popup {
		constructor(id, className, attrs = {}) {
			this.node = document.createElement('div');
			this.node.id = id;
			this.node.className = className;
			this.anchor = null;

			if (hasPopover) {
				this.node.setAttribute('popover', 'manual');
			}
			for (const [name, value] of Object.entries(attrs)) {
				this.node.setAttribute(name, value);
			}

			/*
			 * "I am in the top layer, do not hide me." A react-aria modal marks
			 * every other child of `<body>` `inert` while it is open, and watches
			 * for new ones, so a popup opened over one would be painted and then
			 * take no clicks at all. This is the attribute its `ariaHideOutside()`
			 * checks for, the same way it exempts its own overlays.
			 */
			this.node.dataset.reactAriaTopLayer = 'true';
			document.body.append(this.node);
		}

		get isOpen() {
			return this.node.classList.contains('is-open');
		}

		/**
		 * Shows the popup with the given markup, over (or under) an anchor.
		 *
		 * @param {HTMLElement} anchor The button the popup belongs to.
		 * @param {string}      html   Content of the popup.
		 */
		show(anchor, html) {
			if (!hasPopover || !anchor?.isConnected) {
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

		/** Fills an open popup again, keeping it where it is. */
		update(html) {
			if (!this.isOpen) {
				return;
			}
			this.node.innerHTML = html;
			this.position();
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

			/** Which reaction the list of names is open for, if any. */
			this.openNames = null; // { containerId, emoji, wrapper }
			/** Which set of buttons the picker is open for, if any. */
			this.openPicker = null; // { containerId, wrapper }

			this.names = null;
			this.picker = null;

			this.hoverTimer = null;
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
			if (!hasPopover) {
				return;
			}
			this.names = new Popup(NAMES_POPUP_ID, 'emoji-reaction-popup emoji-reaction-names inverted', {
				role: 'tooltip',
			});
			this.picker = new Popup(PICKER_POPUP_ID, 'emoji-reaction-popup emoji-reaction-picker', {
				role: 'menu',
				'aria-label': emoji_reaction.choose_reaction,
			});
		}

		initContainer(containerId) {
			const data = window.emojiReactionData[containerId];
			if (!data) return;
			this.containers[containerId] = expandState(data);
			this.renderContainer(containerId);
		}

		getContainerElements(containerId) {
			return qsa(`[id="${containerId}"]`);
		}

		renderContainer(containerId) {
			const data = this.containers[containerId];
			const elements = this.getContainerElements(containerId);
			if (!elements.length || !data) return;

			for (const el of elements) {
				el.innerHTML = this.buildContainerHTML(data);
			}

			// The buttons the open popups hang on are gone; find the new ones. The
			// wrapper they are in is still the same node, and it is the one of the
			// copies of this container the reader is actually looking at.
			if (this.openNames?.containerId === containerId) {
				this.showNames(containerId, this.openNames.emoji, this.openNames.wrapper);
			}
			if (this.openPicker?.containerId === containerId) {
				this.showPicker(containerId, this.openPicker.wrapper);
			}
		}

		// --- HTML builders ---

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

		/** The people behind one reaction, or a placeholder while they are fetched. */
		buildNamesHTML(containerId, emojiChar) {
			const data = this.containers[containerId];
			const emoji = data?.emojis.find((e) => e.emoji === emojiChar);
			if (!emoji) {
				return '';
			}

			// Until they are here. Not "until there are any": a reaction of somebody
			// who has since been deleted has a count and no name to go with it, and
			// waiting for that name is waiting forever.
			if (data.usernames_loading || (!data.usernames_loaded && emoji.count > 0)) {
				return `<ul class="emoji-reaction-usernames" role="list"><li class="loading">${esc(
					emoji_reaction.loading
				)}</li></ul>`;
			}

			const names = (emoji.user_names ?? [])
				.map((user) => `<li data-user-id="${esc(user.id)}">${esc(user.name)}</li>`)
				.join('');

			if (!names) {
				return ''; // Nobody left to name: no popup either.
			}

			const moreCount = emoji.total_users - data.max_usernames;
			const more = moreCount > 0 ? `<p>${esc(sprintf(emoji_reaction.and_more, moreCount))}</p>` : '';

			return `<ul class="emoji-reaction-usernames" role="list">${names}</ul>${more}`;
		}

		// --- Opening and closing ---

		/**
		 * Opens the list of people behind a reaction, fetching them the first time.
		 *
		 * @param {string}      containerId Id of the set of buttons.
		 * @param {string}      emojiChar   The reaction.
		 * @param {HTMLElement} wrapper     Which copy of the container the reaction is in.
		 */
		showNames(containerId, emojiChar, wrapper) {
			const data = this.containers[containerId];
			const anchor = qs(`.emoji-reaction-button-popup[data-emoji="${CSS.escape(emojiChar)}"]`, wrapper);
			if (!data || !anchor || !this.names) {
				this.hideNames();
				return;
			}

			const html = this.buildNamesHTML(containerId, emojiChar);
			if (!html) {
				this.hideNames();
				return;
			}

			this.openNames = { containerId, emoji: emojiChar, wrapper };
			this.names.show(anchor, html);
			anchor.setAttribute('aria-describedby', NAMES_POPUP_ID);

			if (!data.usernames_loaded && !data.usernames_loading) {
				this.fetchUsernames(containerId);
			}
		}

		/** The open list of names again, from the state as it is now. */
		refreshNames(containerId) {
			if (this.openNames?.containerId !== containerId) {
				return;
			}
			const html = this.buildNamesHTML(containerId, this.openNames.emoji);
			if (html) {
				this.names.update(html);
			} else {
				this.hideNames();
			}
		}

		hideNames() {
			if (!this.openNames) {
				return;
			}
			this.names.anchor?.removeAttribute('aria-describedby');
			this.openNames = null;
			this.names.hide();
		}

		showPicker(containerId, wrapper) {
			const data = this.containers[containerId];
			const anchor = qs('.emoji-reaction-button-addnew', wrapper);
			if (!data || !anchor || !this.picker) {
				this.hidePicker();
				return;
			}

			this.hideNames();
			this.openPicker = { containerId, wrapper };
			this.picker.show(anchor, this.buildPickerHTML(data));
			anchor.setAttribute('aria-expanded', 'true');
		}

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

		hideAllPopups() {
			this.hideNames();
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

			document.addEventListener('click', (event) => {
				const button = event.target.closest('.emoji-reaction-button');
				const addNew = event.target.closest('.emoji-reaction-button-addnew');

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

				if (addNew) {
					event.preventDefault();
					event.stopPropagation();
					this.handleAddNewToggle(addNew);
					return;
				}

				if (!event.target.closest('.emoji-reaction-popup')) {
					this.hideAllPopups();
				}
			});

			// Hover over a reaction: who reacted. The pointer may travel into the
			// popup itself, which is why it is not closed until it has left both.
			document.addEventListener('mouseover', (event) => {
				const button = event.target.closest('.emoji-reaction-button-popup');
				clearTimeout(this.hoverTimer);

				if (button) {
					const context = this.contextOf(button);
					const emoji = button.dataset.emoji;
					if (!context || (this.openNames?.wrapper === context.wrapper && this.openNames.emoji === emoji)) {
						return;
					}
					this.hoverTimer = setTimeout(
						() => this.showNames(context.containerId, emoji, context.wrapper),
						HOVER_SHOW_MS
					);
					return;
				}

				if (!event.target.closest(`#${NAMES_POPUP_ID}`) && this.openNames) {
					this.hoverTimer = setTimeout(() => this.hideNames(), HOVER_HIDE_MS);
				}
			});

			// Touch: a long press asks who reacted, a tap reacts.
			document.addEventListener(
				'touchstart',
				(event) => {
					const button = event.target.closest('.emoji-reaction-button-popup');
					if (!button) return;

					isLongPress = false;
					longPressTimer = setTimeout(() => {
						isLongPress = true;
						const context = this.contextOf(button);
						if (context) {
							this.hidePicker();
							this.showNames(context.containerId, button.dataset.emoji, context.wrapper);
						}
					}, LONG_PRESS_MS);
				},
				{ passive: true }
			);

			for (const type of ['touchend', 'touchmove', 'touchcancel']) {
				document.addEventListener(type, () => clearTimeout(longPressTimer), { passive: true });
			}

			document.addEventListener('keydown', (event) => {
				if (event.key === 'Escape') {
					this.hideAllPopups();
					return;
				}
				if (event.key !== 'Enter' && event.key !== ' ') {
					return;
				}
				const target = event.target;
				if (target.matches('.emoji-reaction-button-addnew')) {
					event.preventDefault();
					this.handleAddNewToggle(target);
				} else if (target.matches('.emoji-reaction-button')) {
					event.preventDefault();
					this.handleEmojiClick(target);
				}
			});

			// A popup is placed against a button, in window coordinates: once the page
			// has moved under it, it points at nothing.
			window.addEventListener('resize', () => this.hideAllPopups());
			window.addEventListener('scroll', () => this.hideAllPopups(), { capture: true, passive: true });
		}

		// --- Interaction handlers ---

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
			this.hideNames();
			this.sendReaction(context.containerId, emoji, isVoted);
		}

		// --- AJAX ---

		async fetchUsernames(containerId) {
			const data = this.containers[containerId];
			data.usernames_loading = true;

			try {
				const result = await postAjax({
					action: 'emoji_reaction_ajax_get_usernames',
					object_id: data.object_id,
					object_type: data.object_type,
					nonce: data.nonce,
				});

				data.usernames_loading = false;
				if (!result.success || !result.data?.state_data) {
					if (this.openNames?.containerId === containerId) {
						this.hideNames();
					}
					return;
				}

				const newState = result.data.state_data;
				newState.usernames_loaded = true;
				this.containers[containerId] = newState;

				this.refreshNames(containerId);
			} catch (error) {
				console.error('fetchUsernames failed', error);
				data.usernames_loading = false;
				if (this.openNames?.containerId === containerId) {
					this.hideNames(); // Rather than a spinner that never stops.
				}
			}
		}

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

		dismissNotification(el) {
			el.style.animation = 'slideOutToRight 0.3s ease-in';
			setTimeout(() => el.remove(), DISMISS_ANIMATION_MS);
		}

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
