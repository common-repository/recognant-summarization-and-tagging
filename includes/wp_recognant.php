<?php
/*
 * @ class - wp_recognant
 *
 **/

if(!class_exists( 'wp_recognant' )):
class wp_recognant
{
	/*
	*
	**/
	function __construct()
	{
		global $wpdb;

		define( "WPREC_DIR" 				, WP_PLUGIN_DIR.'/' .basename(dirname(WPREC_FILE)) . '/' );
		define( "WPREC_URL"				, plugins_url() .basename(dirname(WPREC_FILE)) . '/'); 

		define( "WPREC_VER"				, "1.0.1" 						);
		define( "WPREC_DEBUG"				, false						);

		define( "WPREC_PER_CYCLE"			, 10							);

		register_activation_hook ( WPREC_FILE	, array( &$this, 'wprec_activate'		)); 
		register_deactivation_hook ( WPREC_FILE	, array( &$this, 'wprec_deactivate'		));

		add_action( 'init'				, array( &$this, 'wprec_run_cron'		));
		add_action( 'admin_menu'			, array( &$this, 'wprec_options_page'	));
		add_action( 'admin_head'			, array( &$this, 'wprec_admin_header'	));
		add_filter( 'plugin_action_links'		, array( &$this, 'wprec_plugin_actions'	), 10, 2 );

		add_filter( 'single_template'			, array( &$this, 'wprec_post_template' 	), 99 );
		add_action( 'transition_post_status'	, array( &$this, 'wprec_post_status'	), 10, 3 );

		add_action( 'wp_ajax_wprec_options'		, array( &$this, 'wprec_ajax_options'	));
		add_action( 'wp_ajax_wprec_getexcerpt'	, array( &$this, 'wprec_ajax_wprec_getexcerpt'	));
	}

	/*
	*
	**/
	function wprec_activate()
	{
		global $wpdb;

		if( ! $wprec_cron = get_option("wprec_cron") )
		{
			$cron = '';
			foreach(range(1,5) as $a)
				$cron .= chr(mt_rand(97, 122));
			$cron = strtolower($cron);
			update_option ("wprec_cron", $cron);
		}

		if( ! $wprec_ver = get_option("wprec_ver") )
			update_option ("wprec_ver", WPREC_VER);
	}

	/*
	*
	**/
	function wprec_deactivate()
	{
		//nothing here.//
	}

	/*
	*
	**/
	function wprec_footer() 
	{
		$plugin_data = get_plugin_data( WPREC_FILE );
		printf( '%1$s plugin | Version %2$s | by %3$s<br />', $plugin_data['Title'], $plugin_data['Version'], $plugin_data['Author']); 
	}

	/*
	*
	**/
	function wprec_page_footer() {
		echo '<br/><div id="page_footer" class="postbox" style="text-align:center;padding:10px;"><em>';
		self::wprec_footer(); 
		echo '</em></div>';
	}

	/*
	*
	**/
	function wprec_plugin_actions($links, $file)
	{
		if( strpos( $file, basename(WPREC_FILE)) !== false )
		{
			$link = '<a href="'.admin_url( 'options-general.php?page=wprecognant' ) .'">'.__( 'Settings', 'wprec_lang' ).'</a>';
			array_unshift( $links, $link );
		}
		return $links;
	}

	/*
	*
	**/
	function wprec_options_page()
	{
		add_options_page( 'WP-Recognant', 'WP-Recognant', 8, 'wprecognant', array( &$this, 'wprec_main' ) );
	}

