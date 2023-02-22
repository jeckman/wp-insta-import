# wp-insta-import
Script for use with [wp-cli eval-file](https://developer.wordpress.org/cli/commands/eval-file/) to import a json formatted instagram export 

Usage:
- Request and download your instagram export from instagram ([directions](https://help.instagram.com/181231772500920)]
- unzip, rename the root folder to "insta" and upload to your WordPress root 
- set author, categories, limit, and post status in the first few lines of insta.php 
- run "wp eval-file insta.php" in the same directory that "insta" folder is located in

Notes:
- Only imports Instagram "posts" and "stories" right now (not "reels"). 
- Creates WordPress posts for Instagram posts (does not create a custom post type). 
- Copies the media into the WP Upload directory, attaches them to the posts, sets the first attachment as the featured image (if it is an image). 
- Puts all posts in one category. 
- If the post has no title, adds the date ("Sunday, January 29th" style) as a title.
- Parses hashtags in the instagram post content and makes them tags on WordPress posts, stripping them from the body 

Caution: If you have anything set to publicize when you make new posts, use caution in publishing many posts at once! 

TODOS:
- Also import "reels" 
- Make into a standalone plugin not dependent on wp-cli eval-file 
- Enable upload of the instagram zip download directly via dashboard 
- Configurability - author, categories, tags, whether to insert images or just attach to post, whether to include posts, reels, stories, etc.
- custom post type for imported instagram posts? 
