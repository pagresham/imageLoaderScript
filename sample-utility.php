<?php if(!defined('ABSPATH')) { die(); } // This line ensures that the script is not run directly
/**
 * Utility Name: SAMPLE Utility
 * Description: SAMPLE UTILITY to explain the basic structure of a Utility Script
 * Author: Burlington Bytes, LLC
 * Author URI: https://www.burlingtonbytes.com
 * Version: 1.0.0
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 **/

 // The File Header above allows the Script Runner to identify and autoload your utility

// removed from head comment // * Supports: input

// this filter contains the actual meat and potatoes of your script
// ---
// $legacy will always be an empty string, but it needed to support a
// legacy version of the utility script format
// ---
// $state is an aritrary value you can return from the previous run of the script,
// and which will be passed through to the next run. One common use is to
// store an offset for paginated database queries. State will be falsy for the
// initial run. It is recommended to store data in state as keys in an array, to
// ensure no overlap with the reserved values of 'complete' and 'error' which
// trigger exiting the script
// ---
// $atts is an array, containing your input form fields, by name, EXCEPT file inputs
// ---
// $files contains an array of any file inputs that were included in the input form
// ---
function bbytes_refactor_image_url( $legacy, $state, $atts, $files ) {
	// scripts must return a state and a message, in an array
	// ---
	// if state is not equal to 'complete' or 'error', the script will be
	// triggered again, with state passed to the $state variable.
	// this allows you to create scripts that will take longer than
	// PHP_MAX_EXECUTION_TIME to fully complete
	// ---
	// The contents of message will be output to the user on each run
	

	// How to tell if I am at the end of the posts? //

	$step = 5;
	$atts;
	$count;
	$posts_seen;
	$return_state = array();
	$offset;


	// If no $state, then first time through, make offset 0, and posts_seen 0
	// $offset = ( $state && isset( $state['offset'] )) ? $state['offset'] : 0;
	
	
	if( ! $state ) {
		// First time through - set posts_seen and count
		$posts = get_posts(array(
			'posts_per_page' => -1,
			'post_type' => 'post',
			'post_status' => 'publish'
		));
		$offset = 0;
		$posts_seen = 0;
		$count = count( $posts ) ? count( $posts ) : 0;

	} else if( isset($state['offset']) && isset($state['posts_seen']) && isset($state['count']) ) {
		$offset = $state['offset'] + $step;
		$count = $state[ 'count' ];
		$posts_seen = $state[ 'posts_seen' ];
	}

	// write_log(array(
	// 	"message" => "Initial Values",
	// 	"offset" => $offset,
	// 	'count' => $count,
	// 	'posts_seen' => $posts_seen
	// ));
	


	// get the batch of posts to process //
	$args = array(
		'posts_per_page' => $step,
		'offset'	     => $offset,
	);
	$batch_posts = get_posts( $args );
	
	
	foreach ($batch_posts as $post) {
		$post_id = $post->ID;

		$tags = extract_tags( $post->post_content, 'img', true, true );
		$unique_tags = array();
		$ids = array();


		foreach ($tags as $tag) {

			
			list( $id, $url ) = get_id_and_url_from_tag( $tag ); 
			if( $url && $id ) {
				// do the side load, and get the new url
				$desc = "";
				////////
				// $new_id = "xxxx";
				// $new_url = "https://fakeurl.com";
				$new_id = media_sideload_image($url, $post_id, $desc, 'id');
				$new_url = wp_get_attachment_url( $new_id );
				


				$attributes = $tag['attributes'];
				
				$tag['id'] = $id;
				$tag['new_id'] = $new_id;
				// $tag['size'] = $size;
				$tag['new_url'] = $new_url;
				$unique_tags[$tag['full_tag']] = $tag;

				$content;
				foreach( $unique_tags as $old_tag => $parsed_tag ) {
					$new_tag = make_new_content_img_tag( $parsed_tag );
					if( $new_tag ) {
						$content = str_replace( $old_tag, $new_tag, $post->post_content );
						write_log($new_tag);
						write_log($old_tag);
					}
				}

				// write_log($new_content);
				$my_post = array(
					'ID'           => $post_id,
			        'post_content' => $content,
				);
				if( $content ) {
					//////
					wp_update_post( $my_post );	
				}
			}
		}
	}
	


	// check how many posts have been read
	$batch_count = count($batch_posts) ? count($batch_posts) : 0;
	$posts_seen = $batch_count + $posts_seen;
	
	// $atts = [ 'count' => $count, 'posts_seen' => $posts_seen, 'offset' => $offset ];

	// // seen all of the posts //
	if( $posts_seen >= $count) {		
		$return_state = 'complete';

		write_log(array(
			"message" => "Ending Values",
			"offset" => $offset,
			'count' => $count,
			'posts_seen' => $posts_seen
		));

	} else {
		$return_state = array(
			'offset'     => $offset,
			'count'      => $count,
			'posts_seen' => $posts_seen
		);
	}
	
	return array(
		// 'state'   => 'complete',
		'state'   => $return_state,
		// 'message' => "HELLO WORLD!\nAtts Were:\n" . json_encode( $atts ),
		'message' => "HELLO WORLD!\nAtts Were:\n" . json_encode( $return_state ),
	);
}
add_filter('wp_util_script', 'bbytes_refactor_image_url', 10, 4);





/**
 * extract_tags 
 */