	/*
	*
	**/
	function wprec_admin_header()
	{
		global $wpdb;

		if( is_admin() && $_GET['page'] == 'wprecognant' )
		{
			?>
		<script type="text/javascript">
		if( typeof jQuery == 'function' ){
			jQuery(document).ready( function($){
				var ajax_nonce 		= '<?php echo wp_create_nonce( 'wprec_ajax' ); ?>';
				var wprec_plugin_url 	= '<?php echo WPREC_URL;?>';
				var site_url 		= '<?php echo site_url(); ?>';
				var ajaxurl 		= '<?php echo admin_url('admin-ajax.php') ?>';

				$("#wprec_optionsfrm").submit( function(event){
					event.preventDefault();

					var data = {
						action		: 'wprec_options',
						wprec_apikey	: $('#wprec_apikey').val(),
						wprec_oneditpost	: ( $('#wprec_oneditpost').is(":checked")? "1":"0" ),
						wprec_length	: $('#wprec_length').val(),
						wprec_addtags	: ( $('#wprec_addtags').is(":checked")? "1":"0" ),
						wprec_tagcount	: $('#wprec_tagcount').val(),
						_ajax_nonce	: ajax_nonce
					};

					$("#wprec_options_ajax").show();
					$('#wprec_options_result').html( '' );
					$('#wprec_options_err').html( '' );

					var myajax = jQuery.post(ajaxurl, data, function(response) {
						$("#wprec_options_ajax").hide();
						if( response != '-1' && ! response.err )
						{
							$('#wprec_options_result').html(response.msg);
							$('#wprec_options_err').html( '' );
						}
						else
						{
							$('#wprec_options_result').html( '' );
							$('#wprec_options_err').html('<!-- code: '+response.err+' -->'+ response.msg );
						}
					}, "json");
					$(window).unload( function() { myajax.abort(); } );
				});

				$("#wprec_getexcerpt").click( function(event){
					event.preventDefault();

					var data = {
						action		: 'wprec_getexcerpt',
						_ajax_nonce	: ajax_nonce
					};

					$("#wprec_getexcerpt_ajax").show();
					$('#wprec_getexcerpt_result').html( '' );
					$('#wprec_getexcerpt_err').html( '' );
					$('#wprec_getexcerptdiv').html('');

					var myajax = jQuery.post(ajaxurl, data, function(response) {
						$("#wprec_getexcerpt_ajax").hide();
						if( response != '-1' && ! response.err )
						{
							$('#wprec_getexcerptdiv').html(response.tbl);
							$('#wprec_getexcerpt_result').html(response.msg);
							$('#wprec_getexcerpt_err').html( '' );
						}
						else
						{
							$('#wprec_getexcerpt_result').html( '' );
							$('#wprec_getexcerpt_err').html('<!-- code: '+response.err+' -->'+ response.msg );
						}
					}, "json");
					$(window).unload( function() { myajax.abort(); } );
				});
			});
		}
		</script>
			<?php
		}
	}

	function wprec_process_api( $rec )
	{
		$url = 'https://enelyou-enelyou-summarization--index--summary--topic--part-of-s.p.mashape.com/sumpagejson/';

		$headers['X-Mashape-Key']	= $rec['apikey'];
		$headers['Content-Type']	= 'application/x-www-form-urlencoded';
		$headers['Accept']		= 'application/json';

		$body = array(
			'length' 		=> $rec['length'],
			'url' 		=> $rec['url']
		);
		if( $rec['addtag'] == 0 )
			$body['sumonly'] 	= 'sumonly';

		$args = array(
			'timeout'		=> 30,
			'headers'		=> $headers,
			'body'		=> $body,
			'sslverify' 	=> false
		);

		$response 	= wp_remote_post( $url, $args );

		$response 	= json_decode( $response['body'], true );
		$summary 	= $response['summary'];
		$keywords 	= array();

		if( ! empty( $response['composition_content'] ) )
		{
			arsort( $response['composition_content'] );
			$keywords = array_slice( $response['composition_content'], 0, ( $rec['tagcount'] * 3 ) );

			$k = array();
			foreach( $keywords as $keyword => $cnt )
			{
				$keyword = trim( $keyword );
				if( empty( $keyword ) ) continue;

				if( ! preg_match( '/^[a-zA-Z]/is', $keyword[0] ) ) continue;
				if( strlen( $keyword ) <= 4 ) continue;
				$k[] = $keyword;
			}
			$keywords = array_slice( $k, 0, $rec['tagcount'] );
 		}
		return array( $summary, $keywords );
	}

