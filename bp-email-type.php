<?php
/*
 * Plugin Name: BP Email Type
 * Description: Allow users to choose whether they want to receive HTML or plain-text BuddyPress emails.
 * Author: r-a-y
 * Author URI: http://profiles.wordpress.org/r-a-y
 */

add_action( 'bp_init', array( 'Ray_BP_Email_Type', 'init' ) );

/**
 * Allow a user to set the email type to HTMl or plain-text.
 *
 * When logged in, go to "Settings > Email" and scroll to the "Email Type"
 * section.  Select whether you want to receive HTML email or not.
 */
class Ray_BP_Email_Type {
	/**
	 * Static initializer.
	 */
	public static function init() {
		return new self();
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Only available in BuddyPress 2.5+.
		if ( false === function_exists( 'bp_send_email' ) ) {
			return;
		}

		add_action( 'bp_notification_settings',     array( $this, 'screen' ), 999999 );
		add_filter( 'bp_send_email_delivery_class', array( $this, 'check_user_option' ), 20, 3 );
	}

	/**
	 * Renders notification fields in BuddyPress' settings area
	 */
	public function screen() {
		if ( !$type = bp_get_user_meta( bp_displayed_user_id(), 'notification_html_email', true ) )
			$type = 'yes';
	?>

		<div id="email-type-notification">

			<h3><?php _e( 'Email Type', 'buddypress' ); ?></h3>

			<p><?php _e( 'Choose between HTML or plain-text when receiving email notifications.', 'buddypress' ); ?></p>

			<table class="notification-settings">
				<thead>
					<tr>
						<th class="icon">&nbsp;</th>
						<th class="title">&nbsp;</th>
						<th class="yes"><?php _e( 'Yes', 'buddypress' ) ?></th>
						<th class="no"><?php _e( 'No', 'buddypress' )?></th>
					</tr>
				</thead>

				<tbody>
					<tr>
						<td>&nbsp;</td>
						<td><?php _e( 'Use HTML email?', 'buddypress' ); ?></td>
						<td class="yes"><input type="radio" name="notifications[notification_html_email]" value="yes" <?php checked( $type, 'yes', true ); ?>/></td>
						<td class="no"><input type="radio" name="notifications[notification_html_email]" value="no" <?php checked( $type, 'no', true ); ?>/></td>
					</tr>
				</tbody>
			</table>
		</div>
	<?php
	}

	/**
	 * Checks to see if the recipient has selected to use HTML email or not.
	 *
	 * Only works with BP_PHPMailer at the moment.
	 *
	 * @param string                   $class      Class name for BP email delivery.
	 * @param string                   $email_type BuddyPress email type.
	 * @param string|array|int|WP_User $to         Either a email address, user ID, WP_User object,
	 *                                             or an array containg the address and name.
	 * @return string
	 */
	public function check_user_option( $class, $email_type, $to ) {
		// Only works for BP_PHPMailer.
		if ( 'BP_PHPMailer' !== $class ) {
			return $class;
		}

		// No need to do this for registration emails. Save some queries.
		if ( 'core-user-registration' === $email_type || 'core-user-registration-with-blog' === $email_type ) {
			return $class;
		}

		// Account for arrays. Edge-case. Ugh.
		if ( is_array( $to ) ) {
			$to = array_shift( $to );
			$to = key( $to );
		}

		// Get the email recipient.
		$recipient = new BP_Email_Recipient( $to );

		// Recipient is not a WP user, so bail.
		if ( empty( $recipient->get_user() ) ) {
			return $class;
		}

		// Get user option.
		$is_email_html = bp_get_user_meta( $recipient->get_user()->ID, 'notification_html_email', true );

		// If user wants plain text, let's hook into PHPMailer to set to plaintext.
		if ( $is_email_html == 'no' ) {
			add_action( 'bp_phpmailer_init', array( $this, 'send_plaintext_only' ) );
		}

		return $class;
	}

	/**
	 * Sets up PHPMailer to send BuddyPress emails in plain-text.
	 *
	 * @param obj $phpmailer The PHPMailer object
	 */
	public function send_plaintext_only( $phpmailer ) {
		// Use plain-text as main body.
		$phpmailer->Body = $phpmailer->AltBody;

		// Wipe out the alt body
		$phpmailer->AltBody = '';

		// set HTML to false to be extra-safe!
		$phpmailer->IsHTML( false );

		// Make sure we remove our hook.
		remove_action( 'bp_phpmailer_init', array( $this, 'send_plaintext_only' ) );
	}
}
