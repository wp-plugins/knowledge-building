
<p class="thread-sorted"><?php knbu_list_comments(); ?></p>
<br>
<div id="respond">
	<h3 id="reply-title">Leave a Reply</h3>
	
	<form action="<?php echo site_url(); ?>/wp-comments-post.php" method="post" id="commentform">
		<?php comment_id_fields(); ?>
		<?php knbu_comment_form_map(get_the_ID()); ?>
	</form>
</div>