	function wprec_process_posts()
	{
		$wprec_options = get_option( 'wprec_options' );

		$args = array(
			'post_type'		=> 'post',
			'post_status'	=> 'publish',
			'post_excerpt'	=> '',
			'posts_per_page'	=> WPREC_PER_CYCLE
		);
		$myposts = get_posts( $args );

		$v .= sprintf( __('Processsing %d posts: '), count( $myposts ) );

		$v .= '<ol>';
		foreach ( $myposts as $post ) :
			$rec = array();
			$rec['apikey'] 	= $wprec_options['wprec_apikey'];
			$rec['length'] 	= $wprec_options['wprec_length'];
			$rec['tagcount'] 	= $wprec_options['wprec_tagcount'];
			$rec['addtag'] 	= $wprec_options['wprec_addtags'];
			$rec['url'] 	= get_permalink( $post->ID ).'?wprec=1';

			list( $summary, $keywords ) = $this->wprec_process_api( $rec );

			wp_update_post( array('ID'=> $post->ID, 'post_excerpt'=>$summary) );

			if( ! empty( $keywords ) )
				wp_set_post_tags( $post->ID, $keywords, true );

			$ss = sprintf( __( 'Added Summary <!-- %s --> and %d Keywords <!-- %s -->'), $summary, count( $keywords ), implode( ', ', $keywords ) );
			$v .= sprintf( __('<li>Processing <a href="%s" target="_blank">%s</a>: %s</li>','wprec_lang'), get_permalink( $post->ID ), get_the_title( $post->ID ), $ss );
			$v .= "\n";
		endforeach;
		wp_reset_postdata();
		$v .= '<ol>';

		return array( $v, count( $myposts ) );
	}

	function wprec_ajax_wprec_getexcerpt()
	{
		global $wpdb, $current_user;
		get_currentuserinfo();

		if(!is_user_logged_in())  
		{
			$out = array();
			$out['msg'] = __('User not logged in','wprec_lang');
			$out['err'] = __LINE__;
			header( "Content-Type: application/json" );
			echo json_encode( $out );
			die();
		}
		check_ajax_referer( "wprec_ajax" );

		if(!defined('DOING_AJAX')) define( 'DOING_AJAX', 1 );
		set_time_limit(60);

		list( $v, $cnt ) = $this->wprec_process_posts();

		$out['msg'] = sprintf( __('%d posts processed.','wprec_lang'), $cnt );
		$out['tbl'] = $v;

		header( "Content-Type: application/json" );
		echo json_encode( $out );
		die();
	}

	function wprec_run_cron()
	{
		if( ! isset( $_GET['wpreccron'] ) ) return;

		$wprec_cron = get_option("wprec_cron");
		$wpreccron = trim( esc_attr( strip_tags( $_GET['wpreccron'] ) ) );
		if( $wprec_cron != $wpreccron ) return;

		list( $v, $cnt ) = $this->wprec_process_posts();

		if( isset( $_GET['v'] ) )
			echo $v;

		exit;
	}

	/*
	*
	* If post_excerpt is empty then fetch new.
	* scenario: Excerpt got from server, user edits the said excerpt, we dont want to overwrite his edits;
	* so update only when empty;
	*
	**/
	function wprec_post_status( $new_status, $old_status, $post ) 
	{
		$wprec_options = get_option( 'wprec_options' );

		if( $wprec_options['wprec_oneditpost'] != 1 ) return;

		if( $new_status == 'publish' && get_post_type( $post ) == 'post' && $post->post_excerpt == '' ) 
		{
			$rec = array();
			$rec['apikey'] 	= $wprec_options['wprec_apikey'];
			$rec['length'] 	= $wprec_options['wprec_length'];
			$rec['tagcount'] 	= $wprec_options['wprec_tagcount'];
			$rec['addtag'] 	= $wprec_options['wprec_addtags'];
			$rec['url'] 	= get_permalink( $post->ID ).'?wprec=1';

			list( $summary, $keywords ) = $this->wprec_process_api( $rec );

			wp_update_post( array('ID'=> $post->ID, 'post_excerpt'=>$summary ) );

			if( ! empty( $keywords ) )
				wp_set_post_tags( $post->ID, $keywords, true );
		}
	}

