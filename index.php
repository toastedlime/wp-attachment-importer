<?php
/**
 * Plugin Name: Image Importer
 * Plugin URI: http://github.com/toastedlime/image-importer
 * Description: Imports images from a WordPress XML export file. This is useful if you have a large number of images to import and your server times out while importing using the WordPress Importer plugin.
 * Version: 0.1
 * Author: Toasted Lime
 * Author URI: http://www.toastedlime.com
 * License: Apache 2.0
 */

function image_importer_scripts(){

	wp_register_script( 'image-importer-js', plugins_url( 'main.js', __FILE__ ), array( 'jquery' ) );

}

function image_importer_add_page(){
	
	add_media_page( 'Image Importer', 'Image Importer', 'manage_options','image-importer','image_importer_options_page' );
	
}

function image_importer_options_page(){

	wp_enqueue_script( 'image-importer-js' );
	
?>

<div>

<h2>Image Importer</h2>

<noscript>

	<div class="error">

		<p>Sorry, but your browser doesn't have JavaScript enabled, and this plugin requires JavaScript.</p>

		<p>Please enable JavaScript for this site to continue.</p>

	</div>

</noscript>

<div id="image-importer-init"></div>

<div id="image-importer-output"></div>

</div>

<?php
}

add_action( 'admin_enqueue_scripts', 'image_importer_scripts' );

add_action( 'admin_menu', 'image_importer_add_page' );

add_action( 'wp_ajax_image_importer_init_success', 'image_importer_init_success' );

add_action( 'wp_ajax_image_importer_init_failure', 'image_importer_init_failure' );

add_action( 'wp_ajax_image_importer_upload', 'image_importer_uploader' );

// AJAX functions are below this line.

function image_importer_init_success(){
?>
	<p>Select the WordPress eXtended RSS (WXR) file and we'll try to get the images and upload them to your blog here.</p>

	<p>Choose a WXR (.xml) file from your computer and press upload.</p>

	<p><input type="file" id="file"/></p>

	<p><button class="button">Upload</button></p>

<?php
die();}

function image_importer_init_failure(){
?>

<div class="error">
	<p>Sorry, but you're using an <strong>outdated</strong> browser that doesn't support the features required to use this plugin.</p>
	<p>Please <a href="http://www.browsehappy.com">upgrade your browser</a> in order to use this plugin.</p>
</div>

<?php
die();}

function image_importer_uploader(){

	$parameters = array(
		'url' => $_POST['url'],
		'post_title' => $_POST['title'],
		'link' => $_POST['link'],
		'pubDate' => $_POST['pubDate'],
		'post_author' => $_POST['creator'],
		'guid' => $_POST['guid'],
		'import_id' => $_POST['post_id'],
		'post_date' => $_POST['post_date'],
		'post_date_gmt' => $_POST['post_date_gmt'],
		'comment_status' => $_POST['comment_status'],
		'ping_status' => $_POST['ping_status'],
		'post_name' => $_POST['post_name'],
		'post_status' => $_POST['status'],
		'post_parent' => $_POST['post_parent'],
		'menu_order' => $_POST['menu_order'],
		'post_type' => $_POST['post_type'],
		'post_password' => $_POST['post_password'],
		'is_sticky' => $_POST['is_sticky']
	);

	function process_attachment( $post, $url ) {
		
		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $url ) )
			$url = rtrim( $this->base_url, '/' ) . $url;

		$upload = fetch_remote_file( $url, $post );
		if ( is_wp_error( $upload ) )
			return $upload;

		if ( $info = wp_check_filetype( $upload['file'] ) )
			$post['post_mime_type'] = $info['type'];
		else
			return new WP_Error( 'attachment_processing_error', __('Invalid file type', 'wordpress-importer') );

		$post['guid'] = $upload['url'];

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		// remap resized image URLs, works by stripping the extension and remapping the URL stub.
		if ( preg_match( '!^image/!', $info['type'] ) ) {
			$parts = pathinfo( $url );
			$name = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

			$parts_new = pathinfo( $upload['url'] );
			$name_new = basename( $parts_new['basename'], ".{$parts_new['extension']}" );
		}

		return $post_id;
	}
	
	function fetch_remote_file( $url, $post ) {
		// extract the file name and extension from the url
		$file_name = basename( $url );

		// get placeholder file in the upload dir with a unique, sanitized filename
		$upload = wp_upload_bits( $file_name, 0, '', $post['post_date'] );
		if ( $upload['error'] )
			return new WP_Error( 'upload_dir_error', $upload['error'] );

		// fetch the remote url and write it to the placeholder file
		$headers = wp_get_http( $url, $upload['file'] );

		// request failed
		if ( ! $headers ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Remote server did not respond', 'wordpress-importer') );
		}

		// make sure the fetch was successful
		if ( $headers['response'] != '200' ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', sprintf( __('Remote server returned error response %1$d %2$s', 'wordpress-importer'), esc_html($headers['response']), get_status_header_desc($headers['response']) ) );
		}

		$filesize = filesize( $upload['file'] );

		if ( isset( $headers['content-length'] ) && $filesize != $headers['content-length'] ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Remote file is incorrect size', 'wordpress-importer') );
		}

		if ( 0 == $filesize ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Zero size file downloaded', 'wordpress-importer') );
		}

		/*
		 * Allow uploads of any size for now.
		 
		$max_size = (int) $this->max_attachment_size();
		if ( ! empty( $max_size ) && $filesize > $max_size ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', sprintf(__('Remote file is too large, limit is %s', 'wordpress-importer'), size_format($max_size) ) );
		}
		*/

		return $upload;
	}

	$remote_url = ! empty($parameters['attachment_url']) ? $parameters['attachment_url'] : $parameters['guid'];
	
	echo json_encode( process_attachment( $parameters, $remote_url ) );

die();}