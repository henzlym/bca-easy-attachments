<?php
/**
 * Plugin initialization and REST API registration.
 *
 * @package EasyAttachments
 * @since 1.0.0
 */

namespace EasyAttachments;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize plugin functionality.
 */
function init() {
	// Define upload directory constants.
	$upload_dir = wp_upload_dir();
	if ( ! defined( 'EASY_ATTACHMENTS_MEDIA_PATH' ) ) {
		define( 'EASY_ATTACHMENTS_MEDIA_PATH', $upload_dir['path'] );
	}
	if ( ! defined( 'EASY_ATTACHMENTS_MEDIA_URL' ) ) {
		define( 'EASY_ATTACHMENTS_MEDIA_URL', $upload_dir['url'] );
	}

	// Register assets.
	add_action( 'init', __NAMESPACE__ . '\\register_assets' );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets' );

	// Register REST API routes.
	add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_routes' );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Register plugin assets.
 *
 * @since 1.0.0
 * @return void
 */
function register_assets() {
	$asset_file = EASY_ATTACHMENTS_PATH . 'build/index.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = include $asset_file;

	// Register editor script.
	wp_register_script(
		'easy-attachments-editor',
		EASY_ATTACHMENTS_URI . 'build/index.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);

	// Register editor styles.
	wp_register_style(
		'easy-attachments-editor',
		EASY_ATTACHMENTS_URI . 'build/index.css',
		array( 'wp-edit-blocks' ),
		$asset['version']
	);
}

/**
 * Enqueue block editor assets.
 *
 * @since 1.0.0
 * @return void
 */
function enqueue_editor_assets() {
	wp_enqueue_script( 'easy-attachments-editor' );
	wp_enqueue_style( 'easy-attachments-editor' );

	// Localize script with configuration.
	$permalink_structure   = get_option( 'permalink_structure' );
	$use_pretty_permalinks = ! empty( $permalink_structure );

	wp_localize_script(
		'easy-attachments-editor',
		'easyAttachmentsConfig',
		array(
			'restUrl'             => $use_pretty_permalinks
				? rest_url( 'easy-attachments/v1/download' )
				: home_url( '/?rest_route=/easy-attachments/v1/download' ),
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'usePrettyPermalinks' => $use_pretty_permalinks,
		)
	);
}

/**
 * Register REST API routes.
 *
 * @since 1.0.0
 * @return void
 */
function register_rest_routes() {
	register_rest_route(
		'easy-attachments/v1',
		'/download',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\\handle_image_download',
			'permission_callback' => function () {
				return current_user_can( 'upload_files' );
			},
			'args'                => array(
				'post_id'       => array(
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				),
				'photo'         => array(
					'type' => 'object',
				),
				'download_link' => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'esc_url_raw',
				),
			),
		)
	);
}

/**
 * Handle image download REST API request.
 *
 * @since 1.0.0
 *
 * @param \WP_REST_Request $request The REST API request object.
 * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
 */