	function wprec_ajax_options()
	{
		global $wpdb, $current_user;
		get_currentuserinfo();

		if(!is_user_logged_in())  
		{
			$out = array();
			$out['msg'] = __('User not logged in','wprec_lang');
			$out['err'] = __LINE__;
			header( "Content-Type: application/json" );
			echo json_encode( $out );
			die();
		}
		check_ajax_referer( "wprec_ajax" );

		if(!defined('DOING_AJAX')) define('DOING_AJAX', 1);
		set_time_limit(60);

		$wprec_options				= array();
		$wprec_options['wprec_apikey']	= (isset( $_POST['wprec_apikey'] )			? sanitize_text_field( $_POST['wprec_apikey'] ): '' );
		$wprec_options['wprec_length']	= (isset( $_POST['wprec_length'] )			? (int)sanitize_text_field( $_POST['wprec_length'] ): '' );
		$wprec_options['wprec_oneditpost']	= (isset( $_POST['wprec_oneditpost'] ) && trim( $_POST['wprec_oneditpost'] ) ==1 ? 1 : 0 );
		$wprec_options['wprec_addtags']	= (isset( $_POST['wprec_addtags'] )	&& trim( $_POST['wprec_addtags'] ) == 1 ? 1 : 0 );
		$wprec_options['wprec_tagcount']	= (isset( $_POST['wprec_tagcount'] )		? (int)sanitize_text_field( $_POST['wprec_tagcount'] ): 1 );

		update_option( 'wprec_options', $wprec_options );
		$out['msg'] = __('Settings have been saved.','wprec_lang');

		header( "Content-Type: application/json" );
		echo json_encode( $out );
		die();
	}

