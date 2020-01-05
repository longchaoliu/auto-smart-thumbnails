jQuery( function( $ ) {

	"use strict";

	var $body = $( 'body' ),
		$button_get_log = $( '#ast-get-log' ),
		$button_cleanup = $( '#ast-cleanup' ),
		$buttons = $( '#ast-buttons' ),
		$message = $( '#ast-status-message' ),
		$log_desc = $( 'ast-log-desc' ),
		$log = $( '#ast-log' );

	$button_get_log.on( 'click', function( e ) {

		e.preventDefault();
		$button_get_log.hide();
		// it doesn't hide, why?		
		$log_desc.hide();

		ajax_request_log();

	});

	$button_cleanup.on( 'click', function( e ) {

		//e.preventDefault();

		$buttons.hide();

		$message
    		.html( auto_smart_thumb.l10n.cleanup_progress )
			.show();

		var page = parseInt( $button_cleanup.attr( 'data-page' ), 10 );

		ajax_request( page );

	});

	$body.on( 'click', '.js-ast-show-log', function( e ) {

		e.preventDefault();

		$log.stop().slideToggle();
	});

	function ajax_request( paged, removed ) {

		paged = 'undefined' == typeof paged ? 1 : parseInt( paged, 10 );
		removed = 'undefined' == typeof removed ? 0 : parseInt( removed, 10 );

		$.post(
			ajaxurl,
			{
				action: 'ast_Remove_Image_Sizes',
				nonce: auto_smart_thumb.nonce,
				paged: paged,
				removed: removed,
      		},
			function( response ) {

				if ( true !== response.success ) {

					// Looks like something went wrong
					$message
            			.html( auto_smart_thumb.l10n.something_wrong )
						.show();

					return;
				}

				if ( true === response.finished ) {

					// Cleanup has finished
					var message = 0 === parseInt( response.removed, 10 ) ? auto_smart_thumb.l10n.nothing_to_remove : auto_smart_thumb.l10n.process_finished.replace( '%d', '<a href="#" class="js-ast-show-log">' + response.removed + '</a>' );

					$message
	            		.html( message );

					if ( 0 !== parseInt( response.removed, 10 ) && response.removed_log.length ) {

						var logHtml = '<pre>';

						$.each( response.removed_log, function( i, file ) {
							logHtml += file + '\n';
						});

						// also dump the logged debug info
						if ( response.debug_log.length > 1 ) {
							logHtml += '\nHere is the loggded debug info.\n';
							$.each( response.debug_log, function( i, info ) {
								logHtml += info + '\n';
							});
						}

						logHtml += '</pre>';

						$log.html( logHtml )

					} else {
						if ( response.debug_log.length > 1 ) {
							// they don't show, why?
							$button_get_log.show();
							$log_desc.show();
						}
					}

					return;
				}

				// Cleanup still in progress
				var completed = ( response.paged * 10 > response.found ) ? response.found : response.paged * 10;

				$message
          			.html( auto_smart_thumb.l10n.cleanup_progress + ' ' + completed + ' / ' + response.found );

				ajax_request( ++response.paged, response.removed );
			},
			'json'
		);
	}

	function ajax_request_log() {

		$.post(
			ajaxurl,
			{
				action: 'ast_Get_Debug_Log',
      		},
			function( response ) {

				if ( response.debug_log.length ) {

					var logHtml = '<pre>';

					$.each( response.debug_log, function( i, info ) {
						logHtml += info + '\n';
					});
					
					logHtml += '</pre>';

					$log.html( logHtml );
					$log.stop().slideToggle();
				}
				return;
			},
			'json'
		);
	}
});