function handle_image_download( \WP_REST_Request $request ) {
	// Start output buffering to catch any unexpected output.
	ob_start();

	try {
		// Load required WordPress admin functions.
		require_admin_dependencies();

		// Extract parameters.
		$post_id       = absint( $request->get_param( 'post_id' ) );
		$photo         = $request->get_param( 'photo' );
		$download_link = $request->get_param( 'download_link' );

		// Validate download link.
		if ( empty( $download_link ) ) {
			ob_end_clean();
			return new \WP_Error(
				'missing_download_link',
				__( 'Download link is required.', 'easy-attachments' ),
				array( 'status' => 400 )
			);
		}

		// Extract and sanitize photo metadata.
		$metadata = extract_photo_metadata( $photo );

		// Parse and validate the image URL.
		$url_data = parse_image_url( $download_link );
		if ( is_wp_error( $url_data ) ) {
			ob_end_clean();
			return $url_data;
		}

		// Download the image file.
		$file_array = download_remote_file( $url_data['url'], $url_data['extension'] );
		if ( is_wp_error( $file_array ) ) {
			ob_end_clean();
			return $file_array;
		}

		// Import into media library.
		$attachment_id = media_handle_sideload( $file_array, $post_id, $metadata['description'] );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $file_array['tmp_name'] );
			ob_end_clean();
			return new \WP_Error(
				'upload_failed',
				$attachment_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		// Update attachment metadata.
		update_attachment_metadata( $attachment_id, $metadata, $download_link );

		// Prepare response.
		$attachment = get_post( $attachment_id );
		$response   = array(
			'success' => true,
			'message' => __( 'Image successfully uploaded to your media library!', 'easy-attachments' ),
			'data'    => array(
				'id'          => $attachment_id,
				'url'         => wp_get_attachment_url( $attachment_id ),
				'title'       => $attachment->post_title,
				'alt'         => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'caption'     => $attachment->post_excerpt,
				'description' => $attachment->post_content,
				'mime_type'   => get_post_mime_type( $attachment_id ),
				'edit_url'    => admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ),
			),
		);

		ob_end_clean();
		return new \WP_REST_Response( $response, 200 );

	} catch ( \Exception $e ) {
		ob_end_clean();
		return new \WP_Error(
			'server_error',
			sprintf( __( 'Server error: %s', 'easy-attachments' ), $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}

/**
 * Load required WordPress admin dependencies.
 *
 * @since 1.0.0
 * @return void
 */
function require_admin_dependencies() {
	if ( ! function_exists( 'download_url' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}
	if ( ! function_exists( 'wp_read_image_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
}

/**
 * Extract and sanitize photo metadata.
 *
 * @since 1.0.0
 *
 * @param array|null $photo Photo data from request.
 * @return array Sanitized photo metadata.
 */
function extract_photo_metadata( $photo ) {
	$metadata = array(
		'alt_description' => '',
		'description'     => '',
		'user_name'       => '',
		'title'           => '',
	);

	if ( ! is_array( $photo ) ) {
		return $metadata;
	}

	$metadata['alt_description'] = isset( $photo['alt_description'] )
		? sanitize_text_field( $photo['alt_description'] )
		: '';

	$metadata['description'] = isset( $photo['description'] )
		? sanitize_text_field( $photo['description'] )
		: '';

	$metadata['user_name'] = isset( $photo['user']['name'] )
		? sanitize_text_field( $photo['user']['name'] )
		: '';

	// Use description as title if available.
	if ( ! empty( $metadata['description'] ) ) {
		$metadata['title']       = $metadata['description'];
		$metadata['description'] = sprintf(
			'%s / %s via Unsplash',
			$metadata['title'],
			$metadata['user_name']
		);
	}

	return $metadata;
}

/**
 * Parse and validate an image URL.
 *
 * @since 1.0.0
 *
 * @param string $url The URL to parse.
 * @return array|\WP_Error Array with 'url' and 'extension' keys, or WP_Error on failure.
 */
function parse_image_url( $url ) {
	$url = esc_url_raw( $url );

	if ( empty( $url ) ) {
		return new \WP_Error(
			'invalid_url',
			__( 'Invalid or malformed URL.', 'easy-attachments' ),
			array( 'status' => 400 )
		);
	}

	$parsed = wp_parse_url( $url );

	if ( ! isset( $parsed['scheme'], $parsed['host'], $parsed['path'] ) ) {
		return new \WP_Error(
			'invalid_url',
			__( 'URL must contain scheme, host, and path.', 'easy-attachments' ),
			array( 'status' => 400 )
		);
	}

	// Extract file extension from query parameter (Unsplash-specific).
	$file_extension = '';
	if ( isset( $parsed['query'] ) ) {
		$args = array();
		wp_parse_str( $parsed['query'], $args );
		$file_extension = isset( $args['fm'] ) ? '.' . sanitize_text_field( $args['fm'] ) : '';
	}

	// Fallback to path extension.
	if ( empty( $file_extension ) ) {
		$path_extension = pathinfo( $parsed['path'], PATHINFO_EXTENSION );
		if ( ! empty( $path_extension ) ) {
			$file_extension = '.' . $path_extension;
		}
	}

	// Reconstruct clean URL.
	$clean_url = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
	if ( isset( $parsed['query'] ) ) {
		$clean_url .= '?' . $parsed['query'];
	}

	return array(
		'url'       => $clean_url,
		'extension' => $file_extension,
	);
}

/**
 * Download a file from a URL to a temporary location.
 *
 * @since 1.0.0
 *
 * @param string $url       The URL to download from.
 * @param string $extension The file extension to use.
 * @return array|\WP_Error File array on success, WP_Error on failure.
 */
function download_remote_file( $url, $extension = '' ) {
	$file_array         = array();
	$file_array['name'] = wp_basename( $url ) . $extension;

	// Download file to temp location.
	$tmp_file = download_url( $url );

	if ( is_wp_error( $tmp_file ) ) {
		return new \WP_Error(
			'download_failed',
			sprintf(
				__( 'Failed to download file: %s', 'easy-attachments' ),
				$tmp_file->get_error_message()
			),
			array( 'status' => 500 )
		);
	}

	$file_array['tmp_name'] = $tmp_file;

	// Detect MIME type.
	$file_type          = wp_check_filetype_and_ext( $tmp_file, $file_array['name'] );
	$file_array['type'] = $file_type['type'] ?: 'image/jpeg';
	$file_array['error'] = 0;
	$file_array['size'] = filesize( $tmp_file );

	// Validate file size.
	if ( false === $file_array['size'] || 0 === $file_array['size'] ) {
		@unlink( $tmp_file );
		return new \WP_Error(
			'invalid_file',
			__( 'Downloaded file is empty or unreadable.', 'easy-attachments' ),
			array( 'status' => 500 )
		);
	}

	// Check file size limits.
	$max_size = apply_filters( 'easy_attachments_max_file_size', 10 * 1024 * 1024 ); // 10MB default.
	if ( $file_array['size'] > $max_size ) {
		@unlink( $tmp_file );
		return new \WP_Error(
			'file_too_large',
			sprintf(
				__( 'File size exceeds maximum allowed size of %s.', 'easy-attachments' ),
				size_format( $max_size )
			),
			array( 'status' => 400 )
		);
	}

	return $file_array;
}

/**
 * Update attachment post metadata and custom fields.
 *
 * @since 1.0.0
 *
 * @param int    $attachment_id The attachment post ID.
 * @param array  $metadata      Photo metadata array.
 * @param string $source_url    Original source URL.
 * @return bool|\WP_Error True on success, WP_Error on failure.
 */
function update_attachment_metadata( $attachment_id, $metadata, $source_url ) {
	$attachment = get_post( $attachment_id );

	if ( ! $attachment ) {
		return new \WP_Error(
			'attachment_not_found',
			__( 'Attachment post not found.', 'easy-attachments' ),
			array( 'status' => 404 )
		);
	}

	// Update post fields.
	$update_data = array( 'ID' => $attachment_id );

	if ( ! empty( $metadata['title'] ) ) {
		$update_data['post_title'] = $metadata['title'];
	}

	if ( ! empty( $metadata['description'] ) ) {
		$update_data['post_content'] = $metadata['description'];
		$update_data['post_excerpt'] = $metadata['description'];
	}

	$updated = wp_update_post( $update_data, true );

	if ( is_wp_error( $updated ) ) {
		return $updated;
	}

	// Update alt text.
	if ( ! empty( $metadata['title'] ) ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $metadata['title'] );
	}

	// Store original source URL.
	update_post_meta( $attachment_id, '_source_url', esc_url_raw( $source_url ) );

	// Store additional metadata.
	if ( ! empty( $metadata['user_name'] ) ) {
		update_post_meta( $attachment_id, '_source_author', sanitize_text_field( $metadata['user_name'] ) );
	}

	return true;
}
