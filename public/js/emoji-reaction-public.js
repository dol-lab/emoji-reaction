(function( $ ) {
	'use strict';

	$( window ).load(function() {

		if($('.emoji-reaction-wrapper').length > 0) {

			$(document).on('click', '.emoji-reaction-button', function(e) {
				e.preventDefault();

				console.log('like');

				var emoji_button = $(this);
				var parent = emoji_button.parent();

				var object_id = parent.attr('data-object-id');
				var object_type = parent.attr('data-object-type');
				var emoji = emoji_button.attr('data-emoji');
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
				};

				var save_action = $.ajax({
					url: emoji_reaction.ajax_url,
					type: 'POST',
					dataType: 'text',
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
					console.log(result);
				});
				save_action.fail(function( jqXHR, textStatus ) {
					console.log( "emoji_reaction: Request failed: " + textStatus );
					//if error: reverse emoji state change (see above)?
				});

				
			});

		}

	});

})( jQuery );
