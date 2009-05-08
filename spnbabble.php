<?php
/*
Plugin Name: SPNBabble
Plugin URI: http://www.themespluginswp.com/plugins/spn-babblespn-babble/
Description: Generates SPNBabble Mini Blog Updates when a new Post is Published.
Author: Darren Dunner
Version: 1.1
Author URI: http://www.themespluginswp.com/
*/

/**
 * Changelog
 *
 * 1.1	4/27/2009
 * 			Made posted to SPNBabble conditional by adding a new Config
 *			Setting.
 *
 * 1.0	4/22/2009
 *			Initial Reslease
 *
 * Compatibility
 * Wordpress Version 2.71
 * Laconica Version 0.7.2.1 (SPNBabble is powered by Laconica)
 *
 * Wordpress <http://wordpress.org>
 * Laconica <http://laconi.ca>
 * SPNBabble <http://spnbabble.sitepronews.com>
 */
 

$spnbabble_plugin_name = 'SPNBabble';
$spnbabble_plugin_prefix = 'spnbabble_';

// Full URI of SPNBabble without trailing slash
define('SPNBABBLE_URI', 'http://spnbabble.sitepronews.com');
define('SPN_API_POST_STATUS', SPNBABBLE_URI.'/api/statuses/update.json');
define('G_VERSION', '1.1');

add_action('publish_post', 'postPublished');

/**
 * Post the blog entry to SPNBabble.
 * The post must be 'published'.
 *
 * This is how the the plugin will post the update to SPNBabble:
 * 1)	A blog post is published (whether it is a New post or we are Updating
 *		and existing post.)
 * 2)	If the Custom Field 'enable_babble' does not exist then we
 *		add it with a value of 'no' (this will prevent the plugin from
 *		automaticaly posting an update to SPNBabble.
 *	3) if the Custom Field 'enable_babble' equals 'yes' then we
 *		check the value of the Custom Field 'has_been_babbled'.
 * 3)	If 'has_been_babbled' does not equal 'yes' then we post an
 *		update to SPNBabble. and add the Custom Field 'has_been_babbled' 
 *		and set its value to 'yes'
 * 5) To post a follow-up update to SPNBabble whenever the blog post
 *		is updated, we can either delete the Custom Field 'has_been_babbled'
 *		or set its value to something other than 'yes' and then save the blog
 *		post.
 *
 * @param int $post_id
 */
function postPublished($post_id = 0) 
{
	// Check the config settings.
	global $spnbabble_plugin_prefix;
	
	// If No then we won't update SPNBabble
	$spn_enable	 = get_option($spnbabble_plugin_prefix . 'spn_enable', 0);
	
	// If Yes then we update SPNBabble by default
	$spn_update	 = get_option($spnbabble_plugin_prefix . 'spn_update', 0);
	
	// If No then we update SPNBabble, if Yes then we do not
	$has_been_babbled 	= get_post_meta($post_id, 'has_been_babbled', true);


#	trigger_error('spn_enable: '.$spn_enable, E_USER_WARNING);
#	trigger_error('spn_update: '.$spn_update, E_USER_WARNING);
#	trigger_error('has_been_babbled: '.$spn_update, E_USER_WARNING);

	if ($spn_enable != 'Yes')
	{
		return;
	}

	if ($spn_update != 'Yes')
	{
		return;
	}
	
	if ($has_been_babbled != 'Yes')
	{
		$post = get_post($post_id);

		if ($post->post_status != 'publish') 
		{
			return;
		}

		$text = sprintf(__('New Blog Post: %s %s', 'spnbabble'), $post->post_title, get_permalink($post_id));
		doUpdate($text);

		add_post_meta($post_id, 'has_been_babbled', 'Yes');
	}
	
} // postPublished()
	
/**
 * Post the blog entry to the SPNBabble.
 *
 * @param string $text
 * @return boolean
 */
function doUpdate($text = '')
{
	global $spnbabble_plugin_prefix;

	$spnbabble_username	 = get_option($spnbabble_plugin_prefix . 'username', 0);
	$spnbabble_password 	= get_option($spnbabble_plugin_prefix . 'password', 0);
	$spnbabble_blogname 	= get_option($spnbabble_plugin_prefix . 'blogname', 0);
	
	if (empty($text))
	{
		return;
	}
	
	require_once(ABSPATH.WPINC.'/class-snoopy.php');
	$snoop = new Snoopy;
	$snoop->agent = 'SPNBabble http://www.themespluginswp.com/plugins/spn-babblespn-babble/';
	$snoop->rawheaders = array(
		'X-Laconica-Client' => 'SPNBabble',
		'X-Laconica-Client-Version' => G_VERSION,
		'X-Laconica-Client-URL' => 'http://www.themespluginswp.com/plugins/spn-babblespn-babble/',
	);
	$snoop->user = $spnbabble_username;
	$snoop->pass = $spnbabble_password;
	$snoop->submit(
		SPN_API_POST_STATUS,
		array(
			'status' 	=> $text,
			'source' => $spnbabble_blogname,	// 'SiteProNews'
		)
	);

	return (strpos($snoop->response_code, '200')) ?true:false;

} // doUpdate()
	

