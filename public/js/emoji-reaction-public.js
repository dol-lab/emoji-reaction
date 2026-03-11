(($) => {
	'use strict';

	const LONG_PRESS_MS = 500;
	const RESIZE_DEBOUNCE_MS = 150;
	const NOTIFICATION_TIMEOUT_MS = 5000;
	const DISMISS_ANIMATION_MS = 300;
	const NOTIFICATION_ID = 'emoji-reaction-limit-notification';
	const TOAST_CONTAINER_ID = 'toast-container';

	// --- Helpers ---

	const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];
	const qs = (selector, root = document) => root.querySelector(selector);

	const emojiPopupId = (data, emoji) =>
		`emoji-popup-${data.object_type}-${data.object_id}-${emoji.codePointAt(0)}`;

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

	function debounce(fn, ms) {
		let timer;
		return (...args) => {
			clearTimeout(timer);
			timer = setTimeout(() => fn(...args), ms);
		};
	}

	function votedClass(emoji) {
		return emoji.user_voted ? 'voted' : 'not-voted';
	}

	// --- Manager ---

	class EmojiReactionManager {
		constructor() {
			this.containers = {};
			this.eventsSetup = false;
		}

		init() {
			if (window.emojiReactionData) {
				for (const id of Object.keys(window.emojiReactionData)) {
					this.initContainer(id);
				}
			}
			if (!this.eventsSetup) {
				this.setupEventListeners();
				this.eventsSetup = true;
			}
		}

		initContainer(containerId) {
			const data = window.emojiReactionData[containerId];
			if (!data) return;
			this.containers[containerId] = data;
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
				this.initializeUIComponents(el);

				if (data.currentOpenEmoji) {
					$(el)
						.find(`.emoji-reaction-button-popup[data-emoji="${data.currentOpenEmoji}"]`)
						.popup('show');
				}
			}
		}

		// --- HTML builders ---

		buildContainerHTML(data) {
			const mainButtons = data.emojis
				.filter((e) => e.count > 0)
				.map((e) => this.buildEmojiButton(e, data))
				.join('');

			let addNewContainer = '';
			if (data.current_user_id > 0 && data.can_react) {
				const dropdownItems = data.emojis
					.map((e) => this.buildDropdownItem(e))
					.join('');

				const popupId = `emoji-addnew-popup-${data.object_type}-${data.object_id}`;
				addNewContainer = `
					<div class="emoji-reaction-button-addnew-container">
						<button
							class="emoji-reaction-button-addnew ui icon center"
							type="button"
							aria-label="Add new reaction"
							aria-expanded="false"
							aria-haspopup="true"
							aria-controls="${popupId}"
							tabindex="0"
						>
							<i class="icon-thumpup-plus"></i>
						</button>
						<div class="ui popup addnew-popup" id="${popupId}" role="menu" aria-label="Choose reaction">
							<div class="item-container" role="group">${dropdownItems}</div>
						</div>
					</div>`;
			}

			return `
				<div class="emoji-reaction-container"
					data-object-id="${data.object_id}"
					data-object-type="${data.object_type}"
					data-nonce="${data.nonce}"
					data-totalcount="${data.total_count}"
				>${mainButtons}</div>
				${addNewContainer}`;
		}

		buildUserListHTML(emoji, data) {
			if (data.usernames_loading || (emoji.count > 0 && !emoji.user_names?.length)) {
				return {
					userList: `<li class="loading">${emoji_reaction.loading}</li>`,
					moreUsers: '',
				};
			}

			if (!emoji.user_names?.length) {
				return { userList: '', moreUsers: '' };
			}

			const userList = emoji.user_names
				.map((u) => `<li data-user-id="${u.id}">${u.name}</li>`)
				.join('');

			const moreCount = emoji.total_users - data.max_usernames;
			const moreUsers = moreCount > 0 ? `<p>And ${moreCount} more ...</p>` : '';

			return { userList, moreUsers };
		}

		buildEmojiButton(emoji, data) {
			const { userList, moreUsers } = this.buildUserListHTML(emoji, data);
			const popupId = emojiPopupId(data, emoji.emoji);

			let buttonLabel = `${emoji.name} (${emoji.count} reaction${emoji.count !== 1 ? 's' : ''})`;
			if (emoji.user_voted) buttonLabel += ' - You reacted with this';

			return `
				<button class="emoji-reaction-button emoji-reaction-button-popup ${votedClass(emoji)}"
					data-emoji="${emoji.emoji}"
					data-count="${emoji.count}"
					name="${emoji.name}"
					type="button"
					aria-label="${buttonLabel}"
					aria-describedby="${popupId}"
					aria-pressed="${emoji.user_voted ? 'true' : 'false'}"
					tabindex="0"
				></button>
				<div class="ui popup emoji-reaction-popup-container" id="${popupId}" role="tooltip" aria-hidden="true">
					<ul class="emoji-reaction-usernames" role="list">${userList}</ul>
					${moreUsers}
				</div>`;
		}

		buildDropdownItem(emoji) {
			if (emoji.legacy) return '';

			let buttonLabel = `React with ${emoji.name}`;
			if (emoji.user_voted) buttonLabel += ' (currently selected)';

			return `<button class="item emoji-reaction-button ${votedClass(emoji)}"
				data-emoji="${emoji.emoji}"
				name="${emoji.name}"
				type="button"
				aria-label="${buttonLabel}"
				aria-pressed="${emoji.user_voted ? 'true' : 'false'}"
				tabindex="0"></button>`;
		}

		// --- UI components (Fomantic UI requires jQuery) ---

		initializeUIComponents(container) {
			const $container = $(container);
			const containerId = container.closest('.emoji-reaction-wrapper')?.id;

			$container.find('.emoji-reaction-button-popup').popup({
				inline: true,
				addTouchEvents: false,
				variation: 'inverted',
				position: 'top right',
				on: 'hover',
				hoverable: true,
				delay: { show: 200, hide: 100 },
				onShow: function () {
					$(this).parent().parent().find('.emoji-reaction-button-addnew').popup('hide');

					const data = window.EmojiReaction.containers[containerId];
					if (data) {
						data.currentOpenEmoji = $(this).data('emoji');
						if (!data.usernames_loaded && !data.usernames_loading) {
							window.EmojiReaction.fetchUsernames(containerId);
						}
					}
				},
				onHide: function () {
					const data = window.EmojiReaction.containers[containerId];
					if (data?.currentOpenEmoji === $(this).data('emoji')) {
						data.currentOpenEmoji = null;
					}
				},
			});

			const $addNewButton = $container.find('.emoji-reaction-button-addnew');
			if ($addNewButton.length) {
				$addNewButton.popup({
					popup: $container.find('.addnew-popup'),
					on: 'click',
					position: 'top right',
					addTouchEvents: true,
				});
			}

			for (const btn of qsa('.emoji-reaction-button', container)) {
				btn.classList.remove('selected', 'active');
			}
		}

		hideAllPopups() {
			$('.emoji-reaction-button-popup').popup('hide');
			$('.emoji-reaction-button-addnew').popup('hide');
		}

		// --- Event listeners ---

		setupEventListeners() {
			let longPressTimer;
			let isLongPress = false;

			$(document).on('click.emoji', '.emoji-reaction-wrapper .emoji-reaction-button', (e) => {
				if (isLongPress) {
					isLongPress = false;
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				$('.emoji-reaction-button-popup').popup('hide');
				this.handleEmojiClick(e.currentTarget);
			});

			$(document).on('touchstart.emoji', '.emoji-reaction-wrapper .emoji-reaction-button-popup', (e) => {
				const target = e.currentTarget;
				isLongPress = false;
				longPressTimer = setTimeout(() => {
					isLongPress = true;
					this.handleEmojiPopup(target);
				}, LONG_PRESS_MS);
			});

			$(document).on('touchend.emoji touchmove.emoji', '.emoji-reaction-wrapper .emoji-reaction-button-popup', () => {
				clearTimeout(longPressTimer);
			});

			$(document).on('keydown.emoji', '.emoji-reaction-wrapper .emoji-reaction-button, .emoji-reaction-button-addnew', (e) => {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					e.stopPropagation();
					if (e.currentTarget.classList.contains('emoji-reaction-button-addnew')) {
						this.handleAddNewToggle(e.currentTarget);
					} else {
						this.handleEmojiClick(e.currentTarget);
					}
				} else if (e.key === 'Escape') {
					this.hideAllPopups();
				}
			});

			$(document).on('click.emoji', (e) => {
				if (!e.target.closest('.emoji-reaction-wrapper, .ui.popup')) {
					this.hideAllPopups();
				}
			});

			window.addEventListener('resize', debounce(() => {
				for (const containerId of Object.keys(this.containers)) {
					this.renderContainer(containerId);
				}
			}, RESIZE_DEBOUNCE_MS));
		}

		// --- Interaction handlers ---

		handleEmojiPopup(button) {
			const wrapper = button.closest('.emoji-reaction-wrapper');
			const containerId = wrapper.id;
			const data = this.containers[containerId];

			$('.emoji-reaction-button-popup').popup('hide');
			$(wrapper).find('.emoji-reaction-button-addnew').popup('hide');
			button.classList.remove('selected', 'active');

			if (!button.classList.contains('emoji-reaction-button-popup')) return;

			if (data && !data.usernames_loaded && !data.usernames_loading) {
				this.fetchUsernames(containerId);
			} else {
				$(button).popup('show');
			}
		}

		handleAddNewToggle(button) {
			const $button = $(button);
			const popup = button.nextElementSibling;
			const isExpanded = button.getAttribute('aria-expanded') === 'true';

			if (isExpanded) {
				$button.popup('hide');
				button.setAttribute('aria-expanded', 'false');
				popup.setAttribute('aria-hidden', 'true');
			} else {
				$button.popup('show');
				button.setAttribute('aria-expanded', 'true');
				popup.setAttribute('aria-hidden', 'false');
				setTimeout(() => qs('.emoji-reaction-button', popup)?.focus(), 100);
			}
		}

		handleEmojiClick(button) {
			const wrapper = button.closest('.emoji-reaction-wrapper');
			const containerId = wrapper.id;
			const data = this.containers[containerId];
			if (!data) return;

			const emoji = button.dataset.emoji || $(button).data('emoji');
			const isVoted = button.classList.contains('voted');

			if (!isVoted && !data.can_react) {
				this.showNotification('Sorry, you do not have permission to react to this content.');
				return;
			}

			$(wrapper).find('.emoji-reaction-button-addnew').popup('hide');
			button.classList.remove('selected', 'active');

			if (isVoted && !data.can_react && !confirm(emoji_reaction.confirm_remove_no_perm)) return;
			if (emoji === '👎' && !isVoted && !confirm(emoji_reaction.thumbs_down_alert)) return;

			this.sendReaction(containerId, emoji, isVoted);
		}

		// --- AJAX ---

		async fetchUsernames(containerId) {
			const data = this.containers[containerId];
			data.usernames_loading = true;

			for (const el of this.getContainerElements(containerId)) {
				for (const ul of qsa('.emoji-reaction-usernames', el)) {
					if (!ul.querySelector('li') || !ul.querySelector('.loading')) {
						ul.innerHTML = `<li class="loading">${emoji_reaction.loading}</li>`;
					}
				}
			}

			try {
				const result = await postAjax({
					action: 'emoji_reaction_ajax_get_usernames',
					object_id: data.object_id,
					object_type: data.object_type,
					nonce: data.nonce,
				});

				data.usernames_loading = false;
				if (result.success && result.data?.state_data) {
					const newState = result.data.state_data;
					newState.usernames_loaded = true;
					newState.currentOpenEmoji = data.currentOpenEmoji;
					this.containers[containerId] = newState;
					this.updatePopupsContent(containerId);
				}
			} catch (error) {
				console.error('fetchUsernames failed', error);
				data.usernames_loading = false;
				for (const el of this.getContainerElements(containerId)) {
					for (const li of qsa('.emoji-reaction-usernames .loading', el)) {
						li.remove();
					}
				}
			}
		}

		updatePopupsContent(containerId) {
			const data = this.containers[containerId];
			if (!data) return;

			for (const container of this.getContainerElements(containerId)) {
				for (const emoji of data.emojis) {
					const popup = qs(`#${emojiPopupId(data, emoji.emoji)}`, container);
					if (!popup) continue;

					const { userList, moreUsers } = this.buildUserListHTML(emoji, data);
					qs('.emoji-reaction-usernames', popup).innerHTML = userList;
					qs('p', popup)?.remove();
					if (moreUsers) {
						popup.insertAdjacentHTML('beforeend', moreUsers);
					}

					if (popup.classList.contains('visible')) {
						const $button = $(container).find(`.emoji-reaction-button-popup[data-emoji="${emoji.emoji}"]`);
						if ($button.length) $button.popup('refresh');
					}
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
					this.showNotification(result.data?.message || 'Unable to update reaction. Please try again.');
				}
			} catch (error) {
				console.error('emoji_reaction request failed', error);
				this.showNotification('Unable to update reaction. Please try again.');
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
					<span class="emoji-reaction-notification-text">${message}</span>
					<button class="emoji-reaction-notification-close">&times;</button>
				</div>`;

			let toast = document.getElementById(TOAST_CONTAINER_ID);
			if (!toast) {
				toast = document.createElement('div');
				toast.id = TOAST_CONTAINER_ID;
				toast.setAttribute('aria-live', 'polite');
				document.body.appendChild(toast);
			}
			toast.appendChild(notification);

			qs('.emoji-reaction-notification-close', notification)
				.addEventListener('click', () => this.dismissNotification(notification));

			setTimeout(() => {
				if (notification.isConnected) {
					this.dismissNotification(notification);
				}
			}, NOTIFICATION_TIMEOUT_MS);
		}
	}

	window.EmojiReaction = new EmojiReactionManager();

	$(() => window.EmojiReaction.init());
	$(window).on('load', () => window.EmojiReaction.init());
	window.addEventListener('new-content', () => window.EmojiReaction.init());
})(jQuery);
