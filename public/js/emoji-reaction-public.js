(function ($) {
	'use strict';

	// Global state management
	window.EmojiReaction = {
		containers: {},
		eventsSetup: false,

		// Initialize all containers on page load
		init: function () {
			// Initialize containers from inline data
			if (window.emojiReactionData) {
				for (var containerId in window.emojiReactionData) {
					this.initContainer(containerId);
				}
			}

			// Set up global event listeners only once
			if (!this.eventsSetup) {
				this.setupEventListeners();
				this.eventsSetup = true;
			}
		},

		// Initialize a specific container
		initContainer: function (containerId) {
			var data = window.emojiReactionData[containerId];
			if (!data) return;

			this.containers[containerId] = data;
			this.renderContainer(containerId);
		},

		// Render a container with current state
		renderContainer: function (containerId) {
			var data = this.containers[containerId];
			// Use attribute selector to handle potential multiple instances with same ID
			var $containers = $('[id="' + containerId + '"]');
			if (!$containers.length || !data) return;

			// Process each container instance
			var self = this;
			$containers.each(function () {
				var $container = $(this);
				// Clear container
				$container.empty();

				// Build HTML
				var html = self.buildContainerHTML(data);
				$container.html(html);

				// Initialize UI components
				self.initializeUIComponents($container);
			});
		},

		// Build complete HTML for container
		buildContainerHTML: function (data) {
			// Build main emoji buttons for all emojis with count > 0
			var mainButtons = '';
			data.emojis.forEach(function (emoji) {
				if (emoji.count > 0) {
					mainButtons += window.EmojiReaction.buildEmojiButton(emoji, data);
				}
			});

			// Build dropdown items (all emojis)
			var dropdownItems = '';
			data.emojis.forEach(function (emoji) {
				dropdownItems += window.EmojiReaction.buildDropdownItem(emoji, data);
			});

			// Build add new button container (only show if user is logged in)
			var addNewContainer = '';
			if (data.current_user_id && data.current_user_id > 0) {
				var popupId = 'emoji-addnew-popup-' + data.object_type + '-' + data.object_id;
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
						<div
							class="ui popup addnew-popup"
							id="${popupId}"
							role="menu"
							aria-label="Choose reaction"
						>
							<div class="item-container" role="group">${dropdownItems}</div>
						</div>
					</div>
					`;
			}

			return `
				<div class="emoji-reaction-container"
					data-object-id="${data.object_id}"
					data-object-type="${data.object_type}"
					data-nonce="${data.nonce}"
					data-totalcount="${data.total_count}"
				>
					${mainButtons}
				</div>
				${addNewContainer}
				`;
		},

		// Build individual emoji button
		buildEmojiButton: function (emoji, data) {
			var votedClass = emoji.user_voted ? 'voted' : 'not-voted';
			var userList = '';
			var moreUsers = '';

			emoji.user_names.forEach(function (user) {
				userList += `<li data-user-id="${user.id}">${user.name}</li>`;
			});

			if (emoji.total_users > data.max_usernames) {
				var moreCount = emoji.total_users - data.max_usernames;
				moreUsers = `<p>And ${moreCount} more ...</p>`;
			}

			// Create accessible button label
			var buttonLabel = emoji.name + ' (' + emoji.count + ' reaction' + (emoji.count !== 1 ? 's' : '') + ')';
			if (emoji.user_voted) {
				buttonLabel += ' - You reacted with this';
			}

			var popupId = 'emoji-popup-' + data.object_type + '-' + data.object_id + '-' + emoji.emoji.codePointAt(0);

			return `
				<button class="emoji-reaction-button emoji-reaction-button-popup ${votedClass}"
					data-emoji="${emoji.emoji}"
					data-count="${emoji.count}"
					name="${emoji.name}"
					type="button"
					aria-label="${buttonLabel}"
					aria-describedby="${popupId}"
					aria-pressed="${emoji.user_voted ? 'true' : 'false'}"
					tabindex="0"
				></button>
				<div class="ui popup emoji-reaction-popup-container"
					id="${popupId}"
					role="tooltip"
					aria-hidden="true"
				>
					<ul class="emoji-reaction-usernames" role="list">${userList}</ul>
					${moreUsers}
				</div>
			`;
		},

		// Build dropdown item
		buildDropdownItem: function (emoji, data) {
			var votedClass = emoji.user_voted ? 'voted' : 'not-voted';
			var buttonLabel = 'React with ' + emoji.name;
			if (emoji.user_voted) {
				buttonLabel += ' (currently selected)';
			}
			return `<button class="item emoji-reaction-button ${votedClass}"
				data-emoji="${emoji.emoji}"
				name="${emoji.name}"
				type="button"
				aria-label="${buttonLabel}"
				aria-pressed="${emoji.user_voted ? 'true' : 'false'}"
				tabindex="0"></button>
			`;
		},

		// Initialize UI components (popups)
		initializeUIComponents: function ($container) {
			$container.find('.emoji-reaction-button-popup').popup({
				inline: true,
				addTouchEvents: true, // Enable touch events for mobile
				variation: 'inverted',
				position: 'top right',
				on: 'manual', // Set to manual so we can control when it shows
				onShow: function () {
					$(this).parent().parent().find('.emoji-reaction-button-addnew').popup('hide');
				}
			});

			// Initialize add new button popup
			var $addNewButton = $container.find('.emoji-reaction-button-addnew');
			if ($addNewButton.length) {
				$addNewButton.popup({
					popup: $container.find('.addnew-popup'),
					on: 'click',
					position: 'top right',
					addTouchEvents: true // Enable touch events for mobile
				});
			}

			// Remove semantic UI classes
			$container.find('.emoji-reaction-button').removeClass('selected active');
		},

		// Set up global event listeners
		setupEventListeners: function () {
			var self = this;

			// Handle emoji button clicks in .emoji-reaction-wrapper
			$(document).on('click.emoji', '.emoji-reaction-wrapper .emoji-reaction-button', function (e) {
				e.preventDefault();
				e.stopPropagation();
				self.handleEmojiPopup($(this));
			});

			// Handle emoji button clicks in add-new popup (these should still vote)
			$(document).on('click.emoji', '.emoji-reaction-button-addnew-container .emoji-reaction-button', function (e) {
				e.preventDefault();
				self.handleEmojiClick($(this));
			});

			// Handle keyboard navigation
			$(document).on('keydown.emoji', '.emoji-reaction-wrapper .emoji-reaction-button, .emoji-reaction-button-addnew', function (e) {
				var $button = $(this);

				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					e.stopPropagation();

					if ($button.hasClass('emoji-reaction-button-addnew')) {
						self.handleAddNewToggle($button);
					} else if ($button.hasClass('emoji-reaction-button-popup')) {
						self.handleEmojiPopup($button);
					}
				} else if (e.key === 'Escape') {
					// Close popups on Escape
					$('.emoji-reaction-button-popup').popup('hide');
					$('.emoji-reaction-button-addnew').popup('hide');
				}
			});

			// Handle keyboard navigation in dropdown menus
			$(document).on('keydown.emoji', '.emoji-reaction-button-addnew-container .emoji-reaction-button', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					self.handleEmojiClick($(this));
				}
			});

			// Close popups when clicking outside
			$(document).on('click.emoji', function (e) {
				if (!$(e.target).closest('.emoji-reaction-wrapper, .ui.popup').length) {
					$('.emoji-reaction-button-popup').popup('hide');
					$('.emoji-reaction-button-addnew').popup('hide');
				}
			});

			// Handle window resize
			var resizeTimeout;
			$(window).resize(function () {
				clearTimeout(resizeTimeout);
				resizeTimeout = setTimeout(function () {
					self.handleResize();
				}, 150);
			});
		},

		// Handle emoji popup (show popup instead of voting)
		handleEmojiPopup: function ($button) {
			var $wrapper = $button.closest('.emoji-reaction-wrapper');

			// Close all existing popups first
			$('.emoji-reaction-button-popup').popup('hide');
			$wrapper.find('.emoji-reaction-button-addnew').popup('hide');
			$button.removeClass('selected active');

			// Show the popup for this emoji button
			if ($button.hasClass('emoji-reaction-button-popup')) {
				$button.popup('show');
			}
		},

		// Handle add new button toggle
		handleAddNewToggle: function ($button) {
			var $popup = $button.next('.addnew-popup');
			var isExpanded = $button.attr('aria-expanded') === 'true';

			if (isExpanded) {
				$button.popup('hide');
				$button.attr('aria-expanded', 'false');
				$popup.attr('aria-hidden', 'true');
			} else {
				$button.popup('show');
				$button.attr('aria-expanded', 'true');
				$popup.attr('aria-hidden', 'false');
				// Focus first emoji button in popup
				setTimeout(function () {
					$popup.find('.emoji-reaction-button').first().focus();
				}, 100);
			}
		},

		// Handle emoji button click
		handleEmojiClick: function ($button) {
			var $wrapper = $button.closest('.emoji-reaction-wrapper');
			var $container = $wrapper.find('.emoji-reaction-container');
			var containerId = $wrapper.attr('id');
			var data = this.containers[containerId];

			if (!data) return;

			var emoji = $button.data('emoji');
			var isVoted = $button.hasClass('voted');

			// Close popups
			$wrapper.find('.emoji-reaction-button-addnew').popup('hide');
			$button.removeClass('selected active');

			// Check thumbs down confirmation
			if (emoji === '👎' && !isVoted) {
				if (!confirm(emoji_reaction.thumbs_down_alert)) {
					return;
				}
			}

			// Send AJAX request
			this.sendReaction(containerId, emoji, isVoted);
		},

		// Send reaction AJAX request
		sendReaction: function (containerId, emoji, unlike) {
			var data = this.containers[containerId];
			var self = this;

			var ajaxData = {
				action: 'emoji_reaction_ajax_save_action',
				object_id: data.object_id,
				object_type: data.object_type,
				emoji: emoji,
				unlike: unlike,
				nonce: data.nonce,
			};

			$.ajax({
				url: emoji_reaction.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: ajaxData,
				success: function (response) {
					if (response.success && response.data.state_data) {
						// Update state and re-render
						self.containers[containerId] = response.data.state_data;
						self.renderContainer(containerId);

						// Announce to screen readers
						var actionInfo = response.data.action_info;
						if (actionInfo) {
							var message;
							if (actionInfo.state === 'liked') {
								message = 'Added ' + actionInfo.emoji + ' reaction';
							} else {
								message = 'Removed ' + actionInfo.emoji + ' reaction';
							}
							self.announceToScreenReader(message);
						}

						// Handle notifications
						if (response.data.action_info && response.data.action_info.limit_message) {
							self.showNotification(response.data.action_info.limit_message);
						}

						// Fire custom event for charts
						self.fireCustomEvent(response.data);
					}
				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.log("emoji_reaction request failed: " + errorThrown);
					self.announceToScreenReader("Failed to update reaction. Please try again.");
				}
			});
		},

		// Fire custom event for chart updates
		fireCustomEvent: function (responseData) {
			if (responseData.action_info && responseData.action_info.object_type === 'post') {
				var event = new CustomEvent('emojiReactionChanged', {
					detail: {
						postId: responseData.action_info.object_id,
						objectType: responseData.action_info.object_type,
						emoji: responseData.action_info.emoji,
						state: responseData.action_info.state,
						userId: responseData.action_info.user_id
					}
				});
				window.dispatchEvent(event);
			}
		},

		// Show notification
		showNotification: function (message) {
			var notificationId = 'emoji-reaction-limit-notification';
			var $existing = $('#' + notificationId);

			if ($existing.length) {
				$existing.remove();
			}

			var $notification = $(`
				<div id="${notificationId}" class="emoji-reaction-notification">
					<div class="emoji-reaction-notification-content">
						<span class="emoji-reaction-notification-icon">ℹ️</span>
						<span class="emoji-reaction-notification-text">${message}</span>
						<button class="emoji-reaction-notification-close">&times;</button>
					</div>
				</div>
			`);

			$('body').append($notification);

			$notification.find('.emoji-reaction-notification-close').on('click', function () {
				$notification.css('animation', 'slideOutToRight 0.3s ease-in');
				setTimeout(function () { $notification.remove(); }, 300);
			});

			setTimeout(function () {
				if ($notification.length && $notification.is(':visible')) {
					$notification.css('animation', 'slideOutToRight 0.3s ease-in');
					setTimeout(function () { $notification.remove(); }, 300);
				}
			}, 5000);
		},

		// Handle window resize
		handleResize: function () {
			for (var containerId in this.containers) {
				this.renderContainer(containerId);
			}
		},

		// Announce message to screen readers
		announceToScreenReader: function (message) {
			var $announcer = $('#emoji-reaction-sr-announcer');
			if (!$announcer.length) {
				$announcer = $('<div id="emoji-reaction-sr-announcer" aria-live="polite" aria-atomic="true" class="sr-only"></div>');
				$('body').append($announcer);

				// Add screen reader only styles
				if (!$('#emoji-reaction-sr-styles').length) {
					$('head').append(`
						<style id="emoji-reaction-sr-styles">
							.sr-only {
								position: absolute !important;
								width: 1px !important;
								height: 1px !important;
								padding: 0 !important;
								margin: -1px !important;
								overflow: hidden !important;
								clip: rect(0, 0, 0, 0) !important;
								white-space: nowrap !important;
								border: 0 !important;
							}
						</style>
					`);
				}
			}

			// Clear and set new message
			$announcer.empty();
			setTimeout(function () {
				$announcer.text(message);
			}, 100);
		}
	};

	// Initialize on DOM ready and window load
	$(document).ready(function () {
		window.EmojiReaction.init();
	});

	$(window).on('load', function () {
		window.EmojiReaction.init();
	});

	// Handle new content events
	window.addEventListener('new-content', function (event) {
		window.EmojiReaction.init();
	});

})(jQuery);
