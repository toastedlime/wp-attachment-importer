<?php
/**
 * Plugin Name: Image Importer
 * Plugin URI: http://github.com/toastedlime/image-importer
 * Description: Imports images from a WordPress XML export file. This is useful if you have a large number of images to import and your server times out while importing using the WordPress Importer plugin.
 * Version: 0.3
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

	<p><input type="file" name="file" id="file"/></p>

	<p> Attribute uploaded images to: <br/>
		<input type="radio" name="author" value=1 checked />&nbsp;Current User<br/>
		<input type="radio" name="author" value=2 />&nbsp;User in import file<br/>
		<input type="radio" name="author" value=3 />&nbsp;Select User: <?php wp_dropdown_users(); ?>

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
		'is_sticky' => $_POST['is_sticky'],
		'attribute_author1' => $_POST['author1'],
		'attribute_author2' => $_POST['author2']
	);

	function process_attachment( $post, $url ) {
		
		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $url ) )
			$url = rtrim( $this->base_url, '/' ) . $url;

		$upload = fetch_remote_file( $url, $post );
		if ( is_wp_error( $upload ) )
			return array(
				'result' => false,
				'type' => 'error',
				'name' => $post['post_title'],
				'error_code' => $upload->get_error_code(),
				'error_msg' => $upload->get_error_message()
			);

		if ( $info = wp_check_filetype( $upload['file'] ) )
			$post['post_mime_type'] = $info['type'];
		else {
			$upload = new WP_Error( 'attachment_processing_error', __('Invalid file type', 'wordpress-importer') );
			return array(
				'result' => false,
				'type' => 'error',
				'name' => $post['post_title'],
				'error_code' => $upload->get_error_code(),
				'error_msg' => $upload->get_error_message()
			);
		}

		$post['guid'] = $upload['url'];

		// Set author per user options.
		switch( $post['attribute_author1'] ){

			case 1: // Attribute to current user.
				$post['post_author'] = (int) wp_get_current_user()->ID;
				break;

			case 2: // Attribute to user in import file.
				if( !username_exists( $post['post_author'] ) )
					wp_create_user( $post['post_author'], wp_generate_password() );
				$post['post_author'] = (int) username_exists( $post['post_author'] );
				break;

			case 3: // Attribute to selected user.
				$post['post_author'] = (int) $post['attribute_author2'];
				break;

		}

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		// remap image URL's
		backfill_attachment_urls( $url, $upload['url'] );

		return array(
			'result' => true,
			'type' => 'updated',
			'name' => $post['post_title'],
			'url' => $upload['url']
		);
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

	function backfill_attachment_urls( $from_url, $to_url ) {
		global $wpdb;
		// remap urls in post_content
		$wpdb->query(
			$wpdb->prepare(
				"
					UPDATE {$wpdb->posts}
					SET post_content = REPLACE(post_content, %s, %s)
				",
				$from_url, $to_url
			)
		);
		// remap enclosure urls
		$result = $wpdb->query(
			$wpdb->prepare(
				"
					UPDATE {$wpdb->postmeta}
					SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key='enclosure'
				",
				$from_url, $to_url
			)
		);
	}

	$remote_url = ! empty($parameters['attachment_url']) ? $parameters['attachment_url'] : $parameters['guid'];
	
	echo json_encode( process_attachment( $parameters, $remote_url ) );

die();}