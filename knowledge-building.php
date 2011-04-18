<?php
/*
Plugin Name: Knowledge Building
Plugin URI: http://fle4.uiah.fi/kb-wp-plugin
Description: Use post comment threads to facilitate meaningful knowledge building discussions. Comes with several knowledge type sets (eg. progressive inquiry, six hat thinking) that can be used to semantically tag comments, turning your Wordpress into a knowledge building environment. Especially useful in educational settings.
Version: 0.5.5
Author: Tarmo Toikkanen
Author URI: http://tarmo.fi
*/

/*  Copyright 2009-2011  Tarmo Toikkanen  (email : tarmo@iki.fi)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

global $knbu_db_version;
$knbu_db_version='0.12';

/*
 * This code snippet loads the XML specifications of KB typesets into memory.
 * Uses wp_cache to optimize performance.
 */
$knbu_kbsets = wp_cache_get('knbu_kbsets');
if ( $knbu_kbsets == false ) {
	$knbu_kbsets = array();
# Read available KBset xml files into memory
	$kbset_dir = WP_PLUGIN_DIR.DIRECTORY_SEPARATOR."knowledge-building".DIRECTORY_SEPARATOR."kbsets";
	$d = dir($kbset_dir);
	while ( false != ($entry = $d->read()) ) {
		if ( ereg('\.xml$',strtolower($entry)) ) {
			$fname = explode('.',$entry);
			$fname = $fname[0];
			$knbu_kbsets[$fname]=simplexml_load_file($kbset_dir.DIRECTORY_SEPARATOR.$entry);
		}
	}
	wp_cache_set('knbu_kbsets',$knbu_kbsets);
}

/**
 * Hooked into plugin activation.
 *
 * Sets up additional database table and necessary options.
 */
function knbu_install() {
	global $wpdb, $knbu_db_version;

	add_option('knbu_categories');

	$table_name = $wpdb->prefix . 'knowledgetypes';
	$installed_version = get_option('knbu_db_version');
	if ( $installed_version != $knbu_db_version || $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {
		$sql = "CREATE TABLE $table_name (
      comment_id BIGINT NOT NULL,
      kbtype tinytext NOT NULL,
      PRIMARY KEY  ( comment_id ));";
		require_once(ABSPATH . 'wp-admin'.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'upgrade.php');
		dbDelta($sql);
		add_option('knbu_db_version', $knbu_db_version);
	}
}
register_activation_hook(__FILE__,'knbu_install');

add_action('admin_menu', 'knbu_plugin_menu');

/**
 * Register option page for this plugin.
 */
function knbu_plugin_menu() {
	add_options_page('Knowledge Building Plugin Options', 'Knowledge Building', 8, __FILE__, 'knbu_plugin_options');
	// Temporarily added installation hook here, since register_activation_hook doesn't work properly
	knbu_install();
}

/**
 * Define option page for this plugin.
 *
 * This option page is used to map KB typesets to post categories.
 */
function knbu_plugin_options() {
	global $knbu_kbsets;
	$hidden = 'knbu_form_posted';

# Update options if form is submitted
	if ( $_POST[$hidden] == 'Y' ) {
		$sels = array();
		foreach ( $knbu_kbsets as $file => $xml ) {
			$fname = explode('.',$file);
			$fname = $fname[0];
			foreach ( get_categories(array('hide_empty' => 0)) as $name => $cat ) {
				$value = $_POST['cat_' . $cat->cat_ID];
				$sels[$cat->cat_ID] = $value;
			}
		}
		update_option('knbu_categories',$sels);
	}
	$sels = get_option('knbu_categories'); ?>
		<div class="wrap">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
			<input type="hidden" name="<?php echo $hidden; ?>" value="Y"/>
			<table class="form-table">
			<tr valign="top">
			<th scope="row">Select Knowledgetype Sets to use with each Category</th>
			<td>
			<table>
			<tr>
			<th></th>
			<?php foreach ( get_categories(array('hide_empty' => 0)) as $name => $cat ) { ?>
				<th><?php echo $cat->name; ?></th>
			<?php } ?>
			</tr>
			<tr>
				<th>None</th>
				<?php foreach ( get_categories(array('hide_empty' => 0)) as $name => $cat ) { ?>
					<td><input type="radio" name="<?php echo 'cat_' . $cat->cat_ID;?>" value="" <?php if (!$sels[$cat->cat_ID]) echo 'checked="checked"'; ?>/></td>
				<?php } ?>
			</tr>
			<?php foreach ( $knbu_kbsets as $file => $xml ) {
				$fname = explode('.',$file);
				$fname = $fname[0];
				?>
			 	<tr>
				<th><?php echo $xml->KnowledgeTypeSet['Name']; ?>
				</th>
				<?php foreach ( get_categories(array('hide_empty' => 0)) as $name => $cat ) { ?>
					<td><input type="radio" name="<?php echo 'cat_' . $cat->cat_ID; ?>" value="<?php echo $fname; ?>" <?php if ($sels[$cat->cat_ID]==$fname) echo 'checked="checked"'; ?>/></td>
				<?php } ?>
				</tr>
			<?php } ?>
			</table>
			</td>
			</tr>
			</table>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes'); ?>" />
			</p>
			</form>
		</div>
		<?php
}

