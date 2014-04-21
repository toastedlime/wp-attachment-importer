<?php
/**
 * Plugin Name: Attachment Importer
 * Plugin URI: http://github.com/toastedlime/wp-attachment-importer
 * Description: Imports images from a WordPress XML export file. This is useful if you have a large number of images to import and your server times out while importing using the WordPress Importer plugin.
 * Version: 0.6.0
 * Author: Toasted Lime
 * Author URI: http://www.toastedlime.com
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: attachment-importer
 */

function attachment_importer_scripts(){

	wp_register_script( 'attachment-importer-js', plugins_url( 'main.js', __FILE__ ), array( 'jquery', 'jquery-ui-tooltip', 'jquery-ui-progressbar' ), 20140421, true );

}

function attachment_importer_add_page(){
	
	register_importer( 'attachment-importer', 'Attachment Importer', 'Import attachments from a WordPress export file.', 'attachment_importer_options_page' );
	
}

function attachment_importer_options_page(){

	wp_enqueue_script( 'attachment-importer-js' );
	wp_localize_script( 'attachment-importer-js', 'aiL10n', array(
	    'emptyInput' => __( 'Please select a file.', 'attachment-importer' ),
	    'noAttachments' => __( 'There were no attachment files found in the import file.', 'attachment-importer' ),
		'parsing' => __( 'Parsing the file.', 'attachment-importer' ),
		'importing' => __( 'Importing file ', 'attachment-importer' ),
		'progress' => __( 'Overall progress: ', 'attachment-importer' ),
		'retrying' => __( 'An error occured. In 5 seconds, retrying file ', 'attachment-importer' ),
		'done' => __( 'All done!', 'attachment-importer' ),
		'ajaxFail' => __( 'There was an error connecting to the server.', 'attachment-importer' ),
		'pbAjaxFail' => __( 'The program could not run. Check the error log below or your JavaScript console for more information', 'attachment-importer' ),
		'fatalUpload' => __( 'There was a fatal error. Check the last entry in the error log below.', 'attachment-importer' )
	) );
	wp_localize_script( 'attachment-importer-js', 'aiSecurity', array(
		'nonce' => wp_create_nonce( 'import-attachment-plugin' )
	) );
	wp_enqueue_style( 'jquery-ui', plugins_url( 'inc/jquery-ui.css', __FILE__ ) );
	wp_enqueue_style( 'attachment-importer', plugins_url( 'inc/style.css', __FILE__ ) );
	
?>

<div class="wrap">

<h2><?php _e( 'Attachment Importer', 'attachment-importer' ); ?></h2>

<noscript>

	<div class="error">

		<p><?php _e( 'Sorry, but your browser doesn\'t have JavaScript enabled, and this plugin requires JavaScript.', 'attachment-importer' ); ?></p>

		<p><?php _e( 'Please enable JavaScript for this site to continue.', 'attachment-importer' ); ?></p>

	</div>

</noscript>

<div id="attachment-importer-init"></div>

<div id="attachment-importer-progressbar"><div id="attachment-importer-progresslabel"></div></div>

<div id="attachment-importer-output"></div>

</div>

<?php
}

add_action( 'admin_enqueue_scripts', 'attachment_importer_scripts' );

add_action( 'admin_menu', 'attachment_importer_add_page' );

add_action( 'wp_ajax_attachment_importer_init_success', 'attachment_importer_init_success' );

add_action( 'wp_ajax_attachment_importer_init_failure', 'attachment_importer_init_failure' );

add_action( 'wp_ajax_attachment_importer_upload', 'attachment_importer_uploader' );

// AJAX functions are below this line.

function attachment_importer_init_success(){
?>
	<p><?php _e( 'Select the WordPress eXtended RSS (WXR) file and we\'ll try to get the images and upload them to your blog.', 'attachment-importer' ); ?></p>

	<p><?php _e( 'Choose a WXR (.xml) file from your computer and press upload.', 'attachment-importer' ); ?></p>

	<p><input type="file" name="file" id="file"/></p>

	<p><?php _e( 'Attribute uploaded images to:', 'attachment-importer' ); ?><br/>
		<input type="radio" name="author" value=1 checked />&nbsp;<?php _e( 'Current User', 'attachment-importer' ); ?><br/>
		<input type="radio" name="author" value=2 />&nbsp;<?php _e( 'User in the import file', 'attachment-importer'); ?><br/>
		<input type="radio" name="author" value=3 />&nbsp;<?php _e( 'Select User:', 'attachment-importer' ); ?> <?php wp_dropdown_users(); ?>

	<p><input type="checkbox" name="delay" />&nbsp;<?php _e( 'Delay file requests by at least five seconds.', 'attachment-importer' ); ?>&nbsp;<a href="#" title="<?php _e( 'This delay can be useful to mitigate hosts that throttle traffic when too many requests are detected from an IP address and mistaken for a DDOS attack.', 'attachment-importer' ); ?>" style="text-decoration:none;"><span class="dashicons dashicons-editor-help"></span></a></p> 

	<p><?php submit_button( _x( 'Upload', 'A button which will submit the attachment for processing when clicked.', 'attachment-importer'), 'secondary', 'upload', false ); ?></p>

<?php
die();}

