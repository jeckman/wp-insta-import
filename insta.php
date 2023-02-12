<?php 

/* assumes the root folder of the instagram download is named insta 
 * assumes the author and categories set below - categories is an array - put comma separated list of category ids there  
 * set "limit" so it only does a few at a time 
 */ 

$author = 3;  //configure with WordPress author by id
$categories = array(103);   // default category or categories, again by ID
$limit = 50;  // ho wmany stories to try to import before stopping - set to -1 for unlimited
$post_status = 'draft';  // status for posts imported - using 'draft' is helpful for debugging

/* set these to determine which things to import */
$import_posts = 0; 
$import_stories = 1;
$import_reels = 0; 


if($import_posts == 1) {

	/* open the json file */
	$json=file_get_contents('insta/content/posts_1.json');
	$json_data = json_decode($json,true); 

	/* loop through the media items in posts_1 */ 
	$count=0; 

	/* trying to allow html insertion in body */ 
	remove_all_filters("content_save_pre"); 

	foreach ($json_data as $media_item) {
		$images = []; 
		if(count($media_item['media'])==1) {
			$title = $media_item['media']['0']['title'];
			$created = $media_item['media']['0']['creation_timestamp'];
			$images['0'] = $media_item['media']['0']['uri'];
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
				$hashtags[$i]	= ltrim($hashtags[$i],$hashtags[$i][0]);
			}
		} else {
			$hashtags = array(''); 
		}

		/* take just first line as title */
		$short_title = explode("\n",$title); 

		/* if post has not title, add the date as a title */
		if($short_title[0] == '') {
			$short_title[0] = date("l, F jS, Y", $created);
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
			'tags_input' => $hashtags
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
				'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content' => '',
				'post_date' => $post_date,
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
		if ($count == $limit) {
			exit();
		}
	}  // end the loop of all menu items
} // end of if for posts 

if($import_stories == 1) {

	$count = 0;
	/* open the json file */
	$json=file_get_contents('insta/content/stories.json');
	$json_data = json_decode($json,true); 
	
	print_r($json_data);

	$wp_story_posts = array();
	$wp_story_posts_index = -1; 
	$previous_day = ''; 
	
	/* this loops through all the media items, and creates a new array of WP posts with images 
     * based on creation date - then a second loop will go through that set and post them */
	foreach ($json_data['ig_stories'] as $story) {
		$created = $story['creation_timestamp'];
		$title =  date("l, F jS, Y", $created);  // for posts on the same day this will be the same 
		if($title == $previous_day) {
			// this is a continuation from the previous post - another image/video in that post
			$wp_story_posts[$wp_story_posts_index]['images'][] = $story['uri'];
			
		} else {
			// this is a new post 
			$wp_story_posts_index++; 
			$previous_day = $title;
			$wp_story_posts[$wp_story_posts_index]['created']= $created; 
			$wp_story_posts[$wp_story_posts_index]['title'] = $title;
			$wp_story_posts[$wp_story_posts_index]['images'][] = $story['uri'];
			$wp_story_posts[$wp_story_posts_index]['body'] =  $story['title'];
		}
		
		$count++;
		// echo "End of array, wp_story_post_index is " . $wp_story_posts_index . "\n"; 
	} // end of loop to build posts array
	
	foreach($wp_story_posts as $my_post) {	
		
		/* In the case of stories, I need to first find how many media items share the same creation date
		 * then I can make a single post for that set of media items that share a date 
		 * But do this while looping through? Keep going until the date changes? 
		 * Build my own new array of posts first, then loop through them? 
		 */ 
		
		/* make the post first, then import the images */
		$post_date = date("Y-m-d H:i:s",$my_post['created']);
		$post_date_gmt = gmdate("Y-m-d H:i:s",$my_post['created']);
		$new_content = $my_post['body'];


		$wordpress_post = array(
			'post_title' => $my_post['title'],
			'post_content' => $my_post['body'],
			'post_category' => $categories,
			'post_status' => $post_status,
			'post_author' => $author,
			'post_date' => $post_date,
			'post_date_gmt' => $post_date_gmt,
			'post_type' => 'post',
		);

		// echo "Inserting post title " .$my_post['title'] . " with " . count($my_post['images']) . " images\n";
		$post_id = wp_insert_post( $wordpress_post); 

		print_r($my_post);
		/* now loop through media items in the post */
		for ($i=0; $i<count($my_post['images']); $i++) {
			/* upload the image / media item */ 
			
			echo "Uploading image " . $my_post['images'][$i] . "\n";
			
			$upload = wp_upload_bits(basename($my_post['images'][$i]),null,file_get_contents('insta/' . $my_post['images'][$i]), date('Y/m',$created));

			/* 'attach' to the post */
			$upload_type = $upload['type']; 
			$attachment = array(
				'guid'	=>	$wp_upload_dir['url'] . '/' . basename($upload['file']),
				'post_mime_type'	=>	$upload_type,
				'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content' => '',
				'post_date' => $post_date,
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

		} // done looping through attachments
		/* wp_update_post to add all the images/videos */ 
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
	}  // end of loop through wp_post_stories array 
	echo "Found " . count($wp_story_posts) . "stories \n";

} // end of import stories

if ($import_reels == 1) {

} // end of import reels 

echo "Done. Count = " . $count . "\n\n"; 
 

?>