/**
 * Retrieve the KB typeset used for the current post.
 *
 * If a post belongs to multiple categories, this function will return the
 * KB typeset of the last category (as returned by get_the_category) that
 * has a KB typeset mapped to it.
 *
 * @uses get_the_category
 * @return string Found KB typeset or false if not found.
 */
function knbu_get_kbset_for_post() {
	global $post;
	$kbset = false;
	$sels = get_option('knbu_categories');
	foreach ( get_the_category($post->ID) as $cat ) {
		$value = $sels[$cat->cat_ID];
		if ( $value ) $kbset = $value;
	}
	return $kbset;
}

/**
 * Retrieve the KB type for a comment.
 *
 * Note that this needs to be called inside the Wordpress loop, so that the
 * global $post points to the correct post. The comment passed as a parameter
 * of course should be a comment to that post.
 *
 * @uses knbu_get_kbset_for_post
 * @param  object  $comment  The comment whose KB type is needed
 * @return array             The KB type for the comment
 */
function knbu_get_ktype_for_comment($comment) {
	global $knbu_kbsets,$post;
	$kbset = knbu_get_kbset_for_post();
	$kbtypes = $knbu_kbsets[$kbset]->KnowledgeTypeSet[0]->KnowledgeType;
	foreach ( $kbtypes as $ktype ) {
		if ( $ktype['ID'] == $comment->ktype ) return $ktype;
	}
	return false;
}

add_filter('comment_save_pre', 'knbu_store_comment');
add_action('comment_post', 'knbu_store_comment');
/**
 * Store comment's KB set information.
 *
 * This function is hooked to comment_post action and comment_save_pre filter.
 *
 * @param mixed   $comment  Either a numeric comment ID or a comment Array
 */
function knbu_store_comment($comment) {
	global $wpdb;
	if ( !isset( $_POST['knbu_ktype'] ) ) return;
	$ktype = $_POST['knbu_ktype'];
	if (is_numeric($comment))
		$cid = $comment;
	else
		$cid = $comment['comment_post_ID'];
	$table_name = $wpdb->prefix . 'knowledgetypes';
	$result = $wpdb->query( $wpdb->prepare("SELECT * FROM $table_name WHERE comment_id = %d;", $cid ) );
	if ($result)
		$wpdb->query( $wpdb->prepare("UPDATE $table_name SET kbtype = %s;", $ktype ) );
	else
		$wpdb->query( $wpdb->prepare("INSERT INTO $table_name (comment_id,kbtype) VALUES(%d,%s);", $cid, $ktype ) );
}

add_action('comments_array', 'knbu_fetch_ktypes', 10, 2);
/**
 * Fetch the KB types for all comments.
 *
 * This function is hooked to the coments_array action. It retrieves all the
 * KB type information for the comments of the specified post.
 *
 * @param  array  $comments  Comments that should be populated with KB type information
 * @param  int    $post_id   Post ID whose comments are scanned.
 * @return array             Comments that are populated with KB type information
 */
function knbu_fetch_ktypes($comments, $post_id) {
	global $wpdb;
	$result = $wpdb->get_results( $wpdb->prepare(
		"SELECT kb.comment_id, kb.kbtype FROM " . $wpdb->prefix . "knowledgetypes kb, " .
		$wpdb->prefix . "comments c " .
		"WHERE c.comment_post_ID = %d AND c.comment_ID = kb.comment_id " .
		"ORDER BY kb.comment_id ASC;", $post_id ) );
	for($i=0;$i<count($result);$i++) {
		foreach ( $comments as $index => $comment )  {
			if ($comment->comment_ID == $result[$i]->comment_id) {
				$comment->ktype = $result[$i]->kbtype;
			}
		}
	}
	return $comments;
}

