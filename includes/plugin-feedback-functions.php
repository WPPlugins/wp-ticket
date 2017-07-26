<?php
/**
 * Plugin Page Feedback Functions
 *
 * @package WP_TICKET_COM
 * @since WPAS 5.3
 */
if (!defined('ABSPATH')) exit;
add_filter('plugin_row_meta', 'wp_ticket_com_plugin_row_meta', 10, 2);
add_filter('plugin_action_links', 'wp_ticket_com_plugin_action_links', 10, 2);
add_action('wp_ajax_wp_ticket_com_send_deactivate_reason', 'wp_ticket_com_send_deactivate_reason');
global $pagenow;
if ('plugins.php' === $pagenow) {
	add_action('admin_footer', 'wp_ticket_com_deactivation_feedback_box');
}
add_action('wp_ajax_wp_ticket_com_show_rateme', 'wp_ticket_com_show_rateme_action');
if (isset($_GET['wp_ticket_com_optin'])) {
	if (!function_exists('wp_get_current_user')) {
		require_once (ABSPATH . 'wp-includes/pluggable.php');
	}
	if ($_GET['wp_ticket_com_optin'] == 1) {
		//opt-in
		$data['plugin_name'] = 'wp_ticket_com';
		$data['plugin_version'] = WP_TICKET_COM_VERSION;
		$data['wp_version'] = get_bloginfo('version');
		$data['php_version'] = phpversion();
		$data['server'] = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';
		$current_user = wp_get_current_user();
		$data['first_name'] = $current_user->user_firstname;
		$data['last_name'] = $current_user->user_lastname;
		$data['nick_name'] = $current_user->user_nicename;
		$data['email'] = $current_user->user_email;
		$data['site_name'] = get_bloginfo('name');
		$data['site_url'] = home_url();
		$data['language'] = get_bloginfo('language');
		$resp = wp_remote_post('https://api.emarketdesign.com/optin_info.php', array(
			'body' => $data,
		));
		update_option('wp_ticket_com_tracking_optin', 1);
	} else {
		//opt-out
		update_option('wp_ticket_com_tracking_optin', -1);
	}
	wp_redirect(esc_url(remove_query_arg('wp_ticket_com_optin')));
	exit;
} else {
	add_action('admin_notices', 'wp_ticket_com_show_optin');
}
function wp_ticket_com_show_optin() {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!get_option('wp_ticket_com_tracking_optin')) {
		$tr_title = __('Please help us improve WP Ticket', 'wp-ticket-com');
		$tr_msg = implode('<br />', array(
			__('Allow eMDPlugins to collect your usage of WP Ticket. This will help you to get a better, more compatible plugin in the future.', 'wp-ticket-com') ,
			__('If you skip this, that\'s okay! WP Ticket will still work just fine.', 'wp-ticket-com') ,
		));
		$tr_link = implode(' ', array(
			sprintf(__('%sDo not allow%s', 'wp-ticket-com') , '<a href="' . admin_url('admin.php?page=wp_ticket_com&wp_ticket_com_optin=0') . '" class="button-secondary" id="wp-ticket-com-do-not-allow-tracking">', '</a>') ,
			sprintf(__('%sAllow%s', 'wp-ticket-com') , '<a href="' . admin_url('admin.php?page=wp_ticket_com&wp_ticket_com_optin=1') . '" class="button-primary" id="wp-ticket-com-allow-tracking">', '</a>') ,
		));
		echo '<div class="update-nag emd-admin-notice">';
		echo '<h3 class="emd-notice-title"><span class="dashicons dashicons-smiley"></span>' . $tr_title . '<span class="dashicons dashicons-smiley"></span></h3><p class="emd-notice-body">';
		echo $tr_msg . '</p><ul class="emd-notice-body nf-red">';
		echo $tr_link . '</ul><div class="emd-permissions"><a href="#" class="emd-perm-trigger"><span class="dashicons dashicons-info" style="text-decoration:none;"></span>' . __('What permissions are being granted?', 'wp-ticket-com') . '</a><ul class="emd-permissions-list" style="display:none;">';
		echo '<li class="emd-permission"><i class="dashicons dashicons-nametag"></i><div><span>' . __('Your Profile Overview', 'wp-ticket-com') . '</span><p>' . __('Name and email address', 'wp-ticket-com') . '</p></div></li>';
		echo '<li class="emd-permission"><i class="dashicons dashicons-admin-settings"></i><div><span>' . __('Your Site Overview', 'wp-ticket-com') . '</span><p>' . __('Site URL, WP version and PHP info', 'wp-ticket-com') . '</p></div></li>';
		echo '<li class="emd-permission"><i class="dashicons dashicons-email-alt"></i><div><span>' . __('Newsletter', 'wp-ticket-com') . '</span><p>' . __('Updates, announcements, marketing, no spam', 'wp-ticket-com') . ', <a href="https://emdplugins.com/subscription-preferences/" target="_blank">unsubscribe anytime</a></p></div></li>';
		echo '</ul></div></div>';
	} else {
		//check min entity count if its not -1 then show notice
		$min_trigger = get_option('wp_ticket_com_show_rateme_plugin_min', 10);
		if ($min_trigger != - 1) {
			wp_ticket_com_show_rateme_notice();
		}
	}
}
function wp_ticket_com_show_rateme_action() {
	if (!wp_verify_nonce($_POST['rateme_nonce'], 'wp_ticket_com_rateme_nonce')) {
		exit;
	}
	$min_trigger = get_option('wp_ticket_com_show_rateme_plugin_min', 10);
	if ($min_trigger == - 1) {
		die;
	}
	if (10 === $min_trigger) {
		$response['redirect'] = "https://wordpress.org/support/plugin/wp-ticket/reviews/#postform";
		$min_trigger = 20;
	} else {
		$response['redirect'] = "https://emdplugins.com/plugin_tag/wp-ticket-com";
		$min_trigger = - 1;
	}
	update_option('wp_ticket_com_show_rateme_plugin_min', $min_trigger);
	echo json_encode($response);
	die;
}
function wp_ticket_com_show_rateme_notice() {
	if (!current_user_can('manage_options')) {
		return;
	}
	$min_count = 0;
	$ent_list = get_option('wp_ticket_com_ent_list');
	$min_trigger = get_option('wp_ticket_com_show_rateme_plugin_min', 10);
	$triggerdate = get_option('wp_ticket_com_activation_date', false);
	$installed_date = (!empty($triggerdate) ? $triggerdate : '999999999999999');
	$today = mktime(0, 0, 0, date('m') , date('d') , date('Y'));
	$label = $ent_list['emd_ticket']['label'];
	$count_posts = wp_count_posts('emd_ticket');
	if ($count_posts->publish > $min_trigger) {
		$min_count = $count_posts->publish;
	}
	if ($min_count > 10 || ($min_trigger == 10 && $installed_date <= $today)) {
		$message_start = '<div class="emd-show-rateme update-nag success">
                        <span class=""><b>WP Ticket</b></span>
                        <div>';
		if ($min_count > 5) {
			$message_start.= sprintf(__("Hi, I noticed you just crossed the %d %s milestone on WP Ticket - that's awesome!", "wp-ticket-com") , $min_trigger, $label);
		} elseif ($installed_date <= $today) {
			$message_start.= __("Hi, I just noticed you have been using WP Ticket for about a week now - that's awesome!", "wp-ticket-com");
		}
		$message_level1 = __('Could you please do me a <span style="color:red" class="dashicons dashicons-heart"></span> BIG favor <span style="color:red" class="dashicons dashicons-heart"></span> and give it a 5-star rating on WordPress? Just to help us spread the word and boost our motivation.', "wp-ticket-com");
		$message_level2 = sprintf(__("Would you like to upgrade now to get more out of your %s?", "wp-ticket-com") , $label);
		$message_end = '<br/><br/>
                        <strong>Safiye Duman</strong><br>eMarket Design Cofounder<br><a data-rate-action="twitter" style="text-decoration:none" href="https://twitter.com/safiye_emd" target="_blank"><span class="dashicons dashicons-twitter"></span>@safiye_emd</a>
                        </div>
                        <div style="background-color: #f0f8ff;padding: 0 0 10px 10px;width: 300px;border: 1px solid;border-radius: 10px;margin: 14px 0;"><br><strong>Thank you</strong> <span class="dashicons dashicons-smiley"></span>
                        <ul data-nonce="' . wp_create_nonce('wp_ticket_com_rateme_nonce') . '">';
		$message_end1 = '<li><a data-rate-action="do-rate" data-plugin="wp_ticket_com" href="#">' . __('Ok, you deserve it', 'wp-ticket-com') . '</a>
       </li>
        <li><a data-rate-action="done-rating" data-plugin="wp_ticket_com" href="#">' . __('I already did', 'wp-ticket-com') . '</a></li>
        <li><a data-rate-action="not-enough" data-plugin="wp_ticket_com" href="#">' . __('Maybe later', 'wp-ticket-com') . '</a></li>';
		$message_end2 = '<li><a data-rate-action="upgrade-now" data-plugin="wp_ticket_com" href="#">' . __('I want to upgrade', 'wp-ticket-com') . '</a>
       </li>
        <li><a data-rate-action="not-enough" data-plugin="wp_ticket_com" href="#">' . __('Maybe later', 'wp-ticket-com') . '</a></li>';
	}
	if ($min_count > 20 && $min_trigger == 20) {
		echo $message_start . '<br>' . $message_level2 . ' ' . $message_end . ' ' . $message_end2 . '</ul></div></div>';
	} elseif ($min_count > 10 || ($min_trigger == 10 && $installed_date <= $today)) {
		echo $message_start . '<br>' . $message_level1 . ' ' . $message_end . ' ' . $message_end1 . '</ul></div></div>';
	}
}
/**
 * Adds links under plugin description
 *
 * @since WPAS 5.3
 * @param array $input
 * @param string $file
 * @return array $input
 */