function extract_tags( $html, $tag, $selfclosing = null, $return_the_entire_tag = false, $charset = 'ISO-8859-1' ){
	if ( is_array($tag) ){
		$tag = implode('|', $tag);
	}
	//known self-closing tabs
	$selfclosing_tags = array( 'area', 'base', 'basefont', 'br', 'hr', 'input', 'img', 'link', 'meta', 'col', 'param' );
	if ( is_null($selfclosing) ){
		$selfclosing = in_array( $tag, $selfclosing_tags );
	}
	//The regexp is different for normal and self-closing tags because I can't figure out
	//how to make a sufficiently robust unified one.
	if ( $selfclosing ){
		$tag_pattern =
			'@<(?P<tag>'.$tag.')           # <tag
			(?P<attributes>\s[^>]+)?       # attributes, if any
			\s*/?>                   # /> or just >, being lenient here
			@xsi';
	} else {
		$tag_pattern =
			'@<(?P<tag>'.$tag.')           # <tag
			(?P<attributes>\s[^>]+)?       # attributes, if any
			\s*>                 # >
			(?P<contents>.*?)         # tag contents
			</(?P=tag)>               # the closing </tag>
			@xsi';
	}
	$attribute_pattern =
		'@
		(?P<name>\w+)                         # attribute name
		\s*=\s*
		(
			(?P<quote>[\"\'])(?P<value_quoted>.*?)(?P=quote)    # a quoted value
			|                           # or
			(?P<value_unquoted>[^\s"\']+?)(?:\s+|$)           # an unquoted value (terminated by whitespace or EOF)
		)
		@xsi';
	//Find all tags
	if ( !preg_match_all($tag_pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ){
		//Return an empty array if we didn't find anything
		return array();
	}
	$tags = array();
	foreach ($matches as $match){
		//Parse tag attributes, if any
		$attributes = array();
		if ( !empty($match['attributes'][0]) ){
			if ( preg_match_all( $attribute_pattern, $match['attributes'][0], $attribute_data, PREG_SET_ORDER ) ){
				//Turn the attribute data into a name->value array
				foreach($attribute_data as $attr){
					if( !empty($attr['value_quoted']) ){
						$value = $attr['value_quoted'];
					} else if( !empty($attr['value_unquoted']) ){
						$value = $attr['value_unquoted'];
					} else {
						$value = '';
					}
					$attributes[$attr['name']] = $value;
				}
			}
		}
		$tag = array(
			'tag_name'   => $match['tag'][0],
			'offset'     => $match[0][1],
			'contents'   => !empty($match['contents'])?$match['contents'][0]:'', //empty for self-closing tags
			'attributes' => $attributes,
		);
		if ( $return_the_entire_tag ){
			$tag['full_tag'] = $match[0][0];
		}
		$tags[] = $tag;
	}

	return $tags;
}


function get_id_and_url_from_tag( $tag ) {
	$ret_val = array();
	if( isset( $tag['attributes'] ) && isset( $tag['attributes']['class'] ) && $tag['attributes']['class'] && isset( $tag['attributes']['src'])) {
		$url = $tag['attributes']['src'];
		$class_tag = $tag['attributes']['class'];


		$classes     = explode( ' ', $tag['attributes']['class'] );
		$id_prefix   = 'wp-image-';
		$size_prefix = 'size-';
		

		// $ret_val = array( false, false );
		foreach( $classes as $class ) {
			// if( !$ret_val[0] && strpos( $class, $id_prefix ) === 0 ) {
			// 	$ret_val[0] = intval( substr( $class, strlen( $id_prefix ) ) );
			// } elseif( !$ret_val[1] && strpos( $class, $size_prefix ) === 0 ) {
			// 	$ret_val[1] = substr( $class, strlen( $size_prefix ) );
			// } elseif( $ret_val[0] && $ret_val[1] ) {
			// 	break;
			// }
			
			// Find the id# from the class tag 
			if( strpos( $class, $id_prefix ) === 0 ) {
				$ret_val[0] = intval( substr( $class, strlen( $id_prefix ) ) );
				break;
			} else {
				$ret_val[0] = false;
			}

		}
		$ret_val[1] = $url;
	
	}
	write_log($ret_val);
	return $ret_val;
}

if (!function_exists('write_log')) {
    function write_log ( $log )  {
        if ( true === WP_DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
        }
    }
}

function make_new_content_img_tag( $tag ) {
	// write_log("tag");
	// write_log($tag);

	// write_log($tag['attributes']);
	$atts = $tag['attributes'];
	$new_tag = $tag;

	// if issset() new_id, id, and new_url
	if( $tag && isset($tag['new_url']) && isset($tag['new_id']) )  {

		$new_url = $tag['new_url']; 
		$atts['src'] = $new_url;
		$new_id = $tag['new_id'];
		$id_prefix = 'wp-image-';
		
		// $new_class = preg_replace('/wp-image-[0-9]{4,}\b/', $id_prefix . $new_id, $atts['class']); 
		
		$atts['class'] = preg_replace('/wp-image-[0-9]{4,}\b/', $id_prefix . $new_id, $atts['class']); 
		
		if( $new_class ) {
			$atts['class'] = $new_class;	
		}

		// $size = $tag['size'];
		// write_log($atts['src']);
		// write_log($atts['class']);
		
		$new_tag = '<img';
		foreach( $atts as $name => $val ) {
			$new_tag .= ' ' . $name . '="' . $val . '"';
		}
		$new_tag .= ' />';

		write_log('new_tag');
		write_log($new_tag);
	
	}

	
	return $new_tag;	
		
}
	
	
	




