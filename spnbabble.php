<?php
/*
Plugin Name: SPNbabble
Plugin URI: http://www.themespluginswp.com/plugins/spn-babble.html
Description: Generates SPNbabble Mini Blog Updates when a new Post is Published.
Author: Scott Stanger
Version: 1.4
Author URI: http://www.highcorral.com/
*/

/**
 * Changelog
 * 
 * 1.4  10/22/2009
 *			Restricted the Settings page to Admin only. 
 *
 * 1.3  8/19/2009
 *			Improved error handling.  If the update fails, display the
 *			response in the Custom Field "has_been_babbled".  Typical
 *			failures are 
 *				401: Invalid username/password
 *				406: Post is too long
 *			If SPNbabble accepts the post then the Custom Field
 *			"has_been_babbled" will be set to "Yes"
 *			Also force the SPNbabble username that is supplied on the
 *			plugin configuration page to lowercase since SPNbabble
 *			requires the username to be all lowercase.
 *
 * 1.2  7/13/2009
 *			Added a new configuration setting so that you can specify
 *			the Post Prefix (by default, it is set to "New Blog Post:").
 *
 * 1.1	4/27/2009
 * 			Made posted to SPNbabble conditional by adding a new Config
 *			Setting.
 *
 * 1.0	4/22/2009
 *			Initial Reslease
 *
 * Compatibility
 * Wordpress Version 2.71
 * Laconica Version 0.7.2.1 (SPNbabble is powered by Laconica)
 *
 * Wordpress <http://wordpress.org>
 * Laconica <http://laconi.ca>
 * SPNbabble <http://spnbabble.sitepronews.com>
 */

#	$allowed_group = 'manage_options'; 

$spnbabble_plugin_name = 'SPNbabble';
$spnbabble_plugin_prefix = 'spnbabble_';

// Full URI of SPNbabble without trailing slash
define('SPNBABBLE_URI', 'http://spnbabble.sitepronews.com');
define('SPN_API_POST_STATUS', SPNBABBLE_URI.'/api/statuses/update.json');
define('G_VERSION', '1.4');

add_action('publish_post', 'postPublished');

/**
 * Post the blog entry to SPNbabble.
 * The post must be 'published'.
 *
 * This is how the the plugin will post the update to SPNbabble:
 * 1)	A blog post is published (whether it is a New post or we are Updating
 *		and existing post.)
 * 2)	If the Custom Field 'enable_babble' does not exist then we
 *		add it with a value of 'no' (this will prevent the plugin from
 *		automaticaly posting an update to SPNbabble.
 *	3) if the Custom Field 'enable_babble' equals 'yes' then we
 *		check the value of the Custom Field 'has_been_babbled'.
 * 4)	If 'has_been_babbled' does not equal 'yes' then we post an
 *		update to SPNbabble. and add the Custom Field 'has_been_babbled' 
 *		and set its value to 'yes'
 * 5) To post a follow-up update to SPNbabble whenever the blog post
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
	
	// If No then we won't update SPNbabble
	$spn_enable	 = get_option($spnbabble_plugin_prefix . 'spn_enable', 0);
	
	// If Yes then we update SPNbabble by default
	$spn_update	 = get_option($spnbabble_plugin_prefix . 'spn_update', 0);

	// Prepend the notices with this
	$postprefix	 = get_option($spnbabble_plugin_prefix . 'postprefix', 0);
	
	// If No then we update SPNbabble, if Yes then we do not
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

		// The postprefix is prepending -- so that it looks like this:
		// "New Blog Post: <title> <permalink>"
		// But the blog owner can use an empty string -- so check the format
		if (empty($postprefix))
		{
			$postprefix = '';
		}
		else
		{
			// Ensure there is a trailing space
			$postprefix = rtrim($postprefix) . ' ';
		}
		$text = sprintf(__('%s%s %s', 'spnbabble'), $postprefix, $post->post_title, get_permalink($post_id));
		$response = doUpdate($text);

		if (strpos($response, '200') !== false)
		{
			// HTTP/1.1 200 OK
			add_post_meta($post_id, 'has_been_babbled', 'Yes');
		}
		elseif (strpos($response, '401') !== false)
		{
			// HTTP/1.1 401 Unauthorized -- This happens when you use an invalid username/password
			add_post_meta($post_id, 'has_been_babbled', 'No (401: invalid username/password)');
		}
		elseif (strpos($response, '406') !== false)
		{
			// HTTP/1.1 406 Not Acceptable -- This happens when the post is too long
			add_post_meta($post_id, 'has_been_babbled', 'No (406: post is too long)');
		}
		else
		{
			// Update failed
			add_post_meta($post_id, 'has_been_babbled', $response);
		}
	}
	
} // postPublished()
	
/**
 * Post the blog entry to the SPNbabble.
 *
 * @param string $text
 * @return boolean
 */