/**
 * Alternative tag for showing comments.
 *
 * Loads kbtype information and uses a custom walker. This tag should be added to
 * the comments.php template, replacing 'wp_list_comments'.
 *
 * @uses knbu_get_kbset_for_post
 * @uses knbu_fetch_ktypes
 * @uses wp_list_comments
 * @uses Walker_KB
 * @param array  $args      Optional arguments
 * @param array  $comments  Comments that should be listed
 */
function knbu_list_comments($args = array(), $comments = null) {
	global $wp_query;

?>
<div id="comment_sorter">
Show notes
<ul>
<li>as thread</li>
<li>by knowledge type</li>
<li>by person</li>
<li>by date</li>
</ul>
</div>
<?php

	$kbtype = knbu_get_kbset_for_post();
	if ( !$kbtype ) {
		wp_list_comments();
	} else {
		$comments = knbu_fetch_ktypes($wp_query->comments,$wp_query->post->ID);
		wp_list_comments(array('walker'=>new Walker_KB),$comments);
	}
}

/**
 * Walker customized to display KB information for comments. 
 *
 * This Walker code is essentially a copy of Walker_Comment, but extended to
 * display custom comment KB types.
 */
class Walker_KB extends Walker_Comment {
	/**
	 * @see Walker_Comment::start_el()
	 * @since unknown
	 *
	 * Overrides Walker_Comment's start element method. Most of the method is copy-paste,
	 * we just add additional metadata and style information.
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $comment Comment data object.
	 * @param int $depth Depth of comment in reference to parents.
	 * @param array $args
	 */
	function start_el(&$output, $comment, $depth, $args) {
		$depth++;
		$GLOBALS['comment_depth'] = $depth;

		if ( !empty($args['callback']) ) {
			call_user_func($args['callback'], $comment, $args, $depth);
			return;
		}

		$GLOBALS['comment'] = $comment;
		extract($args, EXTR_SKIP);

		$ktype = knbu_get_ktype_for_comment($comment);

		if ( 'div' == $args['style'] ) {
			$tag = 'div';
			$add_below = 'comment';
		} else {
			$tag = 'li';
			$add_below = 'div-comment';
		}
			?>
				<<?php echo $tag ?> <?php comment_class(empty( $args['has_children'] ) ? '' : 'parent') ?> id="comment-<?php comment_ID() ?>">
				<?php if ( 'ul' == $args['style'] ) : ?>
				<div id="div-comment-<?php comment_ID() ?>" class="comment-body <?php echo ' ' . $ktype['Colour']; ?>">
				<?php endif; ?>
				<div class="kbtype-label"><?php echo $ktype['Name']; ?></div>
				<div class="comment-author vcard">
				<?php if ($args['avatar_size'] != 0) echo get_avatar( $comment, $args['avatar_size'] ); ?>
				<?php printf(__('<cite class="fn">%s</cite> <span class="says">says:</span>'), get_comment_author_link()) ?>
				</div>
<?php if ($comment->comment_approved == '0') : ?>
				<em><?php _e('Your comment is awaiting moderation.') ?></em>
				<br />
<?php endif; ?>
				<div class="comment-meta commentmetadata"><a href="<?php echo htmlspecialchars( get_comment_link( $comment->comment_ID ) ) ?>"><?php printf(__('%1$s at %2$s'), get_comment_date(),  get_comment_time()) ?></a><?php edit_comment_link(__('(Edit)'),'&nbsp;&nbsp;','') ?></div>

				<?php comment_text() ?>

				<div class="reply">
				<?php comment_reply_link(array_merge( $args, array('add_below' => $add_below, 'depth' => $depth, 'max_depth' => $args['max_depth']))) ?>
				</div>
				<?php if ( 'ul' == $args['style'] ) : ?>
				</div>
				<?php endif; ?>
   <?php
	}
}

add_action('wp_print_styles', 'knbu_custom_stylesheet');
/**
 * Add custom stylesheets to style queue.
 *
 * Hooked to wp_print_styles action.
 */
