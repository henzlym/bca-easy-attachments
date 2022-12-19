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
        array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor'), // Dependencies, defined above.
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


add_action('rest_api_init', function () {
    register_rest_route('easy-attachments/v1', '/download', array(
        'methods' => 'POST',
        'callback' => 'easy_attachments_download',
        'permission_callback' => function () {
            return current_user_can('edit_others_posts');
        }

    ));
});

function easy_attachments_download(WP_REST_Request $request)
{
    require_once ABSPATH . "wp-admin/includes/file.php";
    require_once ABSPATH . "wp-admin/includes/media.php";
    require_once ABSPATH . "wp-admin/includes/image.php";

    // You can get the combined, merged set of parameters:
    $post_id = (null !== $request->get_param('post_id')) ? $request->get_param('post_id') : 0;
    $photo = (null !== $request->get_param('photo')) ? $request->get_param('photo') : null;
    $download_link = (null !== $request->get_param('download_link')) ? sanitize_text_field($request->get_param('download_link')) : "";

    $photo_alt_description = isset($photo['alt_description']) ? sanitize_text_field($photo['alt_description']) : "";
    $photo_description = isset($photo['description']) ? sanitize_text_field($photo['description']) : "";

    $photo_user_name = isset($photo['user']['name']) ? sanitize_text_field($photo['user']['name']) : "";
    $title = "";
    
    if ($photo_description !== "") {
        $title = $photo_description;
        $photo_description = "$title / $photo_user_name via Unsplash";
    }

    // Sanity check inputs
    if (!isset($download_link) || empty($download_link)) {
        return $result;
    }
 
    $url = esc_url_raw( $download_link );
    $parse_url = wp_parse_url( $url );
    $args = [];
    wp_parse_str( $parse_url[ 'query' ], $args );
    // Specific to Unsplash as the serve urls without file extensions.
    $file_extension = isset( $args['fm'] ) ? '.' . $args['fm'] : '';
    $url = $parse_url['scheme'] . '://' . $parse_url['host'] . $parse_url['path'];
    // error_log(print_r([$args,$url],true));

    // $image = media_sideload_image($url, $post_id, $photo_description, 'id');
    
    $file_array         = array();
    $file_array['name'] = wp_basename( $url ) . $file_extension;

    // Download file to temp location.
    $file_array['tmp_name'] = download_url( $url );
    $file_array['type'] = 'image/jpeg';
    $file_array['error'] = 0;
    $file_array['size'] = filesize( $file_array['tmp_name'] );

    // If error storing temporarily, return the error.
    if ( is_wp_error( $file_array['tmp_name'] ) ) {
        return  ['action' => 'error', 'error' => $file_array, 'image_url' => $url, 'step' => 'download_url' ];
    }

    // Do the validation and storage stuff.
    $image = media_handle_sideload( $file_array, $post_id, $photo_description );

    // If error storing permanently, unlink.
    if ( is_wp_error( $image ) ) {
        @unlink( $file_array['tmp_name'] );
        return  ['action' => 'error', 'error' => $image, 'image_url' => $url, 'step' => 'media_handle_sideload' ];
    }

    // Store the original attachment source in meta.
    add_post_meta( $image, '_source_url', $file );

    $attachment = get_post( $image );

	if( !$attachment ) {
        return  ['action' => 'error', 'message' => 'Attachment not found.'];
	}
    
    $updated = wp_update_post( array(
		'ID' => $attachment->ID,
		'post_title' => $title,
		'post_content' => $photo_description,
		'post_excerpt' => $photo_description,
	) );
	
    update_post_meta( $attachment->ID, '_wp_attachment_image_alt', $title );
	    
    return array(
        'success' => true,
        'msg' => __('Image successfully uploaded to your media library!', 'easy-attachments'),
        'id' => $attachment->ID,
        'url' => wp_get_attachment_url($attachment->ID),
        'alt' => $photo_description,
        'caption' => $photo_description,
        'admin_url' => admin_url(),
    );

}
