<?php 

class Gamification_User_departments {

	/**
	 * @var Gamification_User_departments
	 */
	static $instance = NULL;

	public static function get_instance() {

		if( !self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	function __construct() {
		$this->add_hooks();
	}

	function add_hooks() {

		add_filter('manage_users_columns', array(&$this, 'add_manage_users_columns'), 15, 1);
		add_action('manage_users_custom_column', array(&$this, 'user_column_data'), 15, 3);

		add_action("admin_print_scripts", array(&$this, 'js_includes'));
		add_action("admin_print_styles", array(&$this, 'css_includes'));
		add_action('admin_head', array(&$this, 'colorpicker'));
		add_action('admin_head', array(&$this, 'hide_slug'));

		/* Achieve filtering by User department. A hack that may need refining. */
		add_action('pre_user_query', array(&$this, 'user_query'));

		add_filter('views_users', array(&$this, 'views'));

		/* Bulk edit */
		//add_action('admin_init', array(&$this, 'bulk_edit_action'));
		//add_filter('views_users', array(&$this, 'bulk_edit'));

		add_action('admin_init', array(&$this, 'remove_add_form_actions'), 1000);

		/* Taxonomy-related items */
		add_action('init', array(&$this, 'register_user_taxonomy'));
		add_action('create_term', array(&$this, 'meta_save'), 10, 2);
		add_action('edit_term', array(&$this, 'meta_save'), 10, 2);
		add_action( 'admin_menu', array(&$this,'add_user_department_admin_page'));
		add_filter( "user-department_row_actions", array(&$this,'row_actions'), 1, 2);
		add_action( 'manage_user-department_custom_column', array(&$this,'manage_user_department_column'), 10, 3 );
		add_filter( 'manage_edit-user-department_columns', array(&$this,'manage_user_department_user_column'));

		/* Update the user departments when the edit user page is updated. */
		add_action( 'personal_options_update', array(&$this, 'save_user_user_departments'));
		add_action( 'edit_user_profile_update', array(&$this, 'save_user_user_departments'));

		/* Add section to the edit user page in the admin to select profession. */
		//add_action( 'show_user_profile', array(&$this, 'edit_user_user_department_section'), 99999);
		//add_action( 'edit_user_profile', array(&$this, 'edit_user_user_department_section'), 99999);

		/* Cleanup stuff */
		add_action( 'delete_user', array(&$this, 'delete_term_relationships'));
		add_filter( 'sanitize_user', array(&$this, 'disable_username'));
	}

	public static function get_user_user_departments($user = '') {

		$user_id = is_object( $user ) ? $user->ID : absint( $user );

		if( empty( $user_id ) ) {
			return false;
		}

		$user_departments = wp_get_object_terms($user_id, 'user-department', array('fields' => 'all_with_object_id'));

		return $user_departments;
	}

	static function get_user_user_department_tags($user, $page = null) {

		$terms = self::get_user_user_departments($user);

		if( empty($terms) ) {
			return false;
		}

		$in = array();
		foreach($terms as $term) {
			$href = empty($page) ? add_query_arg(array('user-department' => $term->slug), admin_url('users.php')) : add_query_arg(array('user-department' => $term->slug), $page);
			$color = self::get_meta('department-color', $term->term_id);
			$color = empty( $color ) ? '#ffffff' : $color;
			$in[] = sprintf('%s%s%s', '<a style="text-decoration:none; color:white; cursor: pointer; border:0; padding:2px 3px; float:left; margin:0 .3em .2em 0; border-radius:3px; background-color:'.$color.'; color:'.self::get_text_color($color).';" href="'.esc_url( $href ).'" title="'.esc_attr($term->description).'">', $term->name, '</a>');
		}

		return implode('', $in);
	}

	function row_actions(  $actions, $term ) {
		$actions['view'] = sprintf(__('%sView%s', 'gamification-ceylon'), '<a href="'.esc_url( add_query_arg(array('user-department' => $term->slug), admin_url('users.php')) ).'">', '</a>');
		return $actions;
	}

	function update_user_department_count( $terms, $taxonomy ) {
		global $wpdb;

		foreach ( (array) $terms as $term ) {

			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term ) );

			do_action( 'edit_term_taxonomy', $term, $taxonomy );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
			do_action( 'edited_term_taxonomy', $term, $taxonomy );
		}
	}

