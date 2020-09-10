(function( $ ) {
	'use strict';

	$( window ).load(function() {

		if($('.emoji-reactions-wrapper').length > 0) {

			$(document).on('click', '.emoji-reactions-button', function(e) {
				e.preventDefault();

				console.log('like');

				var emoji_button = $(this);
				var parent = emoji_button.parent();

				var object_id = parent.attr('data-object-id');
				var object_type = parent.attr('data-object-type');
				var emoji = emoji_button.attr('data-emoji');

				var unlike = false;
				if (emoji_button.hasClass('voted')) {
					unlike = true;
				}

				var data = {
					action: 'emoji_reactions_ajax_save_action',
					object_id: object_id,
					object_type: object_type,
					emoji: emoji,
					unlike: unlike,
				};

				var save_action = $.ajax({
					url: emoji_reactions.ajax_url,
					type: 'POST',
					dataType: 'text',
					data: data,
				});

				save_action.done(function(result) {
					emoji_button.toggleClass('gray voted');
					console.log(result);
				});
				save_action.fail(function( jqXHR, textStatus ) {
					console.log( "emoji_reactions: Request failed: " + textStatus );
				});
			});

		}

	});

})( jQuery );
