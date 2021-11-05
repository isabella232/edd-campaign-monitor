<?php
/**
 * Plugin Name: Easy Digital Downloads - Campaign Monitor
 * Plugin URI: https://easydigitaldownloads.com/downloads/campaign-monitor/
 * Description: Include a Campaign Monitor signup option with your Easy Digital Downloads checkout.
 * Version: 1.1.1
 * Author: Easy Digital Downloads
 * Author URI: https://easydigitaldownloads.com/
 * Contributors: Pippin Williamson
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if( ! defined( 'EDDCP_PLUGIN_DIR' ) ) {
	define( 'EDDCP_PLUGIN_DIR', dirname( __FILE__ ) );
}

// Define the plugin version as a constant.
if ( ! defined( 'EDDCP_VERSION' ) ) {
	define( 'EDDCP_VERISON', '1.1.1' );
}

// Autoload vendor files.
require_once dirname( __FILE__ ) . '/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| LICENSING / UPDATES
|--------------------------------------------------------------------------
*/

/**
 * Sets up licensing with EDD core.
 * @todo When minimum EDD version is >= 2.11.4, remove checks.
 */
add_action( 'plugins_loaded', function () {
	if ( class_exists( '\\EDD\\Extensions\\ExtensionRegistry' ) ) {
		add_action( 'edd_extension_license_init', function( \EDD\Extensions\ExtensionRegistry $registry ) {
			$registry->addExtension( __FILE__, 'Campaign Monitor', 974, EDDCP_VERISON, 'eddcp_license_key' );
		} );
	} elseif ( class_exists( 'EDD_License' ) ) {
		new EDD_License( __FILE__, 'Campaign Monitor', EDDCP_VERISON, 'Easy Digital Downloads', 'eddcp_license_key', null, 974 );
	}
} );

/**
 * Registers the subsection for EDD Settings.
 *
 * @access public
 * @since  1.1.2
 *
 * @param  array $sections Settings Sections.
 *
 * @return array Sections with Campaign Monitor added.
 */
function eddcp_settings_section( $sections ) {
	$sections['campaignmonitor'] = __( 'Campaign Monitor', 'eddcp' );

	return $sections;
}
add_filter( 'edd_settings_sections_extensions', 'eddcp_settings_section' );

// adds the settings to the Misc section
function eddcp_add_settings($settings) {

  $eddcp_settings = array(
		array(
			'id' => 'eddcp_settings',
			'name' => '<strong>' . __('Campaign Monitor Settings', 'eddcp') . '</strong>',
			'desc' => __('Configure Campaign Monitor Integration Settings', 'eddcp'),
			'type' => 'header'
		),
		array(
			'id' => 'eddcp_api',
			'name' => __('Campaign Monitor API Key', 'eddcp'),
			'desc' => __('Enter your Campaign Monitor API key. This can be found under your Account Settings', 'eddcp'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'eddcp_client',
			'name' => __('Campaign Monitor Client ID', 'eddcp'),
			'desc' => __('Enter the ID of the client to use. The ID can be found in the Client Settings page of the client.', 'eddcp'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'eddcp_list',
			'name' => __('Choose a list', 'eddcp'),
			'desc' => __('Select the list you wish to subscribe buyers to', 'eddcp'),
			'type' => 'select',
			'options' => eddcp_get_lists()
		),
		array(
			'id' => 'eddcp_label',
			'name' => __('Checkout Label', 'eddcp'),
			'desc' => __('This is the text shown next to the signup option', 'eddcp'),
			'type' => 'text',
			'size' => 'regular'
		)
	);

	if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
		$eddcp_settings = array( 'campaignmonitor' => $eddcp_settings );
	}

	return array_merge( $settings, $eddcp_settings );
}
add_filter('edd_settings_extensions', 'eddcp_add_settings');


/**
 * Gets an array of all Campaign Monitor lists.
 *
 * @return array
 */
function eddcp_get_lists() {
	global $edd_options;

	$lists = array(
		'' => __( 'Select a list', 'eddcp' ),
	);
	if ( empty( $edd_options['eddcp_api'] ) || empty( $edd_options['eddcp_client'] ) ) {
		return $lists;
	}
	$rest_clients = new CS_REST_Clients( $edd_options['eddcp_client'], $edd_options['eddcp_api'] );
	$result       = $rest_clients->get_lists();

	if ( $result->was_successful() ) {
		foreach ( $result->response as $list ) {
			$lists[ $list->ListID ] = $list->Name;
		}
	}

	return $lists;
}

/**
 * Adds a subscriber to the Campaign Monitor list.
 *
 * @param string $email
 * @param string $name
 * @return bool
 */
function eddcp_subscribe_email( $email, $name ) {
	global $edd_options;

	if ( empty( $edd_options['eddcp_api'] ) || empty( $edd_options['eddcp_list'] ) ) {
		return false;
	}

	$rest_subscribers = new CS_REST_Subscribers( trim( $edd_options['eddcp_list'] ), trim( $edd_options['eddcp_api'] ) );

	$join_date        = new stdClass();
	$join_date->key   = 'JoinDate';
	$join_date->value = date( 'm-d-Y H:i:s' );

	$custom_fields = array(
		$join_date,
	);

	$subscribe = $rest_subscribers->add(
		array(
			'EmailAddress'   => $email,
			'Name'           => $name,
			'Resubscribe'    => true,
			'ConsentToTrack' => 'Yes',
			'CustomFields'   => $custom_fields,
		)
	);

	return $subscribe->was_successful();
}

/**
 * Displays the subscribe checkbox.
 *
 * @return void
 */
function eddcp_subscribe_fields() {
	global $edd_options;
	if ( empty( $edd_options['eddcp_api'] ) || empty( $edd_options['eddcp_list'] ) ) {
		return;
	}
	$label = ! empty( $edd_options['eddcp_label'] ) ? $edd_options['eddcp_label'] : __( 'Sign up for our mailing list', 'eddcp' );
	?>
	<p>
		<input name="eddcp_campaign_monitor_signup" id="eddcp_campaign_monitor_signup" type="checkbox" checked="checked"/>
		<label for="eddcp_campaign_monitor_signup"><?php echo esc_html( $label ); ?></label>
	</p>
	<?php
}
add_action( 'edd_purchase_form_before_submit', 'eddcp_subscribe_fields', 100 );

/**
 * Checks whether a user should be signed up for the Campaign Monitor list.
 *
 * @param array $posted    The $_POST data from the checkout.
 * @param array $user_info The array of user information.
 * @return void
 */
function eddcp_check_for_email_signup( $posted, $user_info ) {
	if ( ! empty( $posted['eddcp_campaign_monitor_signup'] ) ) {

		$email = $user_info['email'];
		$name  = $user_info['first_name'] . ' ' . $user_info['last_name'];
		eddcp_subscribe_email( $email, $name );
	}
}
add_action( 'edd_checkout_before_gateway', 'eddcp_check_for_email_signup', 10, 2 );
