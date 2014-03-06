jQuery(document).ready(function($){

	$( document ).tooltip();

	var divInit = $( '#attachment-importer-init' );

	if ( ! window.FileReader ){

		$.get(
			ajaxurl,
			{action:'attachment_importer_init_failure'},
			function( data ){
				$( data ).appendTo( divInit );
			});
		
	} else{
	
		$.get(
			ajaxurl,
			{action:'attachment_importer_init_success'},
			function( data ){
				$( data ).appendTo( divInit );
			});

		$( document ).on('click', '.button', function(){			

			var input = $( '#file' ).get(0).files[0],
				reader = new FileReader(),
				divOutput = $( '#attachment-importer-output' ),
				author1 = $( "input[name='author']:checked" ).val(),
				author2 = $( "select[name='user']" ).val(),
				delay = ( $( "input[name='delay']" ).is( ':checked' ) ? 5000 : 0 ),
				progressBar = $( "#attachment-importer-progressbar" ),
				progressLabel = $( "#attachment-importer-progresslabel" );

			if ( ! input ){

				alert( aiL10n.emptyInput );
				
			} else {

				divOutput.empty();

				$( function(){
					progressBar.progressbar({
						value: false
					});
					progressLabel.text( aiL10n.parsing );
				});

				reader.readAsText(input);
			
				reader.onload = function(e){
				
					var file = e.target.result,
						parser = new DOMParser(),
						xml = parser.parseFromString( file, "text/xml" ),
						url = [],
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
					
						var xml_post_type = $( this ).find( 'wp\\:post_type, post_type' ).text();
						
						if( xml_post_type == 'attachment' ){ // We're only looking for image attachments.
							url.push( $( this ).find( 'wp\\:attachment_url, attachment_url' ).text() );
							title.push( $( this ).find( 'title' ).text() );
							link.push( $( this ).find( 'link' ).text() );
							pubDate.push( $( this ).find( 'pubDate' ).text() );
							creator.push( $( this ).find( 'dc\\:creator, creator' ).text() );
							guid.push( $( this ).find( 'guid' ).text() );
							postID.push( $( this ).find( 'wp\\:post_id, post_id' ).text() );
							postDate.push( $( this ).find( 'wp\\:post_date, post_date' ).text() );
							postDateGMT.push( $( this ).find( 'wp\\:post_date_gmt, post_date_gmt' ).text() );
							commentStatus.push( $( this ).find( 'wp\\:comment_status, comment_status' ).text() );
							pingStatus.push( $( this ).find( 'wp\\:ping_status, ping_status' ).text() );
							postName.push( $( this ).find( 'wp\\:post_name, post_name' ).text() );
							status.push( $( this ).find( 'wp\\:status, status' ).text() );
							postParent.push( $( this ).find( 'wp\\:post_parent, post_parent' ).text() );
							menuOrder.push( $( this ).find( 'wp\\:menu_order, menu_order' ).text() );
							postType.push( xml_post_type );
							postPassword.push( $( this ).find( 'wp\\:post_password, post_password' ).text() );
							isSticky.push( $( this ).find( 'wp\\:is_sticky, is_sticky' ).text() );
						}
					});
					
					var pbMax = postType.length;

					$( function(){
					    progressBar.progressbar({
					        value:0,
					        max: postType.length,
					        change: function(){
					            progressLabel.text( aiL10n.importing + progressBar.progressbar( "value" ) + "/" + pbMax );
					        },
					        complete: function(){
					            progressLabel.text( aiL10n.done );
					        }
					    });
					});

					function import_attachments( i ){
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'attachment_importer_upload',
								_ajax_nonce: aiSecurity.nonce,
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
							if( obj.type == "error" && !obj.fatal ){
    							    $( '<p>' + obj.text + '</p>' ).appendTo( divOutput );
    					    }
							i++;
							progressBar.progressbar( "value", progressBar.progressbar( "value" ) + 1 );
							if( obj.fatal ){
							    progressBar.progressbar( "value", pbMax );
							    progressLabel.text( aiL10n.fatalUpload );
							    $( '<div class="' + obj.type + '">' + obj.text +'</div>' ).appendTo( divOutput );
							    return false;
							} else if( postType[i] ){
								setTimeout( function(){
									import_attachments( i )
								}, delay );
							} else {
								return false;
							}

						})
						.fail(function( xhr, status, error ){
							console.error(status);
							console.error(error);
							progressBar.progressbar( "value", pbMax );
							progressLabel.text( aiL10n.pbAjaxFail );
							$( '<div class="error">' + aiL10n.ajaxFail +'</div>' ).appendTo( divOutput );
						});
					}
					
					import_attachments( 0 );
					
				}
				
			}
			
		});
		
	}
	
});
