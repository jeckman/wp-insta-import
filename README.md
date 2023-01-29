# wp-insta-import
Script for use with wp-cli eval-file to import a json formatted instagram export 

Usage:
- Request and download your instagram export from instagram
- unzip, rename the root folder to "insta" and upload to your WordPress root 
- set author, categories, and limit in the first few lines of insta.php 
- run "wp eval-file insta.php" in the same directory that "insta" folder is located in

Notes:
- Only does "posts" right now. 
- Copies the media into the WP Upload directory, attaches them to the posts, sets the first attachment as the featured image (if it is an image). 
- Puts all posts in one category
- If the post has no title, adds the date ("Sunday, January 29th" style) as a title
- Parses hashtags in the instagram post content and makes them tags on WordPress posts, stripping them from the body 

