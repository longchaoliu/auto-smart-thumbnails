<?php

require_once __DIR__ . '/class-ast-face-detector.php';

trait ast_Smart_Crop_Face {

	private $image;

	private function ast_load_image( $src_file ) {

		$imageInfo = getimagesize( $src_file );
		if ( ! $imageInfo ) {
			return false;
		}

		$this->src_type = $imageInfo[2];

		if ( $this->src_type == IMAGETYPE_JPEG ) {

			$this->image = imagecreatefromjpeg( $src_file );

		} elseif( $this->src_type == IMAGETYPE_GIF ) {

			$this->image = imagecreatefromgif( $src_file );

		} elseif( $this->src_type == IMAGETYPE_PNG ) {

			$this->image = imagecreatefrompng( $src_file );

		} else {
			return false;
		}

		return true;
	}

	private function ast_save_image( $dst_img, $dst_file ) {

		if ( $this->src_type == IMAGETYPE_JPEG ) {

			return imagejpeg($dst_img, $dst_file);

		} elseif( $this->src_type == IMAGETYPE_GIF ) {

			return imagegif($dst_img, $dst_file);         

		} elseif( $this->src_type == IMAGETYPE_PNG ) {

			return imagepng($dst_img, $dst_file);

		} else {
			return false;
		}
	}

	private function ast_file_name( $suffix = null, $src_path ) {
		// $suffix will be appended to the destination filename, just before the extension
		if ( ! $suffix ) {
			return $src_path;
		}

		$dir  = pathinfo( $src_path, PATHINFO_DIRNAME );
		$ext  = pathinfo( $src_path, PATHINFO_EXTENSION );

		$name = wp_basename( $src_path, ".$ext" );

		return trailingslashit( $dir ) . "{$name}-{$suffix}.{$ext}";
	}

	// locate the crop window
	private function ast_image_crop_location( $src_h, $crop_h, $face_y, $face_h, $y, $top, $bottom ) {

		$s_x = 0;

		$s_y_max = $src_h - $crop_h;

		if ( $top === $y ) {

			// let face sit beteen top to 2/3 $crop_h
			$s_y = max( 0, $face_y - (int)( $crop_h * 2/3 ) );
			// face at the bottom part of the cropped img, move lower, at 10% $crop_h margin
			$s_y = max( $s_y, $face_y + $face_h - (int)( $crop_h * 0.9 ) );
			$s_y = min( $s_y_max, $s_y );

		} elseif ( $bottom === $y ) {

			// leave 10% $crop_h margin on top
			$s_y = min( $s_y_max, $face_y - (int)( $crop_h * 0.1 ) );
			$s_y = max( 0, $s_y );

		} else {
			if ( 'top' === $top ) {
				// vertical, face slightly higher
				$margin = (int)(( $crop_h - $face_h ) * 0.4 );
			} else {
				// horizontal, face slightly left at center
				$margin = (int)(( $crop_h - $face_h ) * 0.49 );
			}

			$s_y = min( $s_y_max, $face_y - $margin );
			$s_y = max( 0, $s_y );

		}
		return array( $s_x, $s_y );
	}

	// similar to image_resize_dimensions, same return parameters
	private function ast_image_crop_dimensions( $face, $src_w, $src_h, $dst_w, $dst_h, $crop ) {

		// if the resulting image is the same size or larger any side, don't to resize it
		if ( $dst_w > $src_w || $dst_h > $src_h || ( $dst_w == $src_w && $dst_h == $src_h ) || ( ! $dst_w && ! $dst_h ) ) {
			return false;
		}

		// crop the largest possible portion of the original image that we can size to $dst_w x $dst_h
		$aspect_ratio = $src_w / $src_h;
		if ( ! $dst_w ) {
			$dst_w = (int) round( $dst_h * $aspect_ratio );
		}
		if ( ! $dst_h ) {
			$dst_h = (int) round( $dst_w / $aspect_ratio );
		}

		$size_ratio = max($dst_w / $src_w, $dst_h / $src_h);
		$crop_w = round($dst_w / $size_ratio);
		$crop_h = round($dst_h / $size_ratio);

		if ( $crop_w == $src_w && $crop_h == $src_h ) {
			// no crop needed
			return false;
		}

		if ( ! is_array( $crop ) || count( $crop ) !== 2 ) {
			$crop = array( 'center', 'center' );
		}

		list( $x, $y ) = $crop;
		$face_w = $face['w'];
		$face_h = $face['h'];
		if ( ! $face_h ) {
			$face_h = $face_w;
		}

		if ( $crop_w == $src_w ) {
			// crop vertically
			list( $s_x, $s_y ) = $this->ast_image_crop_location( $src_h, $crop_h, $face['y'], $face_h, $y, 'top', 'bottom' );
		} elseif ( $crop_h == $src_h ) {
			// crop horitzontally
			// the same function, just change the dimension
			list( $s_y, $s_x ) = $this->ast_image_crop_location( $src_w, $crop_w, $face['x'], $face_w, $x, 'left', 'right' );
		} else {
			// something wrong
			return false;
		}

		$this->ast_log( "Crop($s_x, $s_y), face({$face['x']}, {$face['y']}), $face_w, src($src_w, $src_h)>crop($crop_w, $crop_h)>dst($dst_w, $dst_h)" );

		// the return array matches the parameters to imagecopyresampled()
		// int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h
		return array( 0, 0, (int) $s_x, (int) $s_y, (int) $dst_w, (int) $dst_h, (int) $crop_w, (int) $crop_h );
	}