function attachment_importer_init_failure(){
?>

<div class="error">
	<p><?php _e( 'Sorry, but you\'re using an <strong>outdated</strong> browser that doesn\'t support the features required to use this plugin.', 'attachment-importer' ); ?></p>
	<p><?php echo sprintf( __( 'You must <a href="%s">upgrade your browser</a> in order to use this plugin.', 'attachment-importer' ), 'http://browsehappy.com' ); ?></p>
</div>

<?php
die();}

function attachment_importer_uploader(){
	
	// check nonce before doing anything else
	if( !check_ajax_referer( 'import-attachment-plugin', false, false ) ){
		$nonce_error = new WP_Error( 'nonce_error', __('Are you sure you want to do this?', 'attachment-importer') );
		echo json_encode ( array(
			'fatal' => true,
			'type' => 'error',
	        'code' => $nonce_error->get_error_code(),
			'message' => $nonce_error->get_error_message(),
			'text' => sprintf( __( 'The <a href="%1$s">security key</a> provided with this request is invalid. Is someone trying to trick you to upload something you don\'t want to? If you really meant to take this action, reload your browser window and try again. (<strong>%2$s</strong>: %3$s)', 'attachment-importer' ), 'http://codex.wordpress.org/WordPress_Nonces', $nonce_error->get_error_code(), $nonce_error->get_error_message() )
		) );
		die();
	}

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
		
		$pre_process = pre_process_attachment( $post, $url );
		if( is_wp_error( $pre_process ) )
			return array(
				'fatal' => false,
				'type' => 'error',
				'code' => $pre_process->get_error_code(),
				'message' => $pre_process->get_error_message(),
				'text' => sprintf( __( '%1$s was not uploaded. (<strong>%2$s</strong>: %3$s)', 'attachment-importer' ), $post['post_title'], $pre_process->get_error_code(), $pre_process->get_error_message() )
			);

		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $url ) )
			$url = rtrim( $this->base_url, '/' ) . $url;

		$upload = fetch_remote_file( $url, $post );
		if ( is_wp_error( $upload ) )
			return array(
				'fatal' => ( $upload->get_error_code() == 'upload_dir_error' && $upload->get_error_message() != 'Invalid file type' ? true : false ),
				'type' => 'error',
				'code' => $upload->get_error_code(),
				'message' => $upload->get_error_message(),
				'text' => sprintf( __( '%1$s could not be uploaded because of an error. (<strong>%2$s</strong>: %3$s)', 'attachment-importer' ), $post['post_title'], $upload->get_error_code(), $upload->get_error_message() )
			);

		if ( $info = wp_check_filetype( $upload['file'] ) )
			$post['post_mime_type'] = $info['type'];
		else {
			$upload = new WP_Error( 'attachment_processing_error', __('Invalid file type', 'attachment-importer') );
			return array(
				'fatal' => false,
				'type' => 'error',
				'code' => $upload->get_error_code(),
				'message' => $upload->get_error_message(),
				'text' => sprintf( __( '%1$s could not be uploaded because of an error. (<strong>%2$s</strong>: %3$s)', 'attachment-importer' ), $post['post_title'], $upload->get_error_code(), $upload->get_error_message() )
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
			'fatal' => false,
			'type' => 'updated',
			'text' => sprintf( __( '%s was uploaded successfully', 'attachment-importer' ), $post['post_title'] )
		);
	}

	function pre_process_attachment( $post, $url ){
		global $wpdb;

		$imported = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT ID, post_date_gmt, guid
				FROM $wpdb->posts
				WHERE post_type = 'attachment'
					AND post_title = %s
				",
				$post['post_title']
			)
		);

		if( $imported ){
			foreach( $imported as $attachment ){
				if( basename( $url ) == basename( $attachment->guid ) ){
					if( $post['post_date_gmt'] == $attachment->post_date_gmt ){
						$headers = wp_get_http( $url );
						if( filesize( get_attached_file( $attachment->ID ) ) == $headers['content-length'] ){
							return new WP_Error( 'duplicate_file_notice', __( 'File already exists', 'attachment-importer' ) );
						}
					}
				}
			}
		}

		return false;
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
			return new WP_Error( 'import_file_error', __('Remote server did not respond', 'attachment-importer') );
		}

		// make sure the fetch was successful
		if ( $headers['response'] != '200' ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', sprintf( __('Remote server returned error response %1$d %2$s', 'attachment-importer'), esc_html($headers['response']), get_status_header_desc($headers['response']) ) );
		}

		$filesize = filesize( $upload['file'] );

		if ( isset( $headers['content-length'] ) && $filesize != $headers['content-length'] ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Remote file is incorrect size', 'attachment-importer') );
		}

		if ( 0 == $filesize ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Zero size file downloaded', 'attachment-importer') );
		}

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
