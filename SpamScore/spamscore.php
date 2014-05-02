<?php
  /*
Plugin Name: SpamScore
Plugin URI: http://blog.altgilbers.com/SpamScore
Description: Rates blogs on a wordpress network install based on how vulnerable they are to spam
Version: 0.1
Author: ian@altgilbers.com
Author URI: http://blog.altgilbers.com
  */


add_action('admin_init', 'SpamScore_admin_init');
function SpamScore_admin_init(){
  register_setting( 'SpamScore_options', 'SpamScore_options', 'SpamScore_options_validate' );
  add_settings_section('SpamScore_main', 'Main Settings', 'SpamScore_section_text', 'SpamScore_options');
  add_settings_field('SpamScore_text_string', 'AntiSpam Plugin', 'SpamScore_setting_string', 'SpamScore_options','SpamScore_main');
}

add_action('admin_menu', 'SpamScoreOptionMenu');
function SpamScoreOptionMenu(){
  if (is_site_admin()){
    add_options_page('SpamScoreOptions', 'SpamScore', 'site_administrator', 'SpamScore','SpamScoreOptionsPage');
  }
}

function SpamScore_section_text() {
  echo '<p>Settings for SpamScore Plugin.</p>';
}

function SpamScore_setting_string() {
  $options = get_option('SpamScore_options');
  echo "<input id='SpamScore_text_string' name='SpamScore_options[text_string]' size='40' type='text' value='{$options['text_string']}' />";
}

function SpamScore_options_validate($input) {
  $options = get_option('SpamScore_options');
  $options['text_string'] = trim($input['text_string']);
  //  if(!preg_match('/^[a-z0-9]{32}$/i', $options['text_string'])) {
  //  $options['text_string'] = '';
  //  }
  return $options;
}

  
function SpamScoreOptionsPage(){
  global $wpdb;
?>
<html>
<head>
<link rel="stylesheet" type="text/css" href="wp-content/plugins/SpamScore/css/jquery-ui.css" />
<script type="text/javascript" src="wp-content/plugins/SpamScore/js/jquery-1.7.min.js"></script>
<script type="text/javascript" src="wp-content/plugins/SpamScore/js/jquery.dataTables.min.js"></script>
<script type="text/javascript">
   $(document).ready(function(){
		       $(function() {
			   $( "#spamscore" ).dataTable({ "aLengthMenu": [[100,50,25,-1], [100,50,25,"All"]]});
			 });
		     });
</script>
</head>
<body>
  <div class="wrap">
<h2> Spam Vulnerability Score </h2>

<form action="options.php" method="post">
    <?php settings_fields('SpamScore_options'); ?>
    <?php do_settings_sections('SpamScore_options'); ?>
<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
</form>
<h2>Results</h2>
      <table id="spamscore" class="ui-widget">
      <thead class="ui-widget-header">
      <tr><th>blog_id</th><th>url</th><th>score</th><th>posts</th><th>comments approved</th><th>comments pending</th><th>comments spam</th></tr>
      </thead>
      <tbody class="ui-widget-content">
<?php
   // antispam plugin
   $antispam_plugin=get_option("SpamScore_options");
  $all_blogs = $wpdb->get_results("select blog_id from wp_blogs where site_id='1' and deleted='0' and spam='0'"); 
  foreach($all_blogs as $blog)
    {
      $j=$blog->blog_id;
      $siteurl=get_blog_option($j,"siteurl");
      $all_details=get_blog_details($j);
      
      // post count
      $post_count=get_blog_option($j, "post_count");

      // comment count
      $approved_comments = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM wp_".$j."_comments where comment_approved=1;" ) );
      $pending_comments = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM wp_".$j."_comments where comment_approved=0;" ) );
      $spam_comments = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM wp_".$j."_comments where comment_approved = 'spam';" ) );

      // the blog's public setting (1,0,-1,-2,-3) 
      $public=$all_details->public;  


      // are all comments moderated (0,1) 
      $comment_moderation=get_blog_option($j,"comment_moderation");

      // are white listed users allowed to post (0,1) 
      $comment_whitelist=get_blog_option($j,"comment_whitelist");

      // are new posts open to comments by default ("open", "closed")
      $default_comment_status=get_blog_option($j,"default_comment_status");

      // must users be registered and logged in to post comments (0,1) 
      $comment_whitelist=get_blog_option($j,"comment_registration");
      
      // are comments closed for old posts?
      $comment_oldpost=get_blog_option($j,"close_comments_for_old_posts");

      // active plugins
      $active_plugins=get_blog_option($j,"active_plugins");

      $a_score=0;
      $m_score=0;

      // accessibility score - is the blog published?
      if ($public<-1)
	$a_score=-20;
      else if($public==-1)
	$a_score=-10;
      else if($public==1)
	$a_score=10;
      else
	$a_score=5;
 
	  
      if ($comment_moderation != 1)  // if moderation is disabled
	$m_score+=10;
      else
	$m_score+=2;
      if ($default_comment_status == "closed")
	$m_score/=2;
      if ($comment_whitelist != 1)  // if whitelist is disabled
	$m_score+=5;
      if ($comment_registration == 1) // if unregistered users can comment
	$m_score/=4;
      foreach($active_plugins as $plugin)
	if (strstr($plugin,"akismet.php"))
	  $m_score/=4;
      $score=0;
      $score=$a_score*$m_score;

      if ($public >= 0)
	if ($comment_moderation != 1)
	  if ($comment_whitelist != 1)
	    foreach($active_plugins as $plugin)
	      if (strstr($plugin,$antispam_plugin['text_string']))
		$score=$score;
      if (strstr($plugin,$antispam_plugin['text_string']))
	$score=$score;
      printf("<tr><td>%d</td><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td></tr>\n",$j,$siteurl,$score,$post_count,$approved_comments,$pending_comments,$spam_comments);
    }
?>
</tbody></table>
</div>
<?php
}
?>
</body>
</html>