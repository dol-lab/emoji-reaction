(function( $ ) {
	'use strict';

	$( window ).load(function() {

		if($('.emoji-reaction-wrapper').length > 0) {

			$(document).on('click', '.emoji-reaction-button', function(e) {
				e.preventDefault();

				console.log('like');

				var emoji_button = $(this);
				var parent = emoji_button.parent();

				var object_id = parent.data('object-id');
				var object_type = parent.data('object-type');
				var nonce = parent.data('nonce');

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
					console.log(result.data.state);
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

})( jQuery );