	/*
	*
	**/
	function wprec_main()
	{
		global $wpdb;

		if (!current_user_can( 'manage_options' )) wp_die(__( 'Sorry, but you have no permissions to change settings.' ));

		if( isset( $_POST['call'] ) && trim( $_POST['call'] ) == 'save_settings' )
		{
			$wprec_options				= array();
			$wprec_options['wprec_apikey']	= (isset( $_POST['wprec_apikey'] )			? sanitize_text_field( $_POST['wprec_apikey'] ): '' );
			$wprec_options['wprec_length']	= (isset( $_POST['wprec_length'] )			? (int)sanitize_text_field( $_POST['wprec_length'] ): '' );
			$wprec_options['wprec_oneditpost']	= (isset( $_POST['wprec_oneditpost'] )		? 1 : 0 );
			$wprec_options['wprec_addtags']	= (isset( $_POST['wprec_addtags'] )			? 1 : 0 );
			$wprec_options['wprec_tagcount']	= (isset( $_POST['wprec_tagcount'] )		? (int)sanitize_text_field( $_POST['wprec_tagcount'] ): 1 );

			update_option( 'wprec_options', $wprec_options );
			$result1 = __('Settings have been saved.','wprc_lang');
		}
		$wprec_options 	= get_option( 'wprec_options' );
		?>
		<div class="wrap">
		<h2><?php _e( 'WP-Recognant Summarization and Tagging', 'wprec_lang' )?></h2>
		<h3><?php _e( 'Add automatic Excerpts and post tags to your posts using Recognant API','wprec_lang' );?></h3>

	<div id="poststuff">
	<div id="post-body" class="metabox-holder columns-1">
	<div id="post-body-content">
<?php
if($result1)
{
?>
<div id="message" class="updated fade"><p><?php echo $result1; ?></p></div>
<?php
}

if($error)
{
?>
<div class="error fade"><p><b><?php _e('Error: ', 'wprec_lang')?></b><?php echo $error;?></p></div>
<?php
}
?>
<style type="text/css">
table.form-table2 th { font-weight:bold; width:25%;}
table.form-table2 td{width:75%;}
.wprec_result{ font-weight:bold; color:#2323ff;}
.wprec_err{font-weight:bold; color:#ff2323;}
</style>
	<div id="genopdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wprec_lang' ); ?>"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'WP-Recognant Summarization: Settings', 'wprec_lang' ); ?></span></h3>
	      <div class="inside">
	  <form method="post" id="wprec_optionsfrm" name="wprec_optionsfrm" >
		<input type="hidden" name="call" value="save_settings"/>

		<table border="0" class="form-table form-table2">
		<tbody>
		<tr valign="top">
		<th scope="row"><label for="wprec_apikey"><?php _e('API Key', 'wprec_lang')?></label>&nbsp;<a href="https://market.mashape.com/Recognant/summarization-index-summary-part-of-speech/pricing">(Get a Free API Key)</a> </th>
		<td><input type="text" name="wprec_apikey" id="wprec_apikey" value="<?php echo $wprec_options['wprec_apikey'] ?>" class="regular-text" />
		</td>
		</tr>
		<tr valign="top">
		<th><label for="wprec_oneditpost"><?php _e('Add Excerpt on Add/ Edit Posts', 'wprec_lang')?></label></th>
		<td><input type="checkbox" name="wprec_oneditpost" id="wprec_oneditpost" value="1" <?php checked( $wprec_options['wprec_oneditpost'], "1" ); ?> /> 
		<br/><span class="description"><?php _e('Check to add excerpts and (or) Tags while adding or editing posts.<br/>If this is unchecked, excerpts/ tags will be fetched only via the Cron job.','wprec_lang');?></span>
		</td>
		</tr>
		<tr valign="top">
		<th><label for="wprec_length"><?php _e('Length of Excerpt', 'wprec_lang')?></label></th>
		<td><input type="text" name="wprec_length" id="wprec_length" value="<?php echo $wprec_options['wprec_length'] ?>" class="regular-text" /> <?php _e('Characters','wprec_lang');?>
		</td>
		</tr>
		<tr valign="top">
		<th><label for="wprec_addtags"><?php _e('Add Tags', 'wprec_lang')?></label></th>
		<td><input type="checkbox" name="wprec_addtags" id="wprec_addtags" value="1" <?php checked( $wprec_options['wprec_addtags'], "1" ); ?>/><br/>
			<span class="description"><?php _e('Add Tags to the post for keywords got from Recognant API.','wprec_lang');?></span>
		</td>
		</tr>
		<tr valign="top">
		<th><label for="wprec_tagcount"><?php _e('Tag count', 'wprec_lang')?></label></th>
		<td><select name="wprec_tagcount" id="wprec_tagcount">
			<?php foreach( range(0,20) as $a ){?>
				<option value="<?php echo $a; ?>" <?php selected( $wprec_options['wprec_tagcount'], $a ) ?>><?php echo $a?></option>
			<?php } ?>
			</select><br/>
			<span class="description"><?php _e('Number of Tags that will be added to the post.','wprec_lang');?></span>
		</td>
		</tr>
		</tbody>
		</table>
		<br/>
		<p class="submit">
			<input name="submit_options" id="submit_options" class="button button-primary" value="Save Settings" type="submit"> 
			<img src="<?php echo admin_url( 'images/wpspin_light.gif' ); ?>" style="display:none" id="wprec_options_ajax" border="0"/>
			<span class="wprec_result" id="wprec_options_result">&nbsp;</span><span class="wprec_err" id="wprec_options_err"></span>
		</p>
		</form>

		</div>
	    </div>

	    <div id="getexcerptdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wprec_lang' ); ?>"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'WP-Recognant: Get Excerpt and Tag Posts', 'wprec_lang' ); ?></span></h3>
	      <div class="inside">
	  <form method="post" id="wprec_optionsfrm" name="wprec_optionsfrm" >
		<input type="hidden" name="call" value="save_settings"/>

		<table border="0" class="form-table form-table2">
		<tbody>
		<tr valign="top">
		<th scope="row"><label for="wprec_getexcerpt"><?php _e('Get Excerpt and Tag Posts', 'wprec_lang')?></label></th>
		<td>
			<input type="button" name="wprec_getexcerpt" id="wprec_getexcerpt" value="<?php printf( __('Generate %d excerpts on latest posts','wprec'), WPREC_PER_CYCLE ); ?>" />
			<img src="<?php echo admin_url( 'images/wpspin_light.gif' ); ?>" style="display:none" id="wprec_getexcerpt_ajax" border="0"/>
			<br/><span class="description"><?php _e( 'Fetch Excerpt and (or) tags for older posts. This may take some time', 'wprec_lang' );?></span><br/>
			<span class="wprec_result" id="wprec_getexcerpt_result">&nbsp;</span><span class="wprec_err" id="wprec_getexcerpt_err"></span><br/>
			<span id="wprec_getexcerptdiv"></span>
		</td>
		</tr>
		</tbody>
		</table>
		</form>

		</div>
	    </div>

	    <div id="genopdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wprec_lang' ); ?>"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'WP-Recognant: Cron', 'wprec_lang' ); ?></span></h3>
	      <div class="inside">
		<input type="hidden" name="call" value="save_cron"/>

