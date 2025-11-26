<?php

/**
 * Blocks Initializer
 *
 * Enqueue CSS/JS of all the blocks.
 *
 * @since   1.0.0
 * @package
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

$upload_dir = wp_upload_dir();
define('EASY_ATTACHMENTS_MEDIA_LIBRARY_PATH', $upload_dir['path']);
define('EASY_ATTACHMENTS_MEDIA_LIBRARY_PATH_TEMP', $upload_dir['basedir'] . '/easy-attatchments');
define('EASY_ATTACHMENTS_MEDIA_LIBRARY_URL', $upload_dir['url']);
/**
 * Enqueue Gutenberg block assets for both frontend + backend.
 *
 * Assets enqueued:
 * 1. blocks.style.build.css - Frontend + Backend.
 * 2. blocks.build.js - Backend.
 * 3. blocks.editor.build.css - Backend.
 *
 * @uses {wp-blocks} for block type registration & related functions.
 * @uses {wp-element} for WP Element abstraction — structure of blocks.
 * @uses {wp-i18n} to internationalize the block's text.
 * @uses {wp-editor} for WP editor styles.
 * @since 1.0.0
 */
function easy_attachments_block_assets()
{
    // phpcs:ignore
    $asset = include_once EASY_ATTACHMENTS_PATH . '/build/index.asset.php';
    // Register block editor script for backend.
    wp_register_script(
        'easy_attachments-block-js', // Handle.
        EASY_ATTACHMENTS_URI . 'build/index.js', // Block.build.js: We register the block here. Built with Webpack.
        array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-plugins', 'wp-components', 'wp-data'), // Dependencies, defined above.
        $asset['version'], // filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.build.js' ), // Version: filemtime — Gets file modification time.
        true // Enqueue the script in the footer.
    );

    // Register block editor styles for backend.
    wp_register_style(
        'easy_attachments-block-editor-css', // Handle.
        EASY_ATTACHMENTS_URI . 'build/index.css', // Block editor CSS.
        array('wp-edit-blocks'), // Dependency to include the CSS after it.
        $asset['version'] // filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.editor.build.css' ) // Version: File modification time.
    );

    // WP Localized globals. Use dynamic PHP stuff in JavaScript via `Global` object.
    wp_localize_script(
        'easy_attachments-block-js',
        'blkcanvasGlobal', // Array containing dynamic data for a JS Global.
        [
            'pluginDirPath' => plugin_dir_path(__DIR__),
            'pluginDirUrl'  => plugin_dir_url(__FILE__),
            'redirectLink'  => get_site_url(),
            'nonce' => wp_create_nonce('wp_rest')
            // Add more data here that you want to access from `Global` object.
        ]
    );
}
// Hook: Block assets.
add_action('init', 'easy_attachments_block_assets');


/*
Plugin Name: Sidebar plugin
*/
function easy_attachments_sidebar_plugin_script_enqueue()
{
    wp_enqueue_script('easy_attachments-block-js');
    wp_enqueue_style('easy_attachments-block-editor-css');
}
add_action('enqueue_block_editor_assets', 'easy_attachments_sidebar_plugin_script_enqueue');

error_log('Easy Attachments: About to register rest_api_init hook');

/**
 * Register REST API route for image downloads.
 */
function easy_attachments_register_rest_route()
{
    error_log('Easy Attachments: rest_api_init hook fired! Registering route...');

    $registered = register_rest_route('easy-attachments/v1', '/download', array(
        'methods' => 'POST',
        'callback' => 'easy_attachments_download',
        'permission_callback' => function () {
            error_log('Easy Attachments: Permission callback checked - User can edit posts: ' . (current_user_can('edit_posts') ? 'YES' : 'NO'));
            return current_user_can('edit_posts');
        },
        'args' => array(
            'post_id' => array(
                'required' => false,
                'type' => 'integer',
                'default' => 0,
                'sanitize_callback' => 'absint',
            ),
            'photo' => array(
                'required' => false,
                'type' => 'object',
            ),
            'download_link' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ),
        ),
    ));

    error_log('Easy Attachments: REST route /easy-attachments/v1/download registered: ' . ($registered ? 'SUCCESS' : 'FAILED'));
}
add_action('rest_api_init', 'easy_attachments_register_rest_route');