	function add_user_department_admin_page() {

		$tax = get_taxonomy( 'user-department' );

		$page = add_users_page(
			esc_attr( $tax->labels->menu_name ),
			esc_attr( $tax->labels->menu_name ),
			$tax->cap->manage_terms,
			'edit-tags.php?taxonomy=' . $tax->name
		);
	}

	function manage_user_department_user_column( $columns ) {

		unset( $columns['posts'], $columns['slug'] );


		$columns['users'] = __( 'Users', 'gamification-ceylon');
		$columns['color'] = __( 'Color', 'gamification-ceylon');

		return $columns;
	}

	function manage_user_department_column( $display, $column, $term_id ) {

		switch($column) {
			case 'users':
				$term = get_term( $term_id, 'user-department' );
				echo '<a href="'.admin_url('users.php?user-department='.$term->slug).'">'.sprintf(_n(__('%s User', 'gamification-ceylon'), __('%s Users', 'gamification-ceylon'), $term->count), $term->count).'</a>';
				break;
			case 'color':
				$color = self::get_meta('department-color', $term_id);
				if(!empty($color)) {
					echo '<div style="width:3.18em; height:3em; background-color:'.self::get_meta('department-color', $term_id).';"></div>';
				}
				break;
		}
		return;
	}


	function edit_user_user_department_section( $user ) {

		$tax = get_taxonomy( 'user-department' );

		/* Make sure the user can assign terms of the profession taxonomy before proceeding. */
		if ( !current_user_can( $tax->cap->assign_terms ) || !current_user_can('edit_users') )
			return;

		/* Get the terms of the 'profession' taxonomy. */
		$terms = get_terms( 'user-department', array( 'hide_empty' => false ) ); ?>

		<h3 id="gamification-ceylon">User departments</h3>
		<table class="form-table">
			<tr>
				<th>
					<label for="user-department" style="font-weight:bold; display:block;"><?php _e( sprintf(_n(__('Add to department', 'gamification-ceylon'), __('Add to departments', 'gamification-ceylon'), sizeof($terms)))); ?></label>
					<a href="<?php echo admin_url('edit-tags.php?taxonomy=user-department'); ?>"><?php _e('Add a User department', 'gamification-ceylon'); ?></a>
				</th>

				<td><?php

					/* If there are any terms available, loop through them and display checkboxes. */
					if ( !empty( $terms ) ) {
						echo '<ul>';
						foreach ( $terms as $term ) {

							$color = self::get_meta('department-color', $term->term_id);
							if(!empty($color)) { $color = ' style="padding:2px .5em; border-radius:3px; background-color:'.$color.'; color:'.self::get_text_color($color).'"'; }
							?>
							<li><input type="checkbox" name="user-department[]" id="user-department-<?php echo esc_attr( $term->slug ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( true, is_object_in_term( $user->ID, 'user-department', $term->slug ) ); ?> /> <label for="user-department-<?php echo esc_attr( $term->slug ); ?>"<?php echo $color; ?>><?php echo $term->name; ?></label></li>
						<?php }
						echo '</ul>';
					}

					/* If there are no user-department terms, display a message. */
					else {
						printf( esc_html__('There are no user departments defined. %sAdd a User department%s', 'gamification-ceylon' ), '<a href="'.esc_url( admin_url('edit-tags.php?taxonomy=user-department') ).'">', '</a>' );
					}

					?></td>
			</tr>
		</table>
	<?php
	}

