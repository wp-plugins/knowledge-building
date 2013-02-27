<?php
function knbu_get_knowledge_type_select() {
	global $knbu_kbsets;
	$value = '<select name="knbu_type">
	<option>Select type</option>';
	foreach($knbu_kbsets[knbu_get_kbset_for_post(get_the_ID())]->KnowledgeTypeSet->KnowledgeType as $type)
		$value .= '<option value="'.$type['ID'].'">'.$type['Name'].'</option>';
	$value .= '</select>';
	return $value;
}

$Colors = [
	'problem' => '#fcfc43',
	'my_expl' => '#4ace93',
	'sci_expl' => '#ffb42b',
	'evaluation' => '#7e3cff',
	'summary' => '#99d51a'
];
function knbu_get_legends() {
	global $knbu_kbsets, $Colors;
	foreach($knbu_kbsets[knbu_get_kbset_for_post(get_the_ID())]->KnowledgeTypeSet->KnowledgeType as $type) {
		$color = isset($Colors[(string)$type['ID']]) ? $Colors[(string)$type['ID']] : '#ffffff';
		echo '<li><span class="color" style="background-color: '.$color.'"></span>'.$type['Name'].'</li>';
	}
	echo '<li><span class="color" style="background-color: black"></span> Unspecified</li>';
}

$replies = get_comments(array(
			'status' => 'approve',
			'post_id' => get_the_ID()
			));
$knowledgeTypes = $wpdb->get_results('SELECT comment_id, kbtype FROM wp_knowledgetypes ORDER BY comment_id');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php the_title(); ?> (Map) | <?php bloginfo('name'); ?></title>
<link href='http://fonts.googleapis.com/css?family=Junge' rel='stylesheet' type='text/css'>
<?php wp_head(); ?>
</head>
<body class="knbu-map-view">
	<div id="map">
		<div id="raven"></div>
		<div id="fps"></div>
		<div id="navigation">
			<div id="zoom"></div>
			<div id="pan">
				<div class="left"></div>
				<div class="right"></div>
				<div class="up"></div>
				<div class="down"></div>
				<div class="center"></div>
			</div>
		</div>
		<div id="legend">
		<ul>
			<?php knbu_get_legends(); ?>
		</ul>
		</div>
	</div>
	<div id="message">
		<div class="message-header">
			<h4 class="message-type"></h4>
		</div>
		<div class="message-content-wrapper">
		<h3 class="message-username">Username</h3><div id="close">X</div>
		<br style="clear:both">
		<div class="message-meta">
			<div class="message-avatar"></div>
			<div class="message-date">6:43 pm 12th June 2013</div>
		</div>
		<div class="message-content-wrapper">
			<div class="message-content"></div>
		</div>
		<div class="message-coords"></div>
		<br style="clear:both">
		<?php if(is_user_logged_in()) { ?>
		<a class="reply-toggle knbu-form-link" id="open-reply">Reply</a>
		<div id="reply-wrapper">
			<form>
			<input type="hidden" value="<?php echo admin_url('admin-ajax.php'); ?>" id="admin-ajax-url">
			<input type="hidden" value="<?php echo get_the_ID(); ?>" id="post-id">
			<br style="clear:both">
				<input type="hidden" name="parent-comment" id="parent-comment-id">
				<p>Knowledge type <br><?php echo knbu_get_knowledge_type_select(); ?></p>
				<p style="clear: both">Reply<br>
				<textarea style="width: 95%" rows="8" name="comment-content"></textarea></p>
				<p><input type="button" value="Send" id="submit-reply" ></p>
			</form>
		</div>
		<?php } else { ?>
			<a href="<?php echo wp_login_url(); ?>" target="_top" class="reply-toggle knbu-form-link">Log in to reply</a>
		<?php } ?>
		</div>
	</div>
		<?php
			usort($replies, 'knbu_cmp');
			knbu_get_childs(0, $replies);
		?>
	</body>
</html>
<?php
function knbu_get_childs($id, $replies) {
	global $knowledgeTypes, $knbu_kbsets, $post;
	echo '<ul '.($id == 0 ? 'id="data" style="display: none"' : '').'
	data-username="'.get_the_author_meta('display_name', $post->post_author).'" 
	data-content="'.$post->post_content.'"
	data-avatar="'.knbu_get_avatar_url( $post->user_id ).'"
	data-username="'.get_the_author_meta( 'display_name', $post->user_id ).'"
	data-email="'.$post->user_email.'"
	>';
	foreach($replies as $reply) {
		if($reply->comment_parent == $id) {		
			$type = false;
			$name = 'Unspecified';
			foreach($knowledgeTypes as $kbtype) {
				if($kbtype->comment_id == $reply->comment_ID) {
					$type = $kbtype->kbtype;
				}
			}
			
			foreach($knbu_kbsets[knbu_get_kbset_for_post(get_the_ID())]->KnowledgeTypeSet->KnowledgeType as $t) {	
				if($t['ID'] == $type) $name = $t['Name']; 
			}
			
			echo '<li class="kbtype-'.$type.'" 
			data-id="'.$reply->comment_ID.'"
			data-kbtype="'.$type.'"
			data-kbname="'.$name.'"
			data-username="'.get_the_author_meta('display_name', $reply->user_id).'"
			data-content="'.$reply->comment_content.'"
			data-avatar="'.knbu_get_avatar_url($reply->user_id).'">';
			knbu_get_childs($reply->comment_ID, $replies);
			echo '</li>';
		}
	}
	echo '</ul>';
}


function knbu_cmp($a, $b) {
	if($a->comment_parent == $b->comment_parent) 
		return 0;
	return $a->comment_parent > $b->comment_parent ? 1 : -1;
}
?>