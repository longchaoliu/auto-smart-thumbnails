<?php

require_once __DIR__ . '/trait-ast-resize-orig-image.php';

class ast_Remove_Image_Sizes {
	use ast_Resize_Orig_Image;

	// Will hold the only instance of our main plugin class
	private static $instance;

	// Instantiate the class and set up stuff
	public static function instantiate() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof ast_Remove_Image_Sizes ) ) {
			self::$instance = new ast_Remove_Image_Sizes();
		}
		return self::$instance;
	}

	public function __construct() {

		add_action( 'admin_menu', array( $this, 'add_tools_subpage' ) );
		add_action( 'wp_ajax_ast_Remove_Image_Sizes', array( $this, 'remove_image_sizes' ) );
		add_action( 'wp_ajax_ast_Get_Debug_Log', array( $this, 'get_debug_log' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Upon image upload, only generate default sizes
		// Filters image sizes automatically generated when uploading an image. Generate default sizes only.
		add_filter( 'intermediate_image_sizes_advanced', array( $this, 'remove_intermediate_sizes' ), 10, 1 );
	}

	// Returns image sizes that we don't want to remove
	public function get_ignored_image_sizes() {

		// default image sizes and names，e.g. [image-in-post] => Image in Post, [full] => Original size
		$ignored_sizes = apply_filters( 'image_size_names_choose', array() );

		// 'medium', 'large' excluded, i.e. they will be removed/cleaned up.
		$ignored_sizes = array_merge( array_keys( $ignored_sizes ), array( 'thumbnail' ) );

		return $ignored_sizes;
	}

	// exclude the sizes that default created, i.e. these sizes will be created by wp system
	public function remove_intermediate_sizes( $sizes ) {

		//return array_intersect_key( $sizes, array_flip( $this->get_ignored_image_sizes() ) );
		// return nothing, i.e so that the thumbnails generation come through our code
		return array();
	}

	public function add_tools_subpage() {
		add_submenu_page(
			'tools.php',
			__( 'Auto Smart Thumbnails', 'auto-smart-thumbnails' ),
			__( 'Auto Smart Thumbnails', 'auto-smart-thumbnails' ),
			'manage_options',
			'auto-smart-thumbnails',
			array( $this, 'tools_subpage_output' )
		);
		// settings for debug log
		add_options_page(
			__( 'Auto Smart Thumbnails', 'auto-smart-thumbnails' ),
			__( 'Auto Smart Thumbnails', 'auto-smart-thumbnails' ),
			'manage_options',
			'auto-smart-thumbnails',
			array( $this, 'settings_subpage_output' )
		);
	}

	// settings screen
	public function settings_subpage_output() {

		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		$debug_switch = get_option( 'ast_opt_debug_switch' );
		$debug_name = 'ast-debug-setting';

		if( ! empty( $_POST ) ) {
			if ( isset( $_POST[ $debug_name ] ) ) {
				$debug_input = 1;
			} else {
				$debug_input = 0;
			}
		} else {
			$debug_input = $debug_switch;
		}

		if ( $debug_switch != $debug_input ) {
			if ( ! $debug_switch ) {
				$debug_log = get_option( 'ast_opt_debug_log', array() );
				if ( empty ( $debug_log ) ) {
					// 1st time
					$debug_log = array();
					$debug_log[] = date( DATE_RFC2822 );
					update_option( 'ast_opt_debug_log', $debug_log );
				} 
			} else {
				// turned off, don't delete the log until it's obtained
			}
	
			$debug_switch = $debug_input;
			update_option( 'ast_opt_debug_switch', $debug_input );
		?>
			<div class="updated"><p><strong><?php _e( 'Settings saved.', 'auto-smart-thumbnails' ); ?></strong></p></div>
	<?php } ?>

		<div class="wrap">
			<h1><?php _e( 'Remove Unused Thumbnails', 'auto-smart-thumbnails' ); ?></h1>
			<p>
				<form method="post" action="">
					<input type="checkbox" name="<?php echo $debug_name; ?>" value="1"
					<?php if ( $debug_switch ) { ?> checked <?php } ?> >
					<?php _e( 'Log debug info for troubleshooting (in case you run into problems).' ); ?>
				
					<p class="submit">
					<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
					</p>
				</form>
			</p>
		</div>
		<?php
	}

	// the working page
	public function tools_subpage_output() {

		$cleanup_in_progress = get_option( 'ast_opt_cleanup_progress' );

		if ( false !== $cleanup_in_progress ) {
			$cleanup_in_progress = absint( $cleanup_in_progress );
		} else {
			$cleanup_in_progress = 1;
		}

		?>

		<div class="wrap">
			<h1><?php _e( 'Remove Unused Thumbnails', 'auto-smart-thumbnails' ); ?></h1>
			<p></p>
			<div id="ast-buttons">
				<button
					id="ast-cleanup"
					class="button button-primary"
					data-page="<?php echo esc_attr( $cleanup_in_progress ); ?>"
				><?php _e( 'Start cleanup', 'auto-smart-thumbnails' ); ?></button>

				<p class="description"><?php _e( 'Click the button to cleanup unused thumbnails.', 'auto-smart-thumbnails' ); ?></p>

				<div id="ast-log-button"
					<?php 
						$debug_switch = get_option( 'ast_opt_debug_switch' );
						$debug_log = get_option( 'ast_opt_debug_log', array() );
						if ( ! $debug_switch || count( $debug_log ) <= 1 ) { ?> 
							style="display: none;" 
						<?php } ?> >
					<p id="ast-log-desc"><?php _e( 'There is debug info logged. Do you want to get it?<br>Note: the log will be cleared after you retrieve it.' ); ?></p>
					<p><button id="ast-get-log" class="button">
					<?php _e( 'Get Debug Log', 'auto-smart-thumbnails' ); ?></button></p>
				</div>
			</div>

			<p id="ast-status-message"></p>
			<div id="ast-log"></div>
		</div>
		<?php
	}

	// cleans up extra image sizes when called via ajax
	public function remove_image_sizes( $__attachment_id ) {

		$paged = ! empty( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1;
		$removed = ! empty( $_POST['removed'] ) ? absint( $_POST['removed'] ) : 0;

		update_option( 'ast_opt_cleanup_progress', $paged );

		if ( 1 == $paged ) {
			// 1st entry, do the query
			if ( ! $__attachment_id ) {
				check_ajax_referer( 'ast-nonce', 'nonce' );
	
				$args = array(
					'fields'         => 'ids',
					//'paged'          => $paged,
					'nopaging'       => true,
					'post_mime_type' => 'image',
					'post_status'    => 'any',
					'post_type'      => 'attachment',
					'posts_per_page' => -1,
				);
			} else {
				$args = array(
					'fields'         => 'ids',
					'post_mime_type' => 'image',
					'post_status'    => 'any',
					'post_type'      => 'attachment',
					'post__in'       => array( absint( $__attachment_id ) ),
				);
			}
	
			$query = new WP_Query( $args );
			$found = absint( $query->found_posts );
			$all_posts = $query->posts;
			update_option( 'ast_opt_post_array', $all_posts );

			$this->ast_log( "#0($__attachment_id)Remove sizes, page $paged, $found posts." );

		} else {
			// get the list from the option
			$all_posts = get_option( 'ast_opt_post_array', array() );
			$found = count( $all_posts );
		}


		$removed_in_current_request = array();
		$upload_dir = wp_upload_dir();
		$upload_path = trailingslashit( $upload_dir['basedir'] );

		$cnt = ( $paged - 1 ) * 10;
		$finished = false;

		for( $x = 0; $x < 10; $x++ ) {

			if ( $cnt >= $found) {
				$finished = true;
				break;
			}

			$attachment_id = $all_posts[ $cnt ];
			$this->ast_log( "cnt=$cnt, id=$attachment_id, page $paged" );

			$cnt ++;

			$this->resize_orig_image( $attachment_id );

			$meta = wp_get_attachment_metadata( $attachment_id );

			if ( empty( $meta['file'] ) ) {
				continue;
			}

			if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
				continue;
			}

			$file_path = str_replace( basename( $meta['file'] ), '', $upload_path . $meta['file'] );

			// default sizes to keep, e.g. thumbnail, used in admin area
			$ignored_sizes = $this->get_ignored_image_sizes();
			
			// Don't remove images if they are used in default image sizes
			$do_not_delete = array();

			// get the file names that we want to keep first
			// Custom image size that matches the dimensions of the default ones and share the same file.
			// if it's handled first, it may cause the file to be deleted. Get the files first will avoid this problem.
			foreach ( $meta['sizes'] as $size => $params ) {
				if ( in_array( $size, $ignored_sizes ) ) {
					$do_not_delete[] = $params['file'];
				}
			}

			// now remove all the custom sizes
			foreach ( $meta['sizes'] as $size => $params ) {

				// we don't want to delete thumbnails, they are used in admin area
				if ( in_array( $size, $ignored_sizes ) ) {
					continue;
				}

				if ( ! in_array( $params['file'], $do_not_delete ) ) {

					$file = realpath( $file_path . $params['file'] );

					// to delete the file
					if ( file_exists( $file ) ) {

						$file_size_bytes = filesize( $file );

						unlink( $file );

						if ( ! file_exists( $file ) ) {
							// file deleted
							$removed++;
							$removed_in_current_request[] = $file;

							unset( $meta['sizes'][ $size ] );

							$this->ast_log( "#5 deleted size: $size, file: $file" );

							// statistics
							$remove_size_total = get_option( 'ast_opt_remove_size_total' );
							$remove_size_total += $file_size_bytes;
							update_option( 'ast_opt_remove_size_total', $remove_size_total );
		
						} else {
							// wierd, the file is still there, readable
							// keep the size
						}
					} else {

						unset( $meta['sizes'][ $size ] );

					}
				}
			} // foreach meta sizes

			wp_update_attachment_metadata( $attachment_id, $meta );

		} // foreach post

		if ( $finished) {
			delete_option( 'ast_opt_cleanup_progress' );
		}

		if ( ! $__attachment_id ) {

			$response = array(
				'finished' => $finished,
				'found' => $found,
				'paged' => $paged,
				'removed' => $removed,
				'success' => true,
			);

			$removed_so_far = get_option( 'ast_opt_removed_log', array() );
			$removed_log = array_merge( $removed_so_far, $removed_in_current_request );

			if ( $finished) {
				delete_option( 'ast_opt_removed_log' );

				$remove_size_total = get_option( 'ast_opt_remove_size_total' );
				delete_option( 'ast_opt_remove_size_total' );
				list( $remove_size_total, $size_unit ) = $this->convert_size_unit( $remove_size_total );
				if ( $remove_size_total > 0 ) {
					$removed_log[] = "Removed total $remove_size_total{$size_unit}Bytes.";
				}

				$downsize_total = get_option( 'ast_opt_downsize_total' );
				delete_option( 'ast_opt_downsize_total' );
				list( $downsize_total, $size_unit ) = $this->convert_size_unit( $downsize_total );
				if ( $downsize_total > 0 ) {
					$removed_log[] = "Downsize total $downsize_total{$size_unit}Bytes. The original are saved in uploads/ast-backup. You may want to download them and further free up the server space.";
				}

				$response['removed_log'] = $removed_log;

				$debug_log = get_option( 'ast_opt_debug_log', array() );
				if ( count( $debug_log ) > 1 ) {
					$response['debug_log'] = $debug_log;
				}
	
			} else {
				update_option( 'ast_opt_removed_log', $removed_log );
			}

			wp_send_json( $response );
		}
	}

	public function get_debug_log() {

		$debug_log = get_option( 'ast_opt_debug_log', array() );
		delete_option( 'ast_opt_debug_log' );

		$response['debug_log'] = $debug_log;
		wp_send_json( $response );

	}

	// add js and css needed on media settings screen
	public function enqueue_assets( $hook ) {

		if ( 'tools_page_auto-smart-thumbnails' !== $hook ) {
			// we only need this script in media settings
			return;
		}

		wp_enqueue_script( 'ast_Remove_Image_Sizes', AST_JS_URL . 'remove-image-sizes.js' );

		$localize = array(
			'l10n'  => array(
				'something_wrong'  => __( 'Something wrong. Please try again or contact the developer.', 'auto-smart-thumbnails' ),
				'process_finished' => __( 'Done! Number of thumbnails removed: %d.', 'auto-smart-thumbnails' ),
				'nothing_to_remove'=> __( 'Done! Nothing to clean up.', 'auto-smart-thumbnails' ),
				'cleanup_progress' => __( 'Cleanup in progress...', 'auto-smart-thumbnails' ),
			),
			'nonce' => wp_create_nonce( 'ast-nonce' ),
		);

		wp_localize_script( 'ast_Remove_Image_Sizes', 'auto_smart_thumb', $localize );

		wp_enqueue_style( 'ast_Remove_Image_Sizes', AST_CSS_URL . 'remove-image-sizes.css' );
	}
}

ast_Remove_Image_Sizes::instantiate();