function wp_ticket_com_plugin_row_meta($input, $file) {
	if ($file != 'wp-ticket/wp-ticket.php') return $input;
	$links = array(
		'<a href="https://docs.emdplugins.com/docs/wp-ticket-community-documentation/">' . __('Docs', 'wp-ticket-com') . '</a>',
		'<a href="https://emdplugins.com/plugin_tag/wp-ticket-com">' . __('Pro Version', 'wp-ticket-com') . '</a>'
	);
	$input = array_merge($input, $links);
	return $input;
}
/**
 * Adds links under plugin description
 *
 * @since WPAS 5.3
 * @param array $input
 * @param string $file
 * @return array $input
 */
function wp_ticket_com_plugin_action_links($links, $file) {
	if ($file != 'wp-ticket/wp-ticket.php') return $links;
	foreach ($links as $key => $link) {
		if ('deactivate' === $key) {
			$links[$key] = $link . '<i class="wp_ticket_com-deactivate-slug" data-slug="wp_ticket_com-deactivate-slug"></i>';
		}
	}
	$new_links['settings'] = '<a href="' . admin_url('admin.php?page=wp_ticket_com_settings') . '">' . __('Settings', 'wp-ticket-com') . '</a>';
	$links = array_merge($new_links, $links);
	return $links;
}
function wp_ticket_com_deactivation_feedback_box() {
	$is_long_term_user = true;
	$feedback_vars['utype'] = 0;
	$trigger_time = get_option('wp_ticket_com_activation_date');
	//7 days before trigger
	$activation_time = $trigger_time - 604800;
	$date_diff = time() - $activation_time;
	$date_diff_days = floor($date_diff / (60 * 60 * 24));
	if ($date_diff_days < 2) {
		$feedback_vars['utype'] = 1;
		$is_long_term_user = false;
	}
	wp_enqueue_style("emd-plugin-modal", WP_TICKET_COM_PLUGIN_URL . 'assets/css/emd-plugin-modal.css');
	$feedback_vars['header'] = __('If you have a moment, please let us know why you are deactivating', 'wp-ticket-com');
	$feedback_vars['submit'] = __('Submit & Deactivate', 'wp-ticket-com');
	$feedback_vars['skip'] = __('Skip & Deactivate', 'wp-ticket-com');
	$feedback_vars['cancel'] = __('Cancel', 'wp-ticket-com');
	$feedback_vars['ask_reason'] = __('Please share the reason so we can improve', 'wp-ticket-com');
	$feedback_vars['nonce'] = wp_create_nonce('wp_ticket_com_deactivate_nonce');
	if ($is_long_term_user) {
		$reasons[1] = __('I no longer need the plugin', 'wp-ticket-com');
		$reasons[3] = __('I only needed the plugin for a short period', 'wp-ticket-com');
		$reasons[9] = __('The plugin update did not work as expected', 'wp-ticket-com');
		$reasons[5] = __('The plugin suddenly stopped working', 'wp-ticket-com');
		$reasons[2] = __('I found a better plugin', 'wp-ticket-com');
	} else {
		$reasons[21] = __('I couldn\'t understand how to make it work', 'wp-ticket-com');
		$reasons[22] = __('The plugin is not working', 'wp-ticket-com');
		$reasons[23] = __('It\'s not what I was looking for', 'wp-ticket-com');
		$reasons[24] = __('The plugin didn\'t work as expected', 'wp-ticket-com');
		$reasons[8] = __('The plugin is great, but I need a specific feature that is not currently supported', 'wp-ticket-com');
		$reasons[2] = __('I found a better plugin', 'wp-ticket-com');
	}
	$shuffle_keys = array_keys($reasons);
	shuffle($shuffle_keys);
	foreach ($shuffle_keys as $key) {
		$new_reasons[$key] = $reasons[$key];
	}
	$reasons = $new_reasons;
	//all
	$reasons[6] = __('It\'s a temporary deactivation. I\'m just debugging an issue', 'wp-ticket-com');
	$reasons[7] = __('Other', 'wp-easy-contact');
	$feedback_vars['disclaimer'] = __('No private information is sent during your submission. Thank you very much for your help improving our plugin.', 'wp-ticket-com');
	$feedback_vars['reasons'] = '';
	foreach ($reasons as $key => $reason) {
		$feedback_vars['reasons'].= '<li class="reason';
		if (in_array($key, Array(
			2,
			7,
			8,
			9,
			5,
			22,
			23,
			24
		))) {
			$feedback_vars['reasons'].= ' has-input';
		}
		$feedback_vars['reasons'].= '"';
		switch ($key) {
			case 2:
				$feedback_vars['reasons'].= 'data-input-type="textfield"';
				$feedback_vars['reasons'].= 'data-input-placeholder="' . __('Please share the plugin name', 'wp-ticket-com') . '"';
			break;
			case 8:
				$feedback_vars['reasons'].= 'data-input-type="textarea"';
				$feedback_vars['reasons'].= 'data-input-placeholder="' . __('Please share the feature that you were looking for so that we can develop it in the future releases', 'wp-ticket-com') . '"';
			break;
			case 9:
				$feedback_vars['reasons'].= 'data-input-type="textarea"';
				$feedback_vars['reasons'].= 'data-input-placeholder="' . __('We are sorry to hear that. Please share your previous version number before update, new updated version number and what happened', 'wp-ticket-com') . '"';
			break;
			case 5:
				$feedback_vars['reasons'].= 'data-input-type="textarea"';
				$feedback_vars['reasons'].= 'data-input-placeholder="' . __('We are sorry to hear that. Please share what happened', 'wp-ticket-com') . '"';
			break;
			case 22:
				$feedback_vars['reasons'].= 'data-input-type="textarea"';
				$feedback_vars['reasons'].= 'data-input-placeholder="' . __('Please share what didn\'t work so we can fix it in the future releases', 'wp-ticket-com') . '"';
			break;
			case 23:
				$feedback_vars['reasons'].= 'data-input-type="textarea"';
				$feedback_vars['reasons'].= 'data-input-placeholder="' . __('Please share what you were looking for', 'wp-ticket-com') . '"';
			break;
			case 24:
				$feedback_vars['reasons'].= 'data-input-type="textarea"';
				$feedback_vars['reasons'].= 'data-input-placeholder="' . __('Please share what you expected', 'wp-ticket-com') . '"';
			break;
			default:
			break;
		}
		$feedback_vars['reasons'].= '><label><span>
                                        <input type="radio" name="selected-reason" value="' . $key . '"/>
                                        </span><span>' . $reason . '</span></label></li>';
	}
	wp_enqueue_script('emd-plugin-feedback', WP_TICKET_COM_PLUGIN_URL . 'assets/js/emd-plugin-feedback.js');
	wp_localize_script("emd-plugin-feedback", 'plugin_feedback_vars', $feedback_vars);
	wp_enqueue_script('wp-ticket-com-feedback', WP_TICKET_COM_PLUGIN_URL . 'assets/js/wp-ticket-com-feedback.js');
	$wp_ticket_com_vars['plugin'] = 'wp_ticket_com';
	wp_localize_script("wp-ticket-com-feedback", 'wp_ticket_com_vars', $wp_ticket_com_vars);
}
function wp_ticket_com_send_deactivate_reason() {
	if (empty($_POST['deactivate_nonce']) || !isset($_POST['reason_id'])) {
		exit;
	}
	if (!wp_verify_nonce($_POST['deactivate_nonce'], 'wp_ticket_com_deactivate_nonce')) {
		exit;
	}
	$reason_info = isset($_POST['reason_info']) ? sanitize_text_field($_POST['reason_info']) : '';
	$postfields['utype'] = intval($_POST['utype']);
	$postfields['reason_id'] = intval($_POST['reason_id']);
	$postfields['plugin_name'] = sanitize_text_field($_POST['plugin_name']);
	if (!empty($reason_info)) {
		$postfields['reason_info'] = $reason_info;
	}
	$args = array(
		'body' => $postfields,
		'sslverify' => false,
		'timeout' => 15,
	);
	$resp = wp_remote_post('https://api.emarketdesign.com/deactivate_info.php', $args);
	echo 1;
	exit;
}