function doUpdate($text = '')
{
	global $spnbabble_plugin_prefix;

	// Force the username to lowercase since SPNbabble will reject it otherwise
	$spnbabble_username	 = strtolower(get_option($spnbabble_plugin_prefix . 'username', 0));
	$spnbabble_password 	= get_option($spnbabble_plugin_prefix . 'password', 0);
	$spnbabble_blogname 	= get_option($spnbabble_plugin_prefix . 'blogname', 0);
	
	if (empty($text))
	{
		return;
	}
	
	require_once(ABSPATH.WPINC.'/class-snoopy.php');
	$snoop = new Snoopy;
	$snoop->agent = 'SPNbabble http://www.themespluginswp.com/plugins/spn-babble.html';
	$snoop->rawheaders = array(
		'X-Laconica-Client' => 'SPNbabble',
		'X-Laconica-Client-Version' => G_VERSION,
		'X-Laconica-Client-URL' => 'http://www.themespluginswp.com/plugins/spn-babble.html',
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

	return $snoop->response_code;
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
		$postprefix		= isset($_POST['postprefix'])?$_POST['postprefix']:'';
		$spn_enable	= isset($_POST['spn_enable'])?$_POST['spn_enable']:'';
		$spn_update	= isset($_POST['spn_update'])?$_POST['spn_update']:'';

		update_option($spnbabble_plugin_prefix . 'username', $username);
		update_option($spnbabble_plugin_prefix . 'password', $password);
		update_option($spnbabble_plugin_prefix . 'blogname', $blogname);
		update_option($spnbabble_plugin_prefix . 'postprefix', $postprefix);
		update_option($spnbabble_plugin_prefix . 'spn_enable', $spn_enable);
		update_option($spnbabble_plugin_prefix . 'spn_update', $spn_update);
	} 
	else 
	{
		$username		= get_option($spnbabble_plugin_prefix . 'username');
		$password 		= get_option($spnbabble_plugin_prefix . 'password');
		$blogname 	= get_option($spnbabble_plugin_prefix . 'blogname');
		$postprefix 	= get_option($spnbabble_plugin_prefix . 'postprefix');
		$spn_enable	= get_option($spnbabble_plugin_prefix . 'spn_enable');
		$spn_update	= get_option($spnbabble_plugin_prefix . 'spn_update');
	
		// The first time the plugin is installed, the value of "spn_enable"
		// will be empty.	
		if (empty($spn_enable)) $postprefix = 'New Blog Post:';
		if (empty($spn_enable)) $spn_enable = 'Yes';
		if (empty($spn_update)) $spn_update = 'Yes';
		
		#trigger_error('spn_update: '.$spn_update, E_USER_WARNING);
	}

	?>
	<div class=wrap>
		<h2>SPNbabble Options</h2>

		<p>
			<h3>General Options</h3>
			You can find out more information about this plugin at <a href="http://www.themespluginswp.com/plugins/spn-babble.html">the SPNbabble Plugin page</a>.  If you have questions you may contact the author <a href="mailto:sstanger@highcorral.com">Scott Stanger</a>.
		</p>
		<br />
		<form method="post">
			SPNbabble Username: <input type="text" name="username" value="<?php echo($username); ?>"><br />
			SPNbabble Password: <input type="password" name="password" value="<?php echo($password); ?>"><br />
			<br />
			Wordpress Blog Name: <input type="text" name="blogname" value="<?php echo($blogname); ?>"><br />
			SPNbabble Notice Prefix: <input type="text" name="postprefix" value="<?php echo($postprefix); ?>"> <i>Ex: "New Blog Post:"</i><br />
			<br />
			<div>
				<fieldset style="border:1px solid #cccccc; padding:5px;">
					<legend>Enable SPNbabble Plugin</legend>
					<label for="spn_enable">Enable the option to update SPNbabble when you post in your blog?</label>
					<select name="spn_enable" id="spn_enable">
						<option value="Yes" <?php if ($spn_enable == 'Yes') echo 'selected="selected"'; ?>>Yes</option>
						<option value="No" <?php if ($spn_enable != 'Yes') echo 'selected="selected"'; ?>>No</option>
					</select>
				</fieldset>
			</div>
			<br />
			<div class="option">
				<fieldset style="border:1px solid #cccccc; padding:5px;">
					<legend>Update SPNbabble</legend>
					<label for="spn_enable">Update SPNbabble by default?</label>
					<select name="spn_update" id="spn_update">
						<option value="Yes" <?php if ($spn_update == 'Yes') echo 'selected="selected"'; ?>>Yes</option>
						<option value="No" <?php if ($spn_update != 'Yes') echo 'selected="selected"'; ?>>No</option>
					</select>
					<p>
						<em>If "Yes" (and 'Enable SPNbabble Plugin' is "Yes") then your Blog posts will automatically create an update for SPNbabble when you save it for the first time.  If you set it to "No" you can still create the update but you will do it on a post by post basic using the Custom Field.</em>
					</p>
				</fieldset>
			</div>

			<div class="submit"><input type="submit" name="info_update" value="Update Options" /></div>
		</form>

		<div><a href="http://spnbabble.sitepronews.com"><img src="http://spnbabble.sitepronews.com/spn_tran_1a.png" width="245" height="58" alt="SPNbabble" title="Visit SPNbabble"></a></div>

	</div>
	
	<?php
}

/**
 *
 */
function spnbabble_add_plugin_option()
{
	global $spnbabble_plugin_name;
	$access_level = 8;	// Restrict the settings to Admin only
	
	//	User Level 		Role
	//		0 				Subscriber
	//		1 				Contributor
	//	2, 3, 4 			Author
	//	5, 6, 7 			Editor
	//	8, 9, 10 		Administrator
	
	if (function_exists('add_options_page')) 
	{
		add_options_page($spnbabble_plugin_name, $spnbabble_plugin_name, $access_level, basename(__FILE__), 'spnbabble_options_subpanel');
	}	
}

add_action('admin_menu', 'spnbabble_add_plugin_option');

?>
