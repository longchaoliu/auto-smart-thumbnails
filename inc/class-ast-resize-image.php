<?php

require_once __DIR__ . '/trait-ast-resize-orig-image.php';
require_once __DIR__ . '/trait-ast-smart-crop-face.php';

class ast_Resize_Image {
	use ast_Resize_Orig_Image;
	use ast_Smart_Crop_Face;

	// Will hold the only instance of our main plugin class
	private static $instance;

	// Instantiate the class and set up stuff
	public static function instantiate() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof ast_Resize_Image ) ) {
			self::$instance = new ast_Resize_Image();
		}
		return self::$instance;
	}

	public function __construct() {

		add_filter( 'image_downsize', array( $this, 'image_downsize' ), 10, 3 );

	}

	// we hook into the filter, check if image size exists, generate it if not and then bail out
	public function image_downsize( $out, $id, $size ) {

		//$this->resize_orig_image( $id );

		$attachment_path = get_attached_file( $id );

		// we don't handle this
    	if ( is_array( $size ) ) {
			return false;
		}

		$meta = wp_get_attachment_metadata( $id );
		$wanted_width = $wanted_height = 0;

		if ( empty( $meta['file'] ) ) {
			return false;
		}

		// custom defined sizes
		global $_wp_additional_image_sizes;

		if ( isset( $_wp_additional_image_sizes ) && isset( $_wp_additional_image_sizes[ $size ] ) ) {

			$wanted_width = $_wp_additional_image_sizes[ $size ]['width'];
			$wanted_height = $_wp_additional_image_sizes[ $size ]['height'];
			$wanted_crop = isset( $_wp_additional_image_sizes[ $size ]['crop'] ) ? $_wp_additional_image_sizes[ $size ]['crop'] : false;

		} else if ( in_array( $size, array( 'thumbnail', 'medium', 'large' ) ) ) {

			$wanted_width  = get_option( $size . '_size_w' );
			$wanted_height = get_option( $size . '_size_h' );
			$wanted_crop   = ( 'thumbnail' === $size ) ? (bool) get_option( 'thumbnail_crop' ) : false;

		} else {
			// unknown size, bail out
      		return false;
		}

		if ( 0 === absint( $wanted_width ) && 0 === absint( $wanted_height ) ) {
			return false;
		}

		$this->ast_log( "#1downsize id=$id, $size, file: $attachment_path" );

		if ( $intermediate = image_get_intermediate_size( $id, $size ) ) {

			$img_url = wp_get_attachment_url( $id );
			$img_url_basename = wp_basename( $img_url );

			$img_url = str_replace( $img_url_basename, $intermediate['file'], $img_url );
			$result_width = $intermediate['width'];
			$result_height = $intermediate['height'];

			$this->ast_log( "#5existing interm size, file: $img_url" );

			return array(
				$img_url,
				$result_width,
				$result_height,
				true,
			);
		} // else size doesn't exist
    
		// either file or size doesn't exist
		// image size not found, create it
		$result = $this->smart_crop_face( $id, $wanted_width, $wanted_height, $wanted_crop );
		if ( $result ) {
			list( $filename, $result_width, $result_height ) = $result;
		} else {
			
			$image_editor = wp_get_image_editor( $attachment_path );

			if ( ! is_wp_error( $image_editor ) ) {
	
				$image_editor->resize( $wanted_width, $wanted_height, $wanted_crop );
	
				$result_image_size = $image_editor->get_size();
				$result_width = $result_image_size['width'];
				$result_height = $result_image_size['height'];
	
				$suffix = $result_width . 'x' . $result_height;
				$filename = $image_editor->generate_filename( $suffix );
	
				$image_editor->save( $filename );
			}	
		}

		if ( $filename ) {
			$result_filename = wp_basename( $filename );
			
			$meta['sizes'][ $size ] = array(
				'file'      => $result_filename,
				'width'     => $result_width,
				'height'    => $result_height,
				'mime-type' => get_post_mime_type( $id ),
			);

			wp_update_attachment_metadata( $id, $meta );

			$img_url = wp_get_attachment_url( $id );
			$img_url_basename = wp_basename( $img_url );

			$img_url = str_replace( $img_url_basename, $result_filename, $img_url );
			$this->ast_log( "#7result file: filename, url: $img_url" );
			
			return array(
				$img_url,
				$result_width,
				$result_height,
				true,
			);

		} else {
			return false;
		}
	}
}

ast_Resize_Image::instantiate();
