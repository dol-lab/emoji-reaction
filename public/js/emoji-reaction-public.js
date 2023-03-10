(function ($) {
	'use strict';

	$(window).load(() => init_emoji_reaction());

	// trigger the window event 'new-content' to re-init click handlers.
	window.addEventListener('new-content', function (event) {
		init_emoji_reaction();
	})

	function init_emoji_reaction() {

		if (!$('.emoji-reaction-wrapper').length) {
			return; // nothing to do, if there is no elements on the page.
		}

		$('.ui.dropdown').dropdown({
			direction: 'upward',
		});
		$('.emoji-reaction-button-popup').popup({
			inline: true,
			addTouchEvents: false,
			variation: 'inverted',
			position: 'top right',
			onShow: function () {
				$(this).parent().parent().find('.ui.dropdown').dropdown('hide');
			}
		});

		// prevent classes selected and active of semantic ui
		$('.emoji-reaction-button').removeClass('selected active');

		// add the off event, so we don't trigger things twice on re-init.
		$(document).off('click.emoji').on('click.emoji', '.emoji-reaction-button', function (e) {
			e.preventDefault();
			// prevent classes selected and active of semantic ui
			$('.emoji-reaction-button').removeClass('selected active');

			var wrapper = $(this).closest('.emoji-reaction-wrapper');
			var emoji = $(this).data('emoji');
			var emoji_button;

			// if clicked button is shown as reaction, refer to itself
			// otherwise try to find related button in emoji-reactions-container
			if ($(this).parent().hasClass('emoji-reactions-container')) {
				emoji_button = $(this);
			} else {
				emoji_button = wrapper.find('.emoji-reactions-container > .emoji-reaction-button[data-emoji="' + emoji + '"]');
			}

			var object_id = wrapper.data('object-id');
			var object_type = wrapper.data('object-type');
			var nonce = wrapper.data('nonce');

			var current_count = parseInt(emoji_button.attr('data-count'));
			var current_totalcount = parseInt(wrapper.attr('data-totalcount'));

			var unlike = false;
			if (emoji_button.hasClass('voted')) {
				unlike = true;
			}

			var data = {
				action: 'emoji_reaction_ajax_save_action',
				object_id: object_id,
				object_type: object_type,
				emoji: emoji,
				unlike: unlike,
				nonce: nonce,
			};

			var save_action = $.ajax({
				url: emoji_reaction.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: data,
			});

			//pretend emoji state change in advance to avoid time offset
			//maybe better: loading animation?
			wrapper.find('.emoji-reaction-button[data-emoji="' + emoji + '"]').toggleClass('not-voted voted');
			if (unlike) {
				emoji_button.attr('data-count', current_count - 1);
				wrapper.attr('data-totalcount', current_totalcount - 1);
				if (current_count - 1 == 0) {
					emoji_button.hide();
				}
			} else {
				emoji_button.attr('data-count', current_count + 1);
				wrapper.attr('data-totalcount', current_totalcount + 1);
				if (current_count == 0) {
					emoji_button.show();
				}
			}

			save_action.done(function (result) {
				if (result.data.state == 'unliked') {
					detach_user_name(emoji_button, result.data.user_id)
				} else if (result.data.state == 'liked') {
					append_user_name(emoji_button, result.data.user_id, result.data.user_name)
				}
			});

			save_action.fail(function (jqXHR, textStatus, errorThrown) {
				console.log("emoji_reaction request failed: " + errorThrown);

				// reverse emoji state change if ajax call failed
				wrapper.find('.emoji-reaction-button[data-emoji="' + emoji + '"]').toggleClass('not-voted voted');
				emoji_button.attr('data-count', current_count);
				wrapper.attr('data-totalcount', current_totalcount);
				if (unlike) {
					if (current_count - 1 == 0) {
						emoji_button.show();
					}
				} else {
					if (current_count == 0) {
						emoji_button.hide();
					}
				}
			});

		});
	}

	function append_user_name(element, user_id, user_name) {
		var container = element.next('.emoji-reaction-popup-container').find('.emoji-reaction-usernames');
		container.append('<li data-user-id=' + user_id + '>' + user_name + '</li>');
	}

	function detach_user_name(element, user_id) {
		var container = element.next('.emoji-reaction-popup-container').find('.emoji-reaction-usernames');
		container.find('[data-user-id=' + user_id + ']').detach();
	}

})(jQuery);