	/*
	* Smart crop with face detection. 
	* Face(s) detected will be recorded in meta data, with algorithm(s) used. 
	* At the moment, two php implementations of face detection are available:
	* 1. https://github.com/mauricesvay/php-facedetection by Karthik Tharavaad and Maurice Svay. 
	*	It  returns only the first face rectangle detectd. And detection is donw on downsized image. A little faster. 
	* 2. https://github.com/felixkoch/PHP-FaceDetector by unidentified someone and Felix Koch. 
	*	It can return multiple face rectangles. It doesn't downsize image. Slightly slower.  

	* The new meta will look like:
	Array (
		[width] => 512
		[height] => 512
		[file] => 2019/04/sample-image-file.jpg
		[sizes] => Array (
			[thumbnail] => Array (
				[file] => sample-image-file-100x100.png
				[width] => 100
				[height] => 100
				[mime-type] => image/png
			)
			[medium] => Array (
				...
			)
		)
		[focal_area] = (
			[x] => 100
			[y] => 123
			[w] => 58
			// below fields are optional
			[faces] => Array (
				[tharavaad-svay] => Array (
					[0] => Array (
						[x] => 100
						[y] => 123
						[w] => 58
					)
				)
				[koch] => Array (
					[0] => Array (
						[x] => 100
						[y] => 123
						[w] => 58
					)
					[1] => Array (
						...
					)
				)
			)
		)
		[image_meta] => Array (
			[camera] => 
			[caption] => 
			[created_timestamp] => 0
			[focal_length] => 0
			[shutter_speed] => 0
			[title] => 
			[orientation] => 0
			...
			[keywords] => Array (
				...
			)
		)
	)
	*/
	public function smart_crop_face( $id, $dst_w, $dst_h, $crop ) {

		if ( ! $crop ) {
			return false;
		}

		$meta = wp_get_attachment_metadata( $id );
		if ( ! $meta['width'] && ! $meta['height'] ) {
			return false;
		}

		$src_file = get_attached_file( $id );
		if ( ! $this->ast_load_image( $src_file ) ) {
			return false;
		}

		if ( empty( $meta['focal_area'] ) || ! is_array( $meta['focal_area'] ) || ( isset( $meta['focal_area'] ) && ! $meta['focal_area']['w'] ) ) {

			// detect and set faces
			$detector = new ast_FaceDetector();
			$detector->faceDetect( $this->image );
			$face = $detector->getFace();

			if ( empty( $face ) ) {
				// no face found
				return false;
			} else {
				// set meta, with algorithm(s) used and faces detected. 
				$meta['focal_area'] = array (
					'x' => $face['x'],
					'y' => $face['y'],
					'w' => $face['w'],
					'h' => $face['w'],
					'faces' => array (
						'tharavaad-svay' => array (
							array (
								'x' => $face['x'],
								'y' => $face['y'],
								'w' => $face['w'],
							),
						),
					),
				);
				wp_update_attachment_metadata( $id, $meta );
			}
		}

		// smart crop
		$dims = $this->ast_image_crop_dimensions( $meta['focal_area'], $meta['width'], $meta['height'], $dst_w, $dst_h, $crop );

		if ( ! $dims ) {
			return false;
		}

		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		$dst_img = wp_imagecreatetruecolor( $dst_w, $dst_h );

		if ( function_exists( 'imageantialias' ) ) {
			imageantialias( $dst_img, true );
		}

		imagecopyresampled( $dst_img, $this->image, 0, 0, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );

		if ( is_resource( $dst_img ) ) {

			imagedestroy( $this->image );

			$suffix = $dst_w . 'x' . $dst_h;
			$dst_file = $this->ast_file_name( $suffix, $src_file );

			$result = $this->ast_save_image( $dst_img, $dst_file);

			imagedestroy( $dst_img );

			if ( $result ) {
				return array( $dst_file, $dst_w, $dst_h );
			}
		}
		return false;
	}
}