	// Code from http://serennu.com/colour/rgbtohsl.php
	static function get_text_color($hexcode = '') {
		$hexcode = str_replace('#', '', $hexcode);

		$redhex  = substr($hexcode,0,2);
		$greenhex = substr($hexcode,2,2);
		$bluehex = substr($hexcode,4,2);

		// $var_r, $var_g and $var_b are the three decimal fractions to be input to our RGB-to-HSL conversion routine
		$var_r = (hexdec($redhex)) / 255;
		$var_g = (hexdec($greenhex)) / 255;
		$var_b = (hexdec($bluehex)) / 255;

		$var_min = min($var_r,$var_g,$var_b);
		$var_max = max($var_r,$var_g,$var_b);
		$del_max = $var_max - $var_min;

		$l = ($var_max + $var_min) / 2;

		if ($del_max == 0) {
			$h = 0;
			$s = 0;
		} else {
			if ($l < 0.5){
				$s = $del_max / ($var_max + $var_min);
			} else {
				$s = $del_max / (2 - $var_max - $var_min);
			};

			$del_r = ((($var_max - $var_r) / 6) + ($del_max / 2)) / $del_max;
			$del_g = ((($var_max - $var_g) / 6) + ($del_max / 2)) / $del_max;
			$del_b = ((($var_max - $var_b) / 6) + ($del_max / 2)) / $del_max;

			if ($var_r == $var_max){
				$h = $del_b - $del_g;
			} elseif ($var_g == $var_max){
				$h = (1 / 3) + $del_r - $del_b;
			} elseif ($var_b == $var_max){
				$h = (2 / 3) + $del_g - $del_r;
			};

			if ($h < 0){
				$h += 1;
			};

			if ($h > 1) {
				$h -= 1;
			};
		};

		if(($l * 100) < 50) {
			return 'white';
		} else {
			return 'black';
		}
	}

	function save_user_user_departments( $user_id, $user_departments = array(), $bulk = false) {

		$tax = get_taxonomy( 'user-department' );

		/* Make sure the current user can edit the user and assign terms before proceeding. */
		if ( ! ( current_user_can( 'edit_user', $user_id ) && current_user_can( $tax->cap->assign_terms ) ) ) {
			return false;
		}

		if(empty($user_departments) && !$bulk) {
			$user_departments = isset( $_POST['user-department'] ) ? $_POST['user-department'] : NULL;
		}

		if(is_null($user_departments) || empty($user_departments)) {
			wp_delete_object_term_relationships( $user_id, 'user-department' );
		} else {

			$departments = array();
			foreach($user_departments as $department) {
				$departments[] = esc_attr($department);
			}

			/* Sets the terms (we're just using a single term) for the user. */
			wp_set_object_terms( $user_id, $departments, 'user-department', false);
		}

		clean_object_term_cache( $user_id, 'user-department' );
	}

	function disable_username( $username ) {
		if ( 'user-department' === $username )
			$username = '';

		return $username;
	}

	function delete_term_relationships( $user_id ) {
		wp_delete_object_term_relationships( $user_id, 'user-department' );
	}

	function register_user_taxonomy() {

		register_taxonomy(
			'user-department',
			'user',
			array(
				'public' => false,
				'show_ui' => true,
				'labels' => array(
					'name' => __( 'User Departments', 'gamification-ceylon' ),
					'singular_name' => __( 'Department', 'gamification-ceylon' ),
					'menu_name' => __( 'Departments', 'gamification-ceylon' ),
					'search_items' => __( 'Search Departments', 'gamification-ceylon' ),
					'popular_items' => __( 'Popular Departments', 'gamification-ceylon' ),
					'all_items' => __( 'All User Departments', 'gamification-ceylon' ),
					'edit_item' => __( 'Edit User Department', 'gamification-ceylon' ),
					'update_item' => __( 'Update User Department', 'gamification-ceylon' ),
					'add_new_item' => __( 'Add New User Department', 'gamification-ceylon' ),
					'new_item_name' => __( 'New User Department Name', 'gamification-ceylon' ),
					'separate_items_with_commas' => __( 'Separate user Departments with commas', 'gamification-ceylon' ),
					'add_or_remove_items' => __( 'Add or remove user Departments', 'gamification-ceylon' ),
					'choose_from_most_used' => __( 'Choose from the most popular user Departments', 'gamification-ceylon' ),
				),
				'rewrite' => false,
				'capabilities' => array(
					'manage_terms' => 'edit_users', // Using 'edit_users' cap to keep this simple.
					'edit_terms'   => 'edit_users',
					'delete_terms' => 'edit_users',
					'assign_terms' => 'read',
				),
				'update_count_callback' => array(&$this, 'update_user_department_count') // Use a custom function to update the count. If not working, use _update_post_term_count
			)
		);

	}

