jQuery(document).ready(function($){

	var divInit = $( '#image-importer-init' );

	if ( ! window.FileReader ){

		$.get(
			ajaxurl,
			{action:'image_importer_init_failure'},
			function( data ){
				$( data ).appendTo( divInit );
			});
		
	} else{
	
		$.get(
			ajaxurl,
			{action:'image_importer_init_success'},
			function( data ){
				$( data ).appendTo( divInit );
			});

		$( document ).on('click', '.button', function(){
			
			var input = $( '#file' ).get(0).files[0],
				reader = new FileReader(),
				divOutput = $( '#image-importer-output' ),
				author1 = $( "input[name='author']:checked" ).val(),
				author2 = $( "select[name='user']" ).val();

			divOutput.empty();

			if ( ! input ){

				alert('Please select a file.');
				
			} else {
			
				$( '<p>Parsing the file.</p>' ).appendTo( divOutput );

				reader.readAsText(input);
			
				reader.onload = function(e){
				
					var file = e.target.result,
						parser = new DOMParser(),
						xml = parser.parseFromString( file, "text/xml" ),
						url = [],
						new_url = [],
						title = [],
						link = [],
						pubDate = [],
						creator = [],
						guid = [],
						postID = [],
						postDate = [],
						postDateGMT = [],
						commentStatus = [],
						pingStatus = [],
						postName = [],
						status = [],
						postParent = [],
						menuOrder = [],
						postType = [],
						postPassword = [],
						isSticky = [];
					
					$( xml ).find( 'item' ).each(function(){
					
						var xml_post_type = $( this ).find( 'post_type' ).text();
						
						if( xml_post_type == 'attachment' ){ // We're only looking for image attachments.
							url.push( $( this ).find( 'attachment_url' ).text() );
							title.push( $( this ).find( 'title' ).text() );
							link.push( $( this ).find( 'link' ).text() );
							pubDate.push( $( this ).find( 'pubDate' ).text() );
							creator.push( $( this ).find( 'creator' ).text() );
							guid.push( $( this ).find( 'guid' ).text() );
							postID.push( $( this ).find( 'post_id' ).text() );
							postDate.push( $( this ).find( 'post_date' ).text() );
							postDateGMT.push( $( this ).find( 'post_date_gmt' ).text() );
							commentStatus.push( $( this ).find( 'comment_status' ).text() );
							pingStatus.push( $( this ).find( 'ping_status' ).text() );
							postName.push( $( this ).find( 'post_name' ).text() );
							status.push( $( this ).find( 'status' ).text() );
							postParent.push( $( this ).find( 'post_parent' ).text() );
							menuOrder.push( $( this ).find( 'menu_order' ).text() );
							postType.push( xml_post_type );
							postPassword.push( $( this ).find( 'post_password' ).text() );
							isSticky.push( $( this ).find( 'is_sticky' ).text() );
						}
					});
					
					$( '<p>Importing the attachments...</p>' ).appendTo( divOutput );

					function import_attachments(i){
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'image_importer_upload',
								author1:author1,
								author2:author2,
								url:url[i],
								title:title[i],
								link:link[i],
								pubDate:pubDate[i],
								creator:creator[i],
								guid:guid[i],
								post_id:postID[i],
								post_date:postDate[i],
								post_date_gmt:postDateGMT[i],
								comment_status:commentStatus[i],
								ping_status:pingStatus[i],
								post_name:postName[i],
								status:status[i],
								post_parent:postParent[i],
								menu_order:menuOrder[i],
								post_type:postType[i],
								post_password:postPassword[i],
								is_sticky:isSticky[i]
							}
						})
						.done(function( data, status, xhr ){
							var obj = $.parseJSON( data );
							if( obj.result ){
								$( '<div class="' + obj.type + '">' + obj.name + ' was uploaded successfully.</div>' ).appendTo( divOutput );
								new_url.push( obj.url );
							} else{
								$( '<div class="' + obj.type + '">' + obj.name + ' could not be uploaded because of an error. (<strong>' + obj.error_code + ':</strong> ' + obj.error_msg + ')</div>' ) .appendTo( divOutput );
							}
							i++;
							if( postType[i] ){
								import_attachments(i);
							} else {
								$( '<p>All done!</p>' ).appendTo( divOutput );
							}

						})
						.fail(function( xhr, status, error ){
							console.err(status);
							console.err(error);
							$( '<div class="error">There was an error connecting to the server</div>' ).appendTo( divOutput );
						});
					}
					
					import_attachments( 0 );
					
				}
				
			}
			
		});
		
	}
	
});