	<table border="0" class="form-table">
		<thead>
		<tr><th scope="row"><strong><?php _e('Cron Options:', 'wprec_lang'); ?></th>
		<td><span class="dexription"><?php printf( __('If you wish to add excerpts to your existing posts automatically, you could run a UNIX cron job. A unix cron job would take %d posts and update its excerpt and/ or Post Tags.','wprec_lang'), WPREC_PER_CYCLE );?></span></td>
		</tr>
		</thead>
		<tbody>
		<tr valign="top">
		<th scope="row" width="25%"><label for="cron_url"><?php _e('Unix cron URL', 'wprec_lang')?></label></th>
		<td width="75%"><input class="regular-text" type="text" name="wprec_cron_url" id="wprec_cron_url" value="<?php echo home_url().'/?wpreccron='. get_option("wprec_cron")?>" onclick="this.select()" readonly="readonly"/>
		<br/><span class="description"><?php _e('Please use the above URL to set up a cron job from your servers control panel.',"wprec_lang") ?></span>
		<br/><?php _e("Example: ", "wprec_lang") ?><br/>
		<input style="width:450px" class="regular-text" type="text" name="wprec_cron_url" id="wprec_cron_url" 
			value="wget -q -O /dev/null <?php echo home_url().'/?wpreccron='. get_option("wprec_cron")?>" onclick="this.select()" readonly="readonly"/>
		</td></tr>
		</tbody>
	</table>

		</div>
	    </div>


	  <hr class="clear" />
	</div><!-- /post-body-content -->

	</div><!-- /post-body -->
	<br class="clear" />
	</div><!-- /poststuff -->
	</div><!-- /wrap -->

	<!-- ==================== -->
	<?php
		$this->wprec_page_footer();
	}

	function wprec_post_template($single_template) 
	{
		global $post;

		if ($post->post_type == 'post' && trim( $_GET['wprec'] ) == 1 ) 
		{
			$single_template = WPREC_DIR . 'includes/templates/single.php';
		}
		return $single_template;
	}
}
endif;

global $wp_recognant;
if( ! $wp_recognant ) $wp_recognant = new wp_recognant();