	function meta_save($term_id, $tt_id) {

		if(isset($_POST['user-department'])) {

			$term_meta = (array) get_option('user-department-meta');

			$term_meta[$term_id] =  (array) $_POST['user-department'];
			update_option('user-department-meta', $term_meta);

			if(isset($_POST['_wp_original_http_referer'])) {
				wp_safe_redirect($_POST['_wp_original_http_referer']);
				exit();
			}
		}
	}


	function add_colorpicker_field() {
		?>
		<tr>
			<th scope="row" valign="top"><label><?php _e('Color for the User department', 'genesis'); ?></label></th>
			<td id="department-color-row">
				<p>
					<input type="text" name="user-department[department-color]" id="department-color" value="<?php echo self::get_meta('department-color'); ?>" />
					<span class="description hide-if-js"><?php _e('If you want to hide header text, add <strong>#blank</strong> as text color.', 'gamification-ceylon' ); ?></span>
					<input type="button" class="button hide-if-no-js" value="<?php esc_html_e('Select a Color', 'gamification-ceylon'); ?>" id="pickcolor" />
				</p>
				<div id="color-picker" style="z-index: 100; background:#eee; border:1px solid #ccc; position:absolute; display:none;"></div>
			</td>
		</tr>
	<?php
	}

	function hide_slug() {
		if(self::is_edit_user_department('all') ) {
			?>
			<style type="text/css">
				.form-wrap form span.description { display: none!important; }
			</style>

			<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('#menu-posts').removeClass('wp-menu-open wp-has-current-submenu').addClass('wp-not-current-submenu');
					$('#menu-users').addClass('wp-has-current-submenu wp-menu-open menu-top menu-top-first').removeClass('wp-not-current-submenu');
					$('#menu-users a.wp-has-submenu').addClass('wp-has-current-submenu wp-menu-open menu-top');
					$('#menu-posts a.wp-has-submenu').removeClass('wp-has-current-submenu wp-menu-open menu-top');
					$('#tag-slug').parent('div.form-field').hide();
					$('.inline-edit-col input[name=slug]').parents('label').hide();
				});
			</script>
		<?php
		} elseif(self::is_edit_user_department('edit')) {
			?>
			<style type="text/css">
				.form-table .form-field td span.description, .form-table .form-field { display: none; }
			</style>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('#menu-posts').removeClass('wp-menu-open wp-has-current-submenu').addClass('wp-not-current-submenu');
					$('#menu-users').addClass('wp-has-current-submenu wp-menu-open menu-top menu-top-first').removeClass('wp-not-current-submenu');
					$('#menu-users a.wp-has-submenu').addClass('wp-has-current-submenu wp-menu-open menu-top');
					$('#menu-posts a.wp-has-submenu').removeClass('wp-has-current-submenu wp-menu-open menu-top');
					$('#edittag #slug').parents('tr.form-field').addClass('hide-if-js');
					$('.form-table .form-field').not('.hide-if-js').css('display', 'table-row');
				});
			</script>
		<?php
		}
	}

	// Get rid of theme, plugin crap for other taxonomies.
	function remove_add_form_actions($taxonomy) {
		remove_all_actions('after-user-department-table');
		remove_all_actions('user-department_edit_form');
		remove_all_actions('user-department_add_form_fields');

		// If you use Rich Text tags, go ahead!
		if(function_exists('kws_rich_text_tags')) {
			add_action('user-department_edit_form_fields', 'kws_add_form');
			add_action('user-department_add_form_fields', 'kws_add_form');
		}

		add_action('user-department_add_form_fields', array(&$this, 'add_form_color_field'), 10, 2);
		add_action('user-department_edit_form', array(&$this, 'add_form_color_field'), 10, 2);
	}

	function add_form_color_field($tag, $taxonomy = '') {

		$tax = get_taxonomy( $taxonomy );

		if(self::is_edit_user_department('edit')) { ?>

			<h3><?php _e('User department Settings', 'gamification-ceylon'); ?></h3>

			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row" valign="top"><label><?php _e('Color for the User department', 'genesis'); ?></label></th>
					<td id="department-color-row">
						<p>
							<input type="text" name="user-department[department-color]" id="department-color" value="<?php echo self::get_meta('department-color'); ?>" />
							<input type="button" class="button hide-if-no-js" value="Select a Color" id="pickcolor" />
						</p>
						<div id="color-picker" style="z-index: 100; background:#eee; border:1px solid #ccc; position:absolute; display:none;"></div>
						<div class="clear"></div>
					</td>
				</tr>
				</tbody>
			</table>
		<?php  } else { ?>
			<div class="form-field">
				<p>
					<input type="text" style="width:40%" name="user-department[department-color]" id="department-color" value="<?php echo self::get_meta('department-color'); ?>" />
					<input type="button" style="margin-left:.5em;width:auto!important;" class="button hide-if-no-js" value="Select a Color" id="pickcolor" />
				</p>
			</div>
			<div id="color-picker" style="z-index: 100; background:#eee; border:1px solid #ccc; position:absolute; display:none;"></div>
		<?php
		}
	}

	function bulk_edit_action() {
		if (!isset( $_REQUEST['bulkedituserdepartmentsubmit'] ) || empty($_POST['user-department'])) { return; }

		check_admin_referer('bulk-edit-user-department');

		// Get an array of users from the string
		parse_str(urldecode($_POST['users']), $users);

		if(empty($users)) { return; }

		$action = $_POST['departmentaction'];

		foreach($users['users'] as $user) {
			$update_departments = array();
			$departments = self::get_user_user_departments($user);
			foreach($departments as $department) {
				$update_departments[$department->slug] = $department->slug;
			}

			if($action === 'add') {
				if(!in_array($_POST['user-department'], $update_departments)) {
					$update_departments[] = $_POST['user-department'];
				}
			} elseif($action === 'remove') {
				unset($update_departments[$_POST['user-department']]);
			}

			// Delete all user departments if they're empty
			if(empty($update_departments)) { $update_departments = null; }

			self::save_user_user_departments( $user, $update_departments, true);
		}
	}

	function bulk_edit($views) {
		if (!current_user_can('edit_users') ) { return $views; }
		$terms = get_terms('user-department', array('hide_empty' => false));
		?>
		<form method="post" id="bulkedituserdepartmentform" class="alignright" style="clear:right; margin:0 10px;">
			<fieldset>
				<legend class="screen-reader-text"><?php _e('Update User departments', 'gamification-ceylon'); ?></legend>
				<div>
					<label for="departmentactionadd" style="margin-right:5px;"><input name="departmentaction" value="add" type="radio" id="departmentactionadd" checked="checked" /> <?php _e('Add users to', 'gamification-ceylon'); ?></label>
					<label for="departmentactionremove"><input name="departmentaction" value="remove" type="radio" id="departmentactionremove" /> <?php _e('Remove users from', 'gamification-ceylon'); ?></label>
				</div>
				<div>
					<input name="users" value="" type="hidden" id="bulkedituserdepartmentusers" />

					<label for="gamification-ceylon-select" class="screen-reader-text"><?php _('User department', 'gamification-ceylon'); ?></label>
					<select name="user-department" id="gamification-ceylon-select" style="max-width: 300px;">
						<?php
						$select = '<option value="">'.__( 'Select User department&hellip;', 'gamification-ceylon').'</option>';
						foreach($terms as $term) {
							$select .= '<option value="'.$term->slug.'">'.$term->name.'</option>'."\n";
						}
						echo $select;
						?>
					</select>
					<?php wp_nonce_field('bulk-edit-user-department') ?>
				</div>
				<div class="clear" style="margin-top:.5em;">
					<?php submit_button( __( 'Update' ), 'small', 'bulkedituserdepartmentsubmit', false ); ?>
				</div>
			</fieldset>
		</form>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#bulkedituserdepartmentform').remove().insertAfter('ul.subsubsub');
				$('#bulkedituserdepartmentform').live('submit', function() {
					var users = $('.wp-list-table.users .check-column input:checked').serialize();
					$('#bulkedituserdepartmentusers').val(users);
				});
			});
		</script>
		<?php
		return $views;
	}

	function views($views) {
		global $wp_roles;
		$terms = get_terms('user-department', array('hide_empty' => true));

		$select = '<select name="user-department" id="gamification-ceylon-select">
		<option value="0"> '. esc_html__('All Users', 'gamification-ceylon' ) . '</option>'."\n";
		$current = false;
		foreach($terms as $term) {
			$user_ids = get_objects_in_term($term->term_id, 'user-department');
			if(isset($_GET['user-department']) && $_GET['user-department'] === $term->slug) {
				$current = $term;
			}
			$select .= '<option value="'.$term->slug.'"'.selected(true, isset($_GET['user-department']) && $_GET['user-department'] === $term->slug,false).'>'.esc_html( $term->name ).'</option>'."\n";
		}

		$select .= '
	</select>';

		if($current) {
			$bgcolor = self::get_meta('department-color', $current->term_id);
			$color = self::get_text_color($bgcolor);
			$roleli = '';
			$role = false;
			$role_name = __('users','user-department');
			if(isset($_GET['role'])) {
				$role = esc_attr( $_GET['role'] );
				$roles = $wp_roles->get_names();
				if(array_key_exists($role, $roles)) {
					$role_name = $roles["{$role}"];
					if(substr($role_name, -1, 1) !== 's') {
						$role_name .= 's';
					}
				}
			}

			$colorblock = ( $bgcolor === '#' || empty($bgcolor) ) ? '' : '<span style="width:1.18em; height:1.18em; float:left; margin-right:.25em; background-color:'.$bgcolor.';"></span>';

			?>
			<div id="user-department-header">
				<h2><?php echo $colorblock; echo sprintf(__('User department: %s', 'gamification-ceylon'), $current->name); ?> <a href="<?php echo admin_url('edit-tags.php?action=edit&taxonomy=user-department&amp;tag_ID='.$current->term_id.'&post_type=post'); ?>" class="add-new-h2" style="background:#fefefe;"><?php _e('Edit User department', 'gamification-ceylon'); ?></a></h2>
				<?php echo wpautop($current->description); ?>
			</div>
			<p class="howto" style="font-style:normal;">
				<span><?php echo sprintf(__('Showing %s in %s','gamification-ceylon'), $role_name, '&ldquo;'.$current->name.'&rdquo;'); ?>.</span>

				<a href="<?php echo esc_url( remove_query_arg('user-department') );?>" class="user-department-user-department-filter"><span></span> <?php echo sprintf(__('Show all %s','gamification-ceylon'), $role_name);?></a>

				<?php if(!empty($role)) { ?>
					<a href="<?php echo esc_url( remove_query_arg('role') ); ?>" class="user-department-user-department-filter"><span></span> <?php echo sprintf(__('Show all users in "%s"','gamification-ceylon'), $current->name); ?></a>
				<?php } ?>
			</p>
			<div class="clear"></div>
		<?php
		}

		ob_start();

		$args = array();
		if(isset($_GET['s'])) { $args['s'] = $_GET['s']; }
		if(isset($_GET['role'])) { $args['role'] = $_GET['role']; }

		?>
		<label for="gamification-ceylon-select"><?php esc_html_e('User departments:', 'gamification-ceylon'); ?></label>

		<form method="get" action="<?php echo esc_url( preg_replace('/(.*?)\/users/ism', 'users', add_query_arg($args, remove_query_arg('user-department'))) ); ?>" style="display:inline;">
			<?php  echo $select; ?>
		</form>
		<style type="text/css">
			.subsubsub li.user-department { display: inline-block!important; }
		</style>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				<?php if(isset($_GET['user-department'])) { ?>
				$('ul.subsubsub li a').each(function() {
					var $that = $(this);
					$(this).attr('href', function() {
						var sep = $that.attr('href').match(/\?/i) ? '&' : '?';
						return $(this).attr('href') + sep +'user-department=<?php echo esc_attr($_GET['user-department']); ?>';
					});
				});
				<?php } ?>
				$("#gamification-ceylon-select").change(function() {
					var action = $(this).parents("form").attr('action');
					if(action.match(/\?/i)) {
						action = action + '&user-department=' + $(this).val();
					} else {
						action = action + '?user-department=' + $(this).val();
					}

					window.location = action;
				});
			});
		</script>

		<?php
		$form = ob_get_clean();

		$views['user-department'] = $form;
		return $views;

	}

	function user_query($Query = '') {
		global $pagenow,$wpdb;

		if($pagenow !== 'users.php') { return; }

		if(!empty($_GET['user-department'])) {

			$departments = explode(',',$_GET['user-department']);
			$ids = array();
			foreach($departments as $department) {
				$term = get_term_by('slug', esc_attr($department), 'user-department');
				$user_ids = get_objects_in_term($term->term_id, 'user-department');
				$ids = array_merge($user_ids, $ids);
			}
			$ids = implode(',', wp_parse_id_list( $user_ids ) );

			$Query->query_where .= " AND $wpdb->users.ID IN ($ids)";
		}

	}

	function css_includes(){
		if(!self::is_edit_user_department() ) { return; }
		wp_enqueue_style('farbtastic', array('jquery'));
	}

	function js_includes() {
		if(!self::is_edit_user_department() ) { return; }
		wp_enqueue_script('farbtastic', array('jquery'));
	}

	function user_column_data($value, $column_name, $user_id) {

		switch( $column_name ) {
			case 'user-department':
				return self::get_user_user_department_tags( $user_id );
				break;
		}
		return $value;
	}

	/**
	 * Add the label to the table header
	 * @param $defaults
	 *
	 * @return mixed
	 */
	function add_manage_users_columns($defaults) {

		$defaults['user-department'] = __('User department', 'gamification-ceylon');

		return $defaults;
	}

	function colorpicker() {

		if(!self::is_edit_user_department() ) { return; }

		?>
		<script type="text/javascript">
			/* <![CDATA[ */
			var farbtastic;
			var default_color = '#333';
			var old_color = null;

			function pickColor(color) {
				jQuery('#department-color').val(color).css('background', color);
				farbtastic.setColor(color);
				jQuery('#department-color').processColor((farbtastic.hsl[2] * 100), (farbtastic.hsl[1] * 100));
			}

			jQuery(document).ready(function( $ ) {

				$('#pickcolor,#department-color').click(function() {
					$('#color-picker').show();
				});

				$('#defaultcolor').click(function() {
					pickColor(default_color);
					$('#department-color').val(default_color).css('background', default_color)
				});

				$('#department-color').keyup(function() {
					var _hex = $('#department-color').val();
					var hex = _hex;
					if ( hex[0] != '#' )
						hex = '#' + hex;
					hex = hex.replace(/[^#a-fA-F0-9]+/, '');
					if ( hex != _hex )
						jQuery('#department-color').val(hex).css('background', hex);
					if ( hex.length == 4 || hex.length == 7 )
						pickColor( hex );
				});

				$(document).mousedown(function(){
					$('#color-picker').each( function() {
						var display = jQuery(this).css('display');
						if (display == 'block')
							jQuery(this).fadeOut(2);
					});
				});

				farbtastic = $.farbtastic('#color-picker', function(color) { pickColor(color); });
				pickColor($('#department-color').val());
			});

			jQuery.fn.processColor = function(black, sat) {
				if(sat > 40) { black = black - 10;}

				if(black <= 50) {
					jQuery(this).css('color', '#ffffff');
				} else {
					jQuery(this).css('color', 'black');
				}
			};
			/* ]]> */
		</script>
	<?php
	}

	public static function get_meta($key = '', $term_id = 0) {

		if(isset($_GET['tag_ID'])) { $term_id = absint( $_GET['tag_ID'] ); }
		if(empty($term_id)) { return false; }

		$term_meta = (array) get_option('user-department-meta');

		if(!isset($term_meta[$term_id])) { return false; }

		if(!empty($key)) {
			return isset($term_meta[$term_id][$key]) ? $term_meta[$term_id][$key] : false;
		} else {
			return $term_meta[$term_id];
		}

	}

	static function is_edit_user_department($page = false) {
		global $pagenow;

		if(
			(!$page || $page === 'edit') &&
			$pagenow === 'edit-tags.php' &&
			isset($_GET['action']) && $_GET['action'] == 'edit' &&
			isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'user-department'
		){
			return true;
		}

		if(
			(!$page || $page === 'all') &&
			( $pagenow === 'edit-tags.php' || $pagenow === 'term.php' ) &&
			isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'user-department' &&
			(!isset($_GET['action']) || $_GET['action'] !== 'edit')
		) {
			return true;
		}

		return false;
	}
}


new Gamification_User_departments();