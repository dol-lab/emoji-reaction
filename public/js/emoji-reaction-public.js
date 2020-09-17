(function( $ ) {
	'use strict';

	$( window ).load(function() {

		if($('.emoji-reaction-wrapper').length > 0) {

			$(document).on('click', '.emoji-reaction-button', function(e) {
				e.preventDefault();

				console.log('like');

				var emoji_button = $(this);
				var wrapper = emoji_button.parent().parent().parent();

				var object_id = wrapper.data('object-id');
				var object_type = wrapper.data('object-type');
				var nonce = wrapper.data('nonce');

				var emoji = emoji_button.data('emoji');
				var current_count = parseInt(emoji_button.attr('data-count'));
				
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
				emoji_button.toggleClass('gray voted');
				if (unlike) {
					emoji_button.attr('data-count', current_count - 1);
					if (current_count - 1 == 0) {
						emoji_button.removeClass('show-count');
					}
				} else {
					emoji_button.attr('data-count', current_count + 1);
					if (!emoji_button.hasClass('show-count')) {
						emoji_button.addClass('show-count');
					}
				}

				save_action.done(function(result) {
					console.log(result.data);

					if (result.data.state == 'unliked') {
						detach_user_name(emoji_button, result.data.user_id )
					} else if (result.data.state == 'liked') {
						append_user_name(emoji_button, result.data.user_id, result.data.user_name)
					}
				});

				save_action.fail(function( jqXHR, textStatus, errorThrown ) {
					console.log( "emoji_reaction: Request failed: " + errorThrown );
					
					// reverse emoji state change if ajax call failed
					emoji_button.toggleClass('gray voted');
					if (unlike) {
						emoji_button.attr('data-count', current_count);
						if (!emoji_button.hasClass('show-count')) {
							emoji_button.addClass('show-count');
						}
					} else {
						emoji_button.attr('data-count', current_count);
						if (current_count == 0) {
							emoji_button.removeClass('show-count');
						}
					}
				});

			});

		}

	});

	function append_user_name(element, user_id, user_name) {
		var container = element.parent().find('.emoji-reaction-usernames');
		container.append('<li data-user-id=' + user_id + '>' + user_name + '</li>');
	}

	function detach_user_name(element, user_id) {
		var container = element.parent().find('.emoji-reaction-usernames');
		container.find('[data-user-id=' + user_id + ']').detach();
	}

})( jQuery );
