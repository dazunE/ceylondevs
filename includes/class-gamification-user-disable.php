<?php


final class gamification_disable_users {
	/**
	 * Initialize all the things
	 *
	 * @since 1.0.0
	 */
	function __construct() {
		// Actions
		add_action( 'init',                       array( $this, 'load_textdomain'             )        );
		add_action( 'show_user_profile',          array( $this, 'use_profile_field'           )        );
		add_action( 'edit_user_profile',          array( $this, 'use_profile_field'           )        );
		add_action( 'personal_options_update',    array( $this, 'user_profile_field_save'     )        );
		add_action( 'edit_user_profile_update',   array( $this, 'user_profile_field_save'     )        );
		add_action( 'wp_login',                   array( $this, 'user_login'                  ), 10, 2 );
		add_action( 'manage_users_custom_column', array( $this, 'manage_users_column_content' ), 10, 3 );
		add_action( 'admin_footer-users.php',	  array( $this, 'manage_users_css'            )        );
		
		// Filters
		add_filter( 'login_message',              array( $this, 'user_login_message'          )        );
		add_filter( 'manage_users_columns',       array( $this, 'manage_users_columns'	      )        );
	}
	/**
	 * Load the textdomain so we can support other languages
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		$domain = 'gamification_disable_users';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	/**
	 * Add the field to user profiles
	 *
	 * @since 1.0.0
	 * @param object $user
	 */
	public function use_profile_field( $user ) {
		// Only show this option to users who can delete other users
		if ( !current_user_can( 'edit_users' ) )
			return;
		?>
		<table class="form-table">
			<tbody>
				<tr>
					<th>
						<label for="gamification_disable_user"><?php _e(' Disable User Account', 'gamification_disable_users' ); ?></label>
					</th>
					<td>
						<input type="checkbox" name="gamification_disable_user" id="gamification_disable_user" value="1" <?php checked( 1, get_the_author_meta( 'gamification_disable_user', $user->ID ) ); ?> />
						<span class="description"><?php _e( 'If checked, the user cannot login with this account.' , 'gamification_disable_users' ); ?></span>
					</td>
				</tr>
			<tbody>
		</table>
		<?php
	}
	/**
	 * Saves the custom field to user meta
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 */
	public function user_profile_field_save( $user_id ) {
		// Only worry about saving this field if the user has access
		if ( !current_user_can( 'edit_users' ) )
			return;
		if ( !isset( $_POST['gamification_disable_user'] ) ) {
			$disabled = 0;
		} else {
			$disabled = $_POST['gamification_disable_user'];
		}
	 
		update_user_meta( $user_id, 'gamification_disable_user', $disabled );
	}
	/**
	 * After login check to see if user account is disabled
	 *
	 * @since 1.0.0
	 * @param string $user_login
	 * @param object $user
	 */
	public function user_login( $user_login, $user = null ) {
		if ( !$user ) {
			$user = get_user_by('login', $user_login);
		}
		if ( !$user ) {
			// not logged in - definitely not disabled
			return;
		}
		// Get user meta
		$disabled = get_user_meta( $user->ID, 'gamification_disable_user', true );
		
		// Is the use logging in disabled?
		if ( $disabled == '1' ) {
			// Clear cookies, a.k.a log user out
			wp_clear_auth_cookie();
			// Build login URL and then redirect
			$login_url = site_url( 'wp-login.php', 'login' );
			$login_url = add_query_arg( 'disabled', '1', $login_url );
			wp_redirect( $login_url );
			exit;
		}
	}
	/**
	 * Show a notice to users who try to login and are disabled
	 *
	 * @since 1.0.0
	 * @param string $message
	 * @return string
	 */
	public function user_login_message( $message ) {
		// Show the error message if it seems to be a disabled user
		if ( isset( $_GET['disabled'] ) && $_GET['disabled'] == 1 ) 
			$message =  '<div id="login_error">' . apply_filters( 'gamification_disable_users_notice', __( 'Account disabled', 'gamification_disable_users' ) ) . '</div>';
		return $message;
	}
	/**
	 * Add custom disabled column to users list
	 *
	 * @since 1.0.3
	 * @param array $defaults
	 * @return array
	 */
	public function manage_users_columns( $defaults ) {
		$defaults['gamification_user_disabled'] = __( 'Disabled', 'gamification_disable_users' );
		return $defaults;
	}
	/**
	 * Set content of disabled users column
	 *
	 * @since 1.0.3
	 * @param empty $empty
	 * @param string $column_name
	 * @param int $user_ID
	 * @return string
	 */
	public function manage_users_column_content( $empty, $column_name, $user_ID ) {
		if ( $column_name == 'gamification_user_disabled' ) {
			if ( get_the_author_meta( 'gamification_disable_user', $user_ID )	== 1 ) {
				return __( 'Disabled', 'gamification_disable_users' );
			}
		}
	}
	/**
	 * Specifiy the width of our custom column
	 *
	 * @since 1.0.3
 	 */
	public function manage_users_css() {
		echo '<style type="text/css">.column-gamification_user_disabled { width: 80px; }</style>';
	}
}
new gamification_disable_users();