function spnbabble_plugin_url($str = '')
{
	$dir_name = '/wp-content/plugins/spnbabble';
	bloginfo('url');
	echo($dir_name . $str);
}

function spnbabble_options_subpanel()
{
	global $spnbabble_plugin_name;
	global $spnbabble_plugin_prefix;

  	if (isset($_POST['info_update'])) 
	{
		$username		= isset($_POST['username'])?$_POST['username']:'';
		$password		= isset($_POST['password'])?$_POST['password']:'';
		$blogname		= isset($_POST['blogname'])?$_POST['blogname']:'';
		$spn_enable	= isset($_POST['spn_enable'])?$_POST['spn_enable']:'';
		$spn_update	= isset($_POST['spn_update'])?$_POST['spn_update']:'';

		update_option($spnbabble_plugin_prefix . 'username', $username);
		update_option($spnbabble_plugin_prefix . 'password', $password);
		update_option($spnbabble_plugin_prefix . 'blogname', $blogname);
		update_option($spnbabble_plugin_prefix . 'spn_enable', $spn_enable);
		update_option($spnbabble_plugin_prefix . 'spn_update', $spn_update);
	} 
	else 
	{
		$username		= get_option($spnbabble_plugin_prefix . 'username');
		$password 		= get_option($spnbabble_plugin_prefix . 'password');
		$blogname 	= get_option($spnbabble_plugin_prefix . 'blogname');
		$spn_enable	= get_option($spnbabble_plugin_prefix . 'spn_enable');
		$spn_update	= get_option($spnbabble_plugin_prefix . 'spn_update');
		
		if (empty($spn_enable)) $spn_enable = 'Yes';
		if (empty($spn_update)) $spn_update = 'Yes';
		
		#trigger_error('spn_update: '.$spn_update, E_USER_WARNING);
	}

	?>
	<div class=wrap>
		<h2>SPNBabble Options</h2>

		<p>
			<h3>General Options</h3>
			You can find out more information about this plugin at <a href="http://www.themespluginswp.com/plugins/spn-babblespn-babble/">the SPNBabble Plugin page</a>.
		</p>
		<br />
		<form method="post">
			SPNBabble Username: <input type="text" name="username" value="<?php echo($username); ?>"><br />
			SPNBabble Password: <input type="password" name="password" value="<?php echo($password); ?>"><br />
			<br />
			Wordpress Blog Name: <input type="text" name="blogname" value="<?php echo($blogname); ?>"><br />
			<br />
			<div>
				<fieldset style="border:1px solid #cccccc; padding:5px;">
					<legend>Enable SPNBabble Plugin</legend>
					<label for="spn_enable">Enable the option to update SPNBabble when you post in your blog?</label>
					<select name="spn_enable" id="spn_enable">
						<option value="Yes" <?php if ($spn_enable == 'Yes') echo 'selected="selected"'; ?>>Yes</option>
						<option value="No" <?php if ($spn_enable != 'Yes') echo 'selected="selected"'; ?>>No</option>
					</select>
				</fieldset>
			</div>
			<br />
			<div class="option">
				<fieldset style="border:1px solid #cccccc; padding:5px;">
					<legend>Update SPNBabble</legend>
					<label for="spn_enable">Update SPNBabble by default?</label>
					<select name="spn_update" id="spn_update">
						<option value="Yes" <?php if ($spn_update == 'Yes') echo 'selected="selected"'; ?>>Yes</option>
						<option value="No" <?php if ($spn_update != 'Yes') echo 'selected="selected"'; ?>>No</option>
					</select>
					<p>
						<em>If "Yes" (and 'Enable SPNBabble Plugin' is "Yes") then your Blog posts will automatically create an update for SPNBabble when you save it for the first time.  If you set it to "No" you can still create the update but you will do it on a post by post basic using the Custom Field.</em>
					</p>
				</fieldset>
			</div>


			
			<div class="submit"><input type="submit" name="info_update" value="Update Options" /></div>
		</form>

	</div>
	
	<?php
}

/**
 *
 */
function spnbabble_add_plugin_option()
{
	global $spnbabble_plugin_name;
	if (function_exists('add_options_page')) 
	{
		add_options_page($spnbabble_plugin_name, $spnbabble_plugin_name, 0, basename(__FILE__), 'spnbabble_options_subpanel');
	}	
}

add_action('admin_menu', 'spnbabble_add_plugin_option');

?>