error_log('Easy Attachments: rest_api_init hook listener added');

/**
 * Download and attach an image from an external URL to the WordPress media library.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response|WP_Error JSON response with attachment data or error.
 */
function easy_attachments_download(WP_REST_Request $request)
{
    // Start output buffering to catch any unexpected output
    ob_start();

    try {
        // Load WordPress admin functions for file handling.
        // These are not autoloaded and must be manually included for REST API contexts.
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        if (!function_exists('wp_read_image_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        error_log('Easy Attachments: Download function started');
        error_log('Easy Attachments: Request params: ' . print_r($request->get_params(), true));

        // Extract and validate parameters
        $post_id = absint($request->get_param('post_id'));
        $photo = $request->get_param('photo');
        $download_link = $request->get_param('download_link');

        error_log('Easy Attachments: post_id = ' . $post_id);
        error_log('Easy Attachments: download_link = ' . $download_link);

        // Validate required parameter
        if (empty($download_link)) {
            error_log('Easy Attachments: ERROR - download_link is empty or not set');
            ob_end_clean(); // Clear any buffered output
            return new WP_Error(
                'missing_download_link',
                __('Download link is required.', 'easy-attachments'),
                array('status' => 400)
            );
        }

        // Extract photo metadata
        $photo_metadata = easy_attachments_extract_photo_metadata($photo);
        error_log('Easy Attachments: Extracted metadata: ' . print_r($photo_metadata, true));

        // Parse and validate URL
        $url_data = easy_attachments_parse_image_url($download_link);
        if (is_wp_error($url_data)) {
            error_log('Easy Attachments: ERROR - Invalid URL: ' . $url_data->get_error_message());
            ob_end_clean(); // Clear any buffered output
            return $url_data;
        }

        error_log('Easy Attachments: Final download URL = ' . $url_data['url']);
        error_log('Easy Attachments: File extension = ' . $url_data['extension']);

        // Download the image file
        $file_array = easy_attachments_download_file($url_data['url'], $url_data['extension']);
        if (is_wp_error($file_array)) {
            error_log('Easy Attachments: ERROR during download: ' . $file_array->get_error_message());
            ob_end_clean(); // Clear any buffered output
            return $file_array;
        }

        error_log('Easy Attachments: File downloaded successfully: ' . $file_array['name']);

        // Import into media library
        $attachment_id = media_handle_sideload($file_array, $post_id, $photo_metadata['description']);

        if (is_wp_error($attachment_id)) {
            error_log('Easy Attachments: ERROR in media_handle_sideload: ' . $attachment_id->get_error_message());
            @unlink($file_array['tmp_name']);
            ob_end_clean(); // Clear any buffered output
            return new WP_Error(
                'upload_failed',
                $attachment_id->get_error_message(),
                array('status' => 500)
            );
        }

        error_log('Easy Attachments: Image attachment ID = ' . $attachment_id);

        // Update attachment metadata
        $update_result = easy_attachments_update_attachment_metadata(
            $attachment_id,
            $photo_metadata,
            $download_link
        );

        if (is_wp_error($update_result)) {
            error_log('Easy Attachments: ERROR updating metadata: ' . $update_result->get_error_message());
            // Don't fail the request, but log the error
        }

        // Prepare success response
        $attachment = get_post($attachment_id);
        $response_data = array(
            'success' => true,
            'message' => __('Image successfully uploaded to your media library!', 'easy-attachments'),
            'data' => array(
                'id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'title' => $attachment->post_title,
                'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                'caption' => $attachment->post_excerpt,
                'description' => $attachment->post_content,
                'mime_type' => get_post_mime_type($attachment_id),
                'edit_url' => admin_url('post.php?post=' . $attachment_id . '&action=edit'),
            ),
        );

        error_log('Easy Attachments: SUCCESS - Returning response: ' . print_r($response_data, true));

        // Clear output buffer and return clean JSON response
        ob_end_clean();
        return new WP_REST_Response($response_data, 200);
    } catch (Exception $e) {
        error_log('Easy Attachments: EXCEPTION - ' . $e->getMessage());
        error_log('Easy Attachments: Stack trace - ' . $e->getTraceAsString());
        ob_end_clean(); // Clear any buffered output
        return new WP_Error(
            'server_error',
            sprintf(__('Server error: %s', 'easy-attachments'), $e->getMessage()),
            array('status' => 500)
        );
    }
}

/**
 * Extract and sanitize photo metadata from the request.
 *
 * @param array|null $photo Photo data from request.
 * @return array Sanitized photo metadata.
 */
function easy_attachments_extract_photo_metadata($photo)
{
    $metadata = array(
        'alt_description' => '',
        'description' => '',
        'user_name' => '',
        'title' => '',
    );

    if (!is_array($photo)) {
        return $metadata;
    }

    $metadata['alt_description'] = isset($photo['alt_description']) ? sanitize_text_field($photo['alt_description']) : '';
    $metadata['description'] = isset($photo['description']) ? sanitize_text_field($photo['description']) : '';
    $metadata['user_name'] = isset($photo['user']['name']) ? sanitize_text_field($photo['user']['name']) : '';

    // Use description as title if available
    if (!empty($metadata['description'])) {
        $metadata['title'] = $metadata['description'];
        $metadata['description'] = sprintf(
            '%s / %s via Unsplash',
            $metadata['title'],
            $metadata['user_name']
        );
    }

    error_log('Easy Attachments: photo_alt_description = ' . $metadata['alt_description']);
    error_log('Easy Attachments: photo_description = ' . $metadata['description']);
    error_log('Easy Attachments: photo_user_name = ' . $metadata['user_name']);
    error_log('Easy Attachments: final title = ' . $metadata['title']);

    return $metadata;
}

/**
 * Parse and validate an image URL, extracting file extension.
 *
 * @param string $url The URL to parse.
 * @return array|WP_Error Array with 'url' and 'extension' keys, or WP_Error on failure.
 */
function easy_attachments_parse_image_url($url)
{
    $url = esc_url_raw($url);

    if (empty($url)) {
        return new WP_Error(
            'invalid_url',
            __('Invalid or malformed URL.', 'easy-attachments'),
            array('status' => 400)
        );
    }

    error_log('Easy Attachments: Escaped URL = ' . $url);

    $parsed = wp_parse_url($url);
    error_log('Easy Attachments: Parsed URL: ' . print_r($parsed, true));

    if (!isset($parsed['scheme']) || !isset($parsed['host']) || !isset($parsed['path'])) {
        return new WP_Error(
            'invalid_url',
            __('URL must contain scheme, host, and path.', 'easy-attachments'),
            array('status' => 400)
        );
    }

    $args = array();
    if (isset($parsed['query'])) {
        wp_parse_str($parsed['query'], $args);
        error_log('Easy Attachments: Query args: ' . print_r($args, true));
    }

    // Extract file extension from query parameter (Unsplash-specific)
    $file_extension = isset($args['fm']) ? '.' . sanitize_text_field($args['fm']) : '';

    // If no extension from query, try to get from path
    if (empty($file_extension)) {
        $path_extension = pathinfo($parsed['path'], PATHINFO_EXTENSION);
        if (!empty($path_extension)) {
            $file_extension = '.' . $path_extension;
        }
    }

    // Reconstruct clean URL
    $clean_url = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
    if (isset($parsed['query'])) {
        $clean_url .= '?' . $parsed['query'];
    }

    return array(
        'url' => $clean_url,
        'extension' => $file_extension,
    );
}

/**
 * Download a file from a URL to a temporary location.
 *
 * @param string $url The URL to download from.
 * @param string $extension The file extension to use.
 * @return array|WP_Error File array on success, WP_Error on failure.
 */
function easy_attachments_download_file($url, $extension = '')
{
    error_log('Easy Attachments: Starting download from URL...');

    $file_array = array();
    $file_array['name'] = wp_basename($url) . $extension;

    // Download file to temp location
    $tmp_file = download_url($url);

    if (is_wp_error($tmp_file)) {
        return new WP_Error(
            'download_failed',
            sprintf(
                __('Failed to download file: %s', 'easy-attachments'),
                $tmp_file->get_error_message()
            ),
            array('status' => 500)
        );
    }

    $file_array['tmp_name'] = $tmp_file;

    // Detect actual MIME type from downloaded file
    $file_type = wp_check_filetype_and_ext($tmp_file, $file_array['name']);
    $file_array['type'] = $file_type['type'] ?: 'image/jpeg'; // Fallback to jpeg
    $file_array['error'] = 0;
    $file_array['size'] = filesize($tmp_file);

    error_log('Easy Attachments: File array: ' . print_r($file_array, true));

    // Validate file size
    if ($file_array['size'] === false || $file_array['size'] === 0) {
        @unlink($tmp_file);
        return new WP_Error(
            'invalid_file',
            __('Downloaded file is empty or unreadable.', 'easy-attachments'),
            array('status' => 500)
        );
    }

    // Optional: Check file size limits (e.g., max 10MB)
    $max_size = apply_filters('easy_attachments_max_file_size', 10 * 1024 * 1024); // 10MB default
    if ($file_array['size'] > $max_size) {
        @unlink($tmp_file);
        return new WP_Error(
            'file_too_large',
            sprintf(
                __('File size exceeds maximum allowed size of %s.', 'easy-attachments'),
                size_format($max_size)
            ),
            array('status' => 400)
        );
    }

    error_log('Easy Attachments: File downloaded successfully to temp location');

    return $file_array;
}

/**
 * Update attachment post metadata and custom fields.
 *
 * @param int    $attachment_id The attachment post ID.
 * @param array  $metadata      Photo metadata array.
 * @param string $source_url    Original source URL.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function easy_attachments_update_attachment_metadata($attachment_id, $metadata, $source_url)
{
    error_log('Easy Attachments: Updating attachment post...');

    $attachment = get_post($attachment_id);

    if (!$attachment) {
        return new WP_Error(
            'attachment_not_found',
            __('Attachment post not found.', 'easy-attachments'),
            array('status' => 404)
        );
    }

    error_log('Easy Attachments: Attachment retrieved: ' . $attachment->post_title);

    // Update post fields
    $update_data = array(
        'ID' => $attachment_id,
    );

    if (!empty($metadata['title'])) {
        $update_data['post_title'] = $metadata['title'];
    }

    if (!empty($metadata['description'])) {
        $update_data['post_content'] = $metadata['description'];
        $update_data['post_excerpt'] = $metadata['description'];
    }

    $updated = wp_update_post($update_data, true);

    if (is_wp_error($updated)) {
        error_log('Easy Attachments: ERROR updating post: ' . $updated->get_error_message());
        return $updated;
    }

    error_log('Easy Attachments: Post updated successfully');

    // Update alt text
    if (!empty($metadata['title'])) {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $metadata['title']);
        error_log('Easy Attachments: Updated image alt text');
    }

    // Store original source URL
    update_post_meta($attachment_id, '_source_url', esc_url_raw($source_url));
    error_log('Easy Attachments: Added _source_url meta');

    // Store additional metadata
    if (!empty($metadata['user_name'])) {
        update_post_meta($attachment_id, '_source_author', sanitize_text_field($metadata['user_name']));
    }

    return true;
}
