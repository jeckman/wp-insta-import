<?php 

/* assumes the root folder of the instagram download is named insta 
 * assumes the author and categories set below - categories is an array - put comma separated list of category ids there  
 * set "limit" so it only does a few at a time 
 */ 

$author = 3;
$categories = array(103);
$limit = 50;
$post_status = 'draft';

/* open the json file */
$json=file_get_contents('insta/content/posts_1.json');
$json_data = json_decode($json,true); 

/* loop through the media items in posts_1 */ 

//print_r($json_data);

$count=0; 

/* trying to allow html insertion in body */ 
remove_all_filters("content_save_pre"); 

foreach ($json_data as $media_item) {
	$images = []; 
	if(count($media_item['media'])==1) {
		$title = $media_item['media']['0']['title'];
		$created = $media_item['media']['0']['creation_timestamp'];
		$images['0'] =  $media_item['media']['0']['uri'];
	} else {
		$title = $media_item['title'];
		$created = $media_item['creation_timestamp'];
		for ($i=0; $i< count($media_item['media']); $i++) {
			$images[$i] = $media_item['media'][$i]['uri'];
		}
	} 
	
	/* find hashtags */
	preg_match_all("/(#\w+)/",$title,$result);
	If ($result) {
		$hashtagsArray = array_count_values($result[0]);
		$hashtags = array_keys($hashtagsArray);
		/* remove hashtags */
		$title = preg_replace('/(#\w+)/',NULL,$title);	
		/* strip # from hashtags */
		for ($i=0; $i<count($hashtags); $i++) {
			$hashtags[$i]  = ltrim($hashtags[$i],$hashtags[$i][0]);
		}
	} else {
		$hashtags = array(''); 
	}

	/* take just first line as title */
	$short_title = explode("\n",$title); 

	/* if post has not title, add the date as a title */
	if($short_title[0] == '') {
		$short_title[0] = date("l, F jS", $created);
	}

	$count++;
	
	/* make the post first, then import the images */
	$post_date = date("Y-m-d H:i:s",$created);
	$post_date_gmt = gmdate("Y-m-d H:i:s",$created);

	$wordpress_post = array(
		'post_title' => $short_title[0],
		'post_content' => $title,
		'post_category' => $categories,
		'post_status' => $post_status,
		'post_author' => $author,
		'post_date' => $post_date,
		'post_date_gmt' => $post_date_gmt,
		'post_type' => 'post',
		'tag_input' => $hashtags
	);

	$post_id = wp_insert_post( $wordpress_post); 

	$new_content = $title; 

	/* loop through the images, uploading, attaching to post, making the first one a featured image */ 
	for ($i=0; $i<count($images); $i++) {
		/* copy file to upload folder */ 
		$upload = wp_upload_bits(basename($images[$i]),null,file_get_contents('insta/' . $images[$i]), date('Y/m',$created));

		/* 'attach' to the post */
		$upload_type = $upload['type']; 
		$attachment = array(
			'guid'	=>	$wp_upload_dir['url'] . '/' . basename($upload['file']),
			'post_mime_type'	=>	$upload_type,
			'post_title' =>	preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
			'post_content' => '',
			'post_date' =>  $post_date,
			'post_date_gmt' => $post_date_gmt,
			'post_status' => 'inherit'
		);

		$attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file']);
		wp_update_attachment_metadata( $attach_id, $attach_data); 
		
		/* if first attachment is an image, make it the featured thumbnail */ 
		if(($i==0)&&( substr($upload_type,0,5) == 'image' )) {
			set_post_thumbnail($post_id, $attach_id);
		}

		/* if your theme displays all attached images you could skip this */
		/* assumes video is mp4 - could be smarter to check */ 			
		if (substr($upload_type,0,5) == 'image') {
			$image_attributes = wp_get_attachment_image_src($attach_id,'large');
			$new_content = $new_content . '<p><img src="' . $image_attributes[0] .'"  width="' . $image_attributes[1] .'" height="' . $image_attributes[2] .'"/></p>';
		} else if (substr($upload_type,0,5) == 'video') {
			$video_url = wp_get_attachment_url($attach_id); 
			$new_content = $new_content . '[video  mp4="' . $video_url . '"][/video]';
		}
	}  /* end of loop through attachmentss */

	/* wp_update_post to add the images */ 
	$wordpress_post = array (
		'ID' => $post_id,
		'post_content' => $new_content,
		'post_date' => $post_date,
		'post_date_gmt' => $post_date_gmt,
		'post_modified' => $post_date,
		'post_modified_gmt' => $post_date_gmt,
	);

	/* todo: check the return value */
	$result = wp_update_post($wordpress_post);


	/* test here just to limit how many at a time */
	if ($count > $limit) {
		exit();
	}
}  // end the loop of all menu items

echo "Done. Count = " . $count . "\n\n"; 
 

?>

