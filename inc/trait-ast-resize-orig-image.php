<?php

trait ast_Resize_Orig_Image {

	public function ast_log( $log_info ) {

		//error_log( $log_info );

		$debug_switch = get_option( 'ast_opt_debug_switch' );
		if ( $debug_switch ) {
			$debug_log = get_option( 'ast_opt_debug_log', array() );
			$debug_log[] = $log_info;
			update_option( 'ast_opt_debug_log', $debug_log );
		}
	}

	public function convert_size_unit( $size_total ) {
		$size_total /= 1024;
		if ( $size_total > 1024 ) {
			$size_total /= 1024;
			$size_unit = 'M';
		} else {
			$size_unit = 'K';
		}
		return array( round($size_total, 2), $size_unit );
	}

	/*
	 * resize the original image 
	 *  1. if file size >128k
	 *  2. if the short side > 1080 (full high definition 1920x1080)
	 */
	public function resize_orig_image( $id ) {

		$meta = wp_get_attachment_metadata( $id );
		if ( isset( $meta['ast-checked'] ) && true === $meta['ast-checked'] ) {
			// this image has been checked
			return;
		}

		$attachment_path = get_attached_file( $id );

		list ( $src_width, $src_height ) = getimagesize( $attachment_path );

		if ( !empty( $meta ) ) {
			// in case the image was downsized outside, correct the meta data
			if ( $meta['width'] != $src_width || $meta['height'] != $src_height ) {
				$this->ast_log( "Update meta (" . $meta['width'] . " x " . $meta['height'] . ") -> ($src_width x $src_height)" );
				$meta['width'] = $src_width;
				$meta['height'] = $src_height;
			}
		} else {
			$meta = array();
		}
		$meta['ast-checked'] = true;
		wp_update_attachment_metadata( $id, $meta );

		$src_size_bytes = filesize( $attachment_path );
		$this->ast_log( "#0Resize id=$id, $attachment_path, size=$src_size_bytes, ($src_width x $src_height)" );

		$src_type = get_post_mime_type( $id );
		if ( $src_type !== 'image/jpeg' || ( ( false !== $src_size_bytes ) && ( $src_size_bytes < 128000) ) ) {
			return;
		}

		// downsize the original image
		$image_editor = wp_get_image_editor( $attachment_path );
		if ( ! is_wp_error( $image_editor ) ) {

			// check the short side, if >= 1080, downsize it by $div, else resave it
			if ( $src_width > $src_height ) {
				$div = floor( $src_height / 1080 );
			} else {
				$div = floor( $src_width / 1080 );
			}

			if ( $div > 1) {
				// downsize
        		$wanted_width = round( $src_width / $div, 0 );
				$wanted_height = round( $src_height / $div, 0 );
				$this->ast_log( "#1Downsize (/$div) -> ( $wanted_width x $wanted_height )" ) ;

				$image_editor->resize( $wanted_width, $wanted_height );
			}

			/* move the original to backup folder
			 * $meta['file']: 2019/08/old3.jpg
			 * $meta['sizes'][size=>thumbnail][file]: old1-100x100.jpg
			 * base: old3.jpg
			 * $upload_path: C:\\xampp\\htdocs/wp-content/uploads
			 * $file_path: C:\\xampp\\htdocs/wp-content/uploads/2019/08/
			 */
			$upload_dir = wp_upload_dir();
			$upload_path = trailingslashit( $upload_dir['basedir'] );
			$meta_file = str_replace( $upload_path, '', $attachment_path );
			$base_file = basename( $meta_file );
			$file_path = str_replace( $base_file, '', $attachment_path );
			$tmp_file = $file_path . 'temp-' . $base_file;

			/*
			 * Save the image to jpg (as tmp_file) and keep it only when the size is smaller than the original 
			 * return: {'path'=>string, 'file'=>string, 'width'=>int, 'height'=>int, 'mime-type'=>string}
			 * $result = $image_editor->save( $attachment_path, 'image/jpeg' );
			 */
			$result = $image_editor->save( $tmp_file );
			if ( empty( $result['path'] ) ) {
				// save failed
				return;
			}
			
			$new_size_bytes = filesize( $result['path'] );
			$this->ast_log( "#2New file: " . $result['path'] . ", size=$new_size_bytes" );
		 
			/*
			 * Save new file, only when the new file is 25k smaller
			 * hassle below is to backup the original and check the file operation output
			 */
			if ( ( false !== $new_size_bytes ) && ($new_size_bytes < ( $src_size_bytes - 25000) ) ) {

				// make sure the backup path ready
        		$backup_file = trailingslashit( $upload_path . 'ast-backup' ) . $meta_file;
				$backup_path = str_replace( $base_file, '', $backup_file );

				if ( !file_exists( $backup_path ) ) {
					if ( !mkdir( $backup_path, 0777, true ) ) {
						// should we quit? no, if backup fails, just overwrite it
            			$this->ast_log( "#3Failed to create dir $backup_path" );
						//return $meta;
					}
				}

				// backup the original file 
				if ( !rename( $attachment_path, $backup_file ) ) {
					$this->ast_log( "#4Failed to backup $attachment_path -> $backup_file" );
					// simply overwrite it. 
        		}

				if ( rename( $result['path'], $attachment_path ) ) {
					// rename done, update meta
					if ( !empty( $meta ) ) {
						$meta['width'] = $result['width'];
						$meta['height'] = $result['height'];
						wp_update_attachment_metadata( $id, $meta );
					}

					// statistics
					$downsize_total = get_option( 'ast_opt_downsize_total' );
					$downsize_total += ( $src_size_bytes - $new_size_bytes );
					update_option( 'ast_opt_downsize_total', $downsize_total );

					/*
					$downsize_files = get_option( 'ast_opt_downsize_files', array() );
					$downsize_files[] = "$attachment_path: $src_size_bytes -> $new_size_bytes";
					update_option( 'ast_opt_downsize_files', $downsize_files );
					*/
				} else {
					$this->ast_log( "#5Failed to rename" . $result['path'] . "-> $attachment_path" );

					if ( !rename( $backup_file, $attachment_path ) ) {
						$this->ast_log( "#6Failed to restore $backup_file -> $attachment_path" );
					}
				}
			} else {

				// delete the temp- file
				unlink( $result['path'] );

			} // new files created
		} // image_editor new size
	}
}