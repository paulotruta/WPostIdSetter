<?php
/**
 * Plugin Name: WPostIdSetter
 * Description: Let's you define a custom post_id for your next WordPress published post.
 * Author: paulotruta
 * Version: 1.0
 * Author URI: http://www.smithstories.xyz
 * Text Domain: wpostidsetter
 */
class WPostIdSetter {
	/**
	 * Text domain for translations.
	 *
	 * @var string
	 */
	public $td = 'wppostidsetter';

	/**
	 * Error logs email.
	 *
	 * @var string
	 */
	private $logs_address = '';

	/**
	 * The plugin slug.
	 *
	 * @var string
	 */
	protected static $plugin_slug = 'wpostidsetter';

	/**
	 * The plugin instance pointer var.
	 *
	 * @var null
	 */
	protected static $instance = null;

	/**
	 * Allows the plugin instance to be used as a singleton across a Wordpress requests instance.
	 *
	 * @return class WPostIdSetter current active instance.
	 */
	public static function get_instance() {
		// create an object if not already instantiated, and register it in the class.
		self::$instance = (null === self::$instance) ? new self : null;
		return self::$instance;
	}

	/**
	 * The function build the basic plugin logic, filling all the necessary instance variables.
	 */
	public function __construct() {

		$this -> error = __( 'This given post id cannot be used.', 'wpostidsetter' );

		// Hook to make some work on plugin activation.
		// Run the activate (in this Class scope) when the plugin is activated.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Add the locale loading action.
		add_action( 'init', array( $this, 'load_locale' ) );

		// Defining settings page for this plugin.
        add_action( 'admin_menu', array( $this, 'wpostidsetter_add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'wpostidsetter_settings_init' ) );

		return true;
	}

	/**
	 * Logs any errors to php error log, optionally sending an e-mail to the development team.
	 *
	 * @param  string  $message   The message log.
	 * @param  boolean $send_logs Wether to send to dev team by email.
	 * @return bool             Returns the option to send logs to dev team.
	 */
	private function _log( $message, $send_logs = false ) {

		$prefix = '(WPostIdSetter plugin | ' . get_site_url() . ') => ';
		$message = $prefix . $message;

		// This ensures the development team does not get crowded with debugging e-mails. Hook to the wpostidsetter_mail_logs filter to force all logs to be sent to the dev team.
		$send_logs = $send_logs ? apply_filters( 'wpmodal_mail_logs', false ) : false;

		// First log to local error log.
		error_log( $message, 0 );
		if ( $send_logs ) {
			// If settings says that and email should be sent to the development team, so be it.
			error_log( $message, 1, $this -> logs_address );
		}

		return $send_logs; // Return the option, might be useful...
	}

	/**
	 * Setup locale method
	 */
	public function load_locale() {
		load_plugin_textdomain( $this->td, false, dirname( plugin_basename( __FILE__ ) ) . '/translations' );
	}

	/**
	 * Activation function - Can be used to trigger operations upon activating the plugin.
	 */
	public function activate() {
		// Nothing to do here... for now.
	}

    function get_last_post_id()
    {
        global $wpdb;

        $query = "SELECT ID FROM $wpdb->posts ORDER BY ID DESC LIMIT 0,1";

        $result = $wpdb->get_results($query);
        $row = $result[0];
        $id = $row->ID;

        return $id;
    }

    function set_last_post_id($id) {

		$post_id = null;

		if( !get_post_status() && $id > ( get_last_post_id() + 1 ) ) {
            // Create fake draft post.
            $post = array(
                'import_id' => $id,
                'comment_status' => 'open',
                'post_content' => 'WPostIdSetter',
                'post_name' => 'wpostidsetter',
                'post_status' => 'draft',
                'post_title' => 'wpostidsetter draft post',
                'post_type' => 'post',
            );

            $post_id = wp_insert_post($post);

            // TODO: delete it right after
        } else {
			$this->_log('It is not possible to set the desired post_id.');
		}

        return $post_id;
	}

    function my_error_notice() {
        ?>
		<div class="error notice">
			<p><?php echo( $this->error ); ?></p>
		</div>
        <?php
    }


    function wpostidsetter_add_admin_menu(  ) {

        add_options_page( 'WPostIdSetter', 'WPostIdSetter', 'manage_options', 'wpostidsetter', 'wpostidsetter_options_page' );

    }

    function wpostidsetter_settings_init(  ) {

        register_setting( 'pluginPage', 'wpostidsetter_settings' );

        add_settings_section(
            'wpostidsetter_pluginPage_section',
            __( 'Next post_id', 'wpostidsetter' ),
            'wpostidsetter_settings_section_callback',
            'pluginPage'
        );

		$lowest_possible_post_id = $this->get_last_post_id() + 1;
		$field_text = sprintf( 'Insert a number below, bigger than %s', $lowest_possible_post_id );

        add_settings_field(
            'wpostidsetter_text_field_0',
            __( $field_text, 'wpostidsetter' ),
            'wpostidsetter_text_field_0_render',
            'pluginPage',
            'wpostidsetter_pluginPage_section'
        );


    }

    function wpostidsetter_text_field_0_render(  ) {

        $options = get_option( 'wpostidsetter_settings' );
        ?>
		<input type='text' name='wpostidsetter_settings[wpostidsetter_text_field_0]' value='<?php echo $options['wpostidsetter_text_field_0']; ?>'>
        <?php

    }

    function wpostidsetter_settings_section_callback(  ) {

        echo __( 'This page allows to tweak some behaviour about WordPress internal workings.', 'wpostidsetter' );

    }

    function wpostidsetter_options_page(  ) {

		$new_post_id = null;
		if( ! empty( $_POST['wpostidsetter_settings[wpostidsetter_text_field_0]'] ) ) {
			$new_post_id = $this->set_last_post_id($_POST['wpostidsetter_settings[wpostidsetter_text_field_0]']);
		}

        ?>
		<form action='options.php' method='post'>

			<h2>WPostIdSetter</h2>

            <?php
				if( empty( $new_post_id ) ) {
                    add_action( 'admin_notices', 'my_error_notice' );
				}
            	settings_fields( 'pluginPage' );
            	do_settings_sections( 'pluginPage' );
            	submit_button();
            ?>

		</form>
        <?php

    }

} // End class WPostIdSetter.
// Initialize the plugin class by instantiating the singleton!
WPostIdSetter::get_instance();
?>