function knbu_custom_stylesheet() {
	$myStyleUrl = WP_PLUGIN_URL . '/knowledge-building/style.css';
	$myStyleFile = WP_PLUGIN_DIR . '/knowledge-building/style.css';
	if ( file_exists($myStyleFile) ) {
		wp_enqueue_style( 'myStyleSheets', $myStyleUrl);
	}
	wp_enqueue_style('jquery-simpledialog', WP_PLUGIN_URL . '/knowledge-building/jquery.simpledialog/simpledialog.css');
}

add_action('wp_print_scripts', 'knbu_script_load');
/**
 * Add custom javascript files when needed.
 *
 * Hooked to wp_print_scripts action. Only adds scripts when displaying comments.
 */
function knbu_script_load() {
	if ( comments_open() && ( is_single() || is_page() ) ) {
		wp_enqueue_script('jquery-simpledialog', WP_PLUGIN_URL . '/knowledge-building/jquery.simpledialog/simpledialog.js', array('jquery'));
		wp_enqueue_script('knbu', WP_PLUGIN_URL . '/knowledge-building/knowledgebuilding.js', array('jquery','jquery-color') );
	}
}

add_action('init','knbu_ajax_hook');
/**
 * Ajax hook.
 *
 * Hooked to init action to insert our own ajax handler to the wp action
 * whenever the 'knbu_ktype_info' variable is detected.
 */
function knbu_ajax_hook() {
	if ( isset( $_POST['knbu_ktype_info'] ) ) add_action('wp','knbu_ajax_ktype_provider');
}
/**
 * Ajax method called by comment form.
 *
 * Hooked to wp action by knbu_ajax_hook. Will die after sending information, so
 * it only returns the json formatted data.
 *
 * @uses knbu_get_kbset_for_post
 * @uses json_encode
 */
function knbu_ajax_ktype_provider() {
	global $knbu_kbsets;
	$kbset = knbu_get_kbset_for_post();
	$requested = $_POST['knbu_ktype_info'];
	foreach ( $knbu_kbsets[$kbset]->KnowledgeTypeSet[0]->KnowledgeType as $ktype ) {
		if ( $ktype['ID'] == $requested ) {
			$cl = (string)$ktype->Checklist;
			die(json_encode(array('name' => (string)$ktype['Name'],
								  'color' => (string)$ktype['Colour'],
								  'phrases' => (string)$ktype->StartingPhrase,
								  'description' => nl2br((string)$ktype->Description),
								  'checklist' => nl2br($cl))));
		}
	}
}


add_action('comment_form', 'knbu_comment_form');
/**
 * Displays custom additions to comment editing form.
 *
 * Hooked into the comment_form action. If the current post's categories do not have
 * KB typesets mapped to them, returns without doing anything, so the normal comment
 * form is displayed. For KB enabled posts, adds cognitive scaffolding.
 *
 * @param int $post_ID  The post for which scaffolding should be displayed.
 */
function knbu_comment_form($post_ID) {
	global $knbu_kbsets,$post;
	$kbtype = knbu_get_kbset_for_post();
	if ( !$kbtype ) return;
?>
	<div id="knbu" style="display:none;">
		<div id="knbu_scaffold" style="display:none;">
			<p id="knbu_heading" class="ktype_heading">Knowledge type</p>
			<div id="knbu_checklist">Checklist</div>
			<div id="knbu_popup" style="display:none;"><h3 id="knbu_popup_heading">Knowledge type</h3><p id="knbu_popup_p">Description</p></div>
			<span id="knbu_selector2"><select id="knbu_ktype2">
				<?php foreach ( $knbu_kbsets[$kbtype]->KnowledgeTypeSet[0]->KnowledgeType as $ktype ) {
					echo '<option value="' . $ktype['ID'] . '">' . $ktype['Name'] . '</option>';
				} ?>
			</select></span>
			<input id="knbu_select2" type="button" value="Change"/>
			<a href="#" id="knbu_popper" rel="knbu_popup">Details...</a>
		</div>
		<p id="knbu_init">
			<span id="knbu_selector">Select <select id="knbu_ktype">
				<option value="">knowledge type</option>
				<?php foreach ( $knbu_kbsets[$kbtype]->KnowledgeTypeSet[0]->KnowledgeType as $ktype ) {
					echo '<option value="' . $ktype['ID'] . '">' . $ktype['Name'] . '</option>';
				} ?>
				</select>
			</span>
			<input id="knbu_select" type="button" value="Add comment"/>
		</p>
		<input id="knbu_real_ktype" type="text" style="display:none;"" name="knbu_ktype" value=""/>
	</div>
<?php
}

?>
