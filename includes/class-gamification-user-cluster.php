<?php 

class Gamification_User_clusters {

	/**
	 * @var Gamification_User_clusters
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

		/* Achieve filtering by User cluster. A hack that may need refining. */
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
		add_action( 'admin_menu', array(&$this,'add_user_cluster_admin_page'));
		add_filter( "user-cluster_row_actions", array(&$this,'row_actions'), 1, 2);
		add_action( 'manage_user-cluster_custom_column', array(&$this,'manage_user_cluster_column'), 10, 3 );
		add_filter( 'manage_edit-user-cluster_columns', array(&$this,'manage_user_cluster_user_column'));

		/* Update the user clusters when the edit user page is updated. */
		add_action( 'personal_options_update', array(&$this, 'save_user_user_clusters'));
		add_action( 'edit_user_profile_update', array(&$this, 'save_user_user_clusters'));

		/* Add section to the edit user page in the admin to select profession. */
		//add_action( 'show_user_profile', array(&$this, 'edit_user_user_cluster_section'), 99999);
		//add_action( 'edit_user_profile', array(&$this, 'edit_user_user_cluster_section'), 99999);

		/* Cleanup stuff */
		add_action( 'delete_user', array(&$this, 'delete_term_relationships'));
		add_filter( 'sanitize_user', array(&$this, 'disable_username'));
	}

	public static function get_user_user_clusters($user = '') {

		$user_id = is_object( $user ) ? $user->ID : absint( $user );

		if( empty( $user_id ) ) {
			return false;
		}

		$user_clusters = wp_get_object_terms($user_id, 'user-cluster', array('fields' => 'all_with_object_id'));

		return $user_clusters;
	}

	static function get_user_user_cluster_tags($user, $page = null) {

		$terms = self::get_user_user_clusters($user);

		if( empty($terms) ) {
			return false;
		}

		$in = array();
		foreach($terms as $term) {
			$href = empty($page) ? add_query_arg(array('user-cluster' => $term->slug), admin_url('users.php')) : add_query_arg(array('user-cluster' => $term->slug), $page);
			$color = self::get_meta('cluster-color', $term->term_id);
			$color = empty( $color ) ? '#ffffff' : $color;
			$in[] = sprintf('%s%s%s', '<a style="text-decoration:none; color:white; cursor: pointer; border:0; padding:2px 3px; float:left; margin:0 .3em .2em 0; border-radius:3px; background-color:'.$color.'; color:'.self::get_text_color($color).';" href="'.esc_url( $href ).'" title="'.esc_attr($term->description).'">', $term->name, '</a>');
		}

		return implode('', $in);
	}

	function row_actions(  $actions, $term ) {
		$actions['view'] = sprintf(__('%sView%s', 'gamification-ceylon'), '<a href="'.esc_url( add_query_arg(array('user-cluster' => $term->slug), admin_url('users.php')) ).'">', '</a>');
		return $actions;
	}

	function update_user_cluster_count( $terms, $taxonomy ) {
		global $wpdb;

		foreach ( (array) $terms as $term ) {

			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term ) );

			do_action( 'edit_term_taxonomy', $term, $taxonomy );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
			do_action( 'edited_term_taxonomy', $term, $taxonomy );
		}
	}

	function add_user_cluster_admin_page() {

		$tax = get_taxonomy( 'user-cluster' );

		$page = add_users_page(
			esc_attr( $tax->labels->menu_name ),
			esc_attr( $tax->labels->menu_name ),
			$tax->cap->manage_terms,
			'edit-tags.php?taxonomy=' . $tax->name
		);
	}

	function manage_user_cluster_user_column( $columns ) {

		unset( $columns['posts'], $columns['slug'] );


		$columns['users'] = __( 'Users', 'gamification-ceylon');
		$columns['color'] = __( 'Color', 'gamification-ceylon');

		return $columns;
	}

	function manage_user_cluster_column( $display, $column, $term_id ) {

		switch($column) {
			case 'users':
				$term = get_term( $term_id, 'user-cluster' );
				echo '<a href="'.admin_url('users.php?user-cluster='.$term->slug).'">'.sprintf(_n(__('%s User', 'gamification-ceylon'), __('%s Users', 'gamification-ceylon'), $term->count), $term->count).'</a>';
				break;
			case 'color':
				$color = self::get_meta('cluster-color', $term_id);
				if(!empty($color)) {
					echo '<div style="width:3.18em; height:3em; background-color:'.self::get_meta('cluster-color', $term_id).';"></div>';
				}
				break;
		}
		return;
	}


	function edit_user_user_cluster_section( $user ) {

		$tax = get_taxonomy( 'user-cluster' );

		/* Make sure the user can assign terms of the profession taxonomy before proceeding. */
		if ( !current_user_can( $tax->cap->assign_terms ) || !current_user_can('edit_users') )
			return;

		/* Get the terms of the 'profession' taxonomy. */
		$terms = get_terms( 'user-cluster', array( 'hide_empty' => false ) ); ?>

		<h3 id="gamification-ceylon">User clusters</h3>
		<table class="form-table">
			<tr>
				<th>
					<label for="user-cluster" style="font-weight:bold; display:block;"><?php _e( sprintf(_n(__('Add to cluster', 'gamification-ceylon'), __('Add to clusters', 'gamification-ceylon'), sizeof($terms)))); ?></label>
					<a href="<?php echo admin_url('edit-tags.php?taxonomy=user-cluster'); ?>"><?php _e('Add a User cluster', 'gamification-ceylon'); ?></a>
				</th>

				<td><?php

					/* If there are any terms available, loop through them and display checkboxes. */
					if ( !empty( $terms ) ) {
						echo '<ul>';
						foreach ( $terms as $term ) {

							$color = self::get_meta('cluster-color', $term->term_id);
							if(!empty($color)) { $color = ' style="padding:2px .5em; border-radius:3px; background-color:'.$color.'; color:'.self::get_text_color($color).'"'; }
							?>
							<li><input type="checkbox" name="user-cluster[]" id="user-cluster-<?php echo esc_attr( $term->slug ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( true, is_object_in_term( $user->ID, 'user-cluster', $term->slug ) ); ?> /> <label for="user-cluster-<?php echo esc_attr( $term->slug ); ?>"<?php echo $color; ?>><?php echo $term->name; ?></label></li>
						<?php }
						echo '</ul>';
					}

					/* If there are no user-cluster terms, display a message. */
					else {
						printf( esc_html__('There are no user clusters defined. %sAdd a User cluster%s', 'gamification-ceylon' ), '<a href="'.esc_url( admin_url('edit-tags.php?taxonomy=user-cluster') ).'">', '</a>' );
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

	function save_user_user_clusters( $user_id, $user_clusters = array(), $bulk = false) {

		$tax = get_taxonomy( 'user-cluster' );

		/* Make sure the current user can edit the user and assign terms before proceeding. */
		if ( ! ( current_user_can( 'edit_user', $user_id ) && current_user_can( $tax->cap->assign_terms ) ) ) {
			return false;
		}

		if(empty($user_clusters) && !$bulk) {
			$user_clusters = isset( $_POST['user-cluster'] ) ? $_POST['user-cluster'] : NULL;
		}

		if(is_null($user_clusters) || empty($user_clusters)) {
			wp_delete_object_term_relationships( $user_id, 'user-cluster' );
		} else {

			$clusters = array();
			foreach($user_clusters as $cluster) {
				$clusters[] = esc_attr($cluster);
			}

			/* Sets the terms (we're just using a single term) for the user. */
			wp_set_object_terms( $user_id, $clusters, 'user-cluster', false);
		}

		clean_object_term_cache( $user_id, 'user-cluster' );
	}

	function disable_username( $username ) {
		if ( 'user-cluster' === $username )
			$username = '';

		return $username;
	}

	function delete_term_relationships( $user_id ) {
		wp_delete_object_term_relationships( $user_id, 'user-cluster' );
	}

	function register_user_taxonomy() {

		register_taxonomy(
			'user-cluster',
			'user',
			array(
				'public' => false,
				'show_ui' => true,
				'labels' => array(
					'name' => __( 'User Clusters', 'gamification-ceylon' ),
					'singular_name' => __( 'Cluster', 'gamification-ceylon' ),
					'menu_name' => __( 'Clusters', 'gamification-ceylon' ),
					'search_items' => __( 'Search Clusters', 'gamification-ceylon' ),
					'popular_items' => __( 'Popular Clusters', 'gamification-ceylon' ),
					'all_items' => __( 'All User Clusters', 'gamification-ceylon' ),
					'edit_item' => __( 'Edit User Cluster', 'gamification-ceylon' ),
					'update_item' => __( 'Update User Cluster', 'gamification-ceylon' ),
					'add_new_item' => __( 'Add New User Cluster', 'gamification-ceylon' ),
					'new_item_name' => __( 'New User Cluster Name', 'gamification-ceylon' ),
					'separate_items_with_commas' => __( 'Separate user Clusters with Commas', 'gamification-ceylon' ),
					'add_or_remove_items' => __( 'Add or remove user Clusters', 'gamification-ceylon' ),
					'choose_from_most_used' => __( 'Choose from the most popular user Clusters', 'gamification-ceylon' ),
				),
				'rewrite' => false,
				'capabilities' => array(
					'manage_terms' => 'edit_users', // Using 'edit_users' cap to keep this simple.
					'edit_terms'   => 'edit_users',
					'delete_terms' => 'edit_users',
					'assign_terms' => 'read',
				),
				'update_count_callback' => array(&$this, 'update_user_cluster_count') // Use a custom function to update the count. If not working, use _update_post_term_count
			)
		);

	}

	function meta_save($term_id, $tt_id) {

		if(isset($_POST['user-cluster'])) {

			$term_meta = (array) get_option('user-cluster-meta');

			$term_meta[$term_id] =  (array) $_POST['user-cluster'];
			update_option('user-cluster-meta', $term_meta);

			if(isset($_POST['_wp_original_http_referer'])) {
				wp_safe_redirect($_POST['_wp_original_http_referer']);
				exit();
			}
		}
	}


	function add_colorpicker_field() {
		?>
		<tr>
			<th scope="row" valign="top"><label><?php _e('Color for the User cluster', 'genesis'); ?></label></th>
			<td id="cluster-color-row">
				<p>
					<input type="text" name="user-cluster[cluster-color]" id="cluster-color" value="<?php echo self::get_meta('cluster-color'); ?>" />
					<span class="description hide-if-js"><?php _e('If you want to hide header text, add <strong>#blank</strong> as text color.', 'gamification-ceylon' ); ?></span>
					<input type="button" class="button hide-if-no-js" value="<?php esc_html_e('Select a Color', 'gamification-ceylon'); ?>" id="pickcolor" />
				</p>
				<div id="color-picker" style="z-index: 100; background:#eee; border:1px solid #ccc; position:absolute; display:none;"></div>
			</td>
		</tr>
	<?php
	}

	function hide_slug() {
		if(self::is_edit_user_cluster('all') ) {
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
		} elseif(self::is_edit_user_cluster('edit')) {
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
		remove_all_actions('after-user-cluster-table');
		remove_all_actions('user-cluster_edit_form');
		remove_all_actions('user-cluster_add_form_fields');

		// If you use Rich Text tags, go ahead!
		if(function_exists('kws_rich_text_tags')) {
			add_action('user-cluster_edit_form_fields', 'kws_add_form');
			add_action('user-cluster_add_form_fields', 'kws_add_form');
		}

		add_action('user-cluster_add_form_fields', array(&$this, 'add_form_color_field'), 10, 2);
		add_action('user-cluster_edit_form', array(&$this, 'add_form_color_field'), 10, 2);
	}

	function add_form_color_field($tag, $taxonomy = '') {

		$tax = get_taxonomy( $taxonomy );

		if(self::is_edit_user_cluster('edit')) { ?>

			<h3><?php _e('User cluster Settings', 'gamification-ceylon'); ?></h3>

			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row" valign="top"><label><?php _e('Color for the User cluster', 'genesis'); ?></label></th>
					<td id="cluster-color-row">
						<p>
							<input type="text" name="user-cluster[cluster-color]" id="cluster-color" value="<?php echo self::get_meta('cluster-color'); ?>" />
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
					<input type="text" style="width:40%" name="user-cluster[cluster-color]" id="cluster-color" value="<?php echo self::get_meta('cluster-color'); ?>" />
					<input type="button" style="margin-left:.5em;width:auto!important;" class="button hide-if-no-js" value="Select a Color" id="pickcolor" />
				</p>
			</div>
			<div id="color-picker" style="z-index: 100; background:#eee; border:1px solid #ccc; position:absolute; display:none;"></div>
		<?php
		}
	}

	function bulk_edit_action() {
		if (!isset( $_REQUEST['bulkedituserclustersubmit'] ) || empty($_POST['user-cluster'])) { return; }

		check_admin_referer('bulk-edit-user-cluster');

		// Get an array of users from the string
		parse_str(urldecode($_POST['users']), $users);

		if(empty($users)) { return; }

		$action = $_POST['clusteraction'];

		foreach($users['users'] as $user) {
			$update_clusters = array();
			$clusters = self::get_user_user_clusters($user);
			foreach($clusters as $cluster) {
				$update_clusters[$cluster->slug] = $cluster->slug;
			}

			if($action === 'add') {
				if(!in_array($_POST['user-cluster'], $update_clusters)) {
					$update_clusters[] = $_POST['user-cluster'];
				}
			} elseif($action === 'remove') {
				unset($update_clusters[$_POST['user-cluster']]);
			}

			// Delete all user clusters if they're empty
			if(empty($update_clusters)) { $update_clusters = null; }

			self::save_user_user_clusters( $user, $update_clusters, true);
		}
	}

	function bulk_edit($views) {
		if (!current_user_can('edit_users') ) { return $views; }
		$terms = get_terms('user-cluster', array('hide_empty' => false));
		?>
		<form method="post" id="bulkedituserclusterform" class="alignright" style="clear:right; margin:0 10px;">
			<fieldset>
				<legend class="screen-reader-text"><?php _e('Update User clusters', 'gamification-ceylon'); ?></legend>
				<div>
					<label for="clusteractionadd" style="margin-right:5px;"><input name="clusteraction" value="add" type="radio" id="clusteractionadd" checked="checked" /> <?php _e('Add users to', 'gamification-ceylon'); ?></label>
					<label for="clusteractionremove"><input name="clusteraction" value="remove" type="radio" id="clusteractionremove" /> <?php _e('Remove users from', 'gamification-ceylon'); ?></label>
				</div>
				<div>
					<input name="users" value="" type="hidden" id="bulkedituserclusterusers" />

					<label for="gamification-ceylon-select" class="screen-reader-text"><?php _('User cluster', 'gamification-ceylon'); ?></label>
					<select name="user-cluster" id="gamification-ceylon-select" style="max-width: 300px;">
						<?php
						$select = '<option value="">'.__( 'Select User cluster&hellip;', 'gamification-ceylon').'</option>';
						foreach($terms as $term) {
							$select .= '<option value="'.$term->slug.'">'.$term->name.'</option>'."\n";
						}
						echo $select;
						?>
					</select>
					<?php wp_nonce_field('bulk-edit-user-cluster') ?>
				</div>
				<div class="clear" style="margin-top:.5em;">
					<?php submit_button( __( 'Update' ), 'small', 'bulkedituserclustersubmit', false ); ?>
				</div>
			</fieldset>
		</form>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#bulkedituserclusterform').remove().insertAfter('ul.subsubsub');
				$('#bulkedituserclusterform').live('submit', function() {
					var users = $('.wp-list-table.users .check-column input:checked').serialize();
					$('#bulkedituserclusterusers').val(users);
				});
			});
		</script>
		<?php
		return $views;
	}

	function views($views) {
		global $wp_roles;
		$terms = get_terms('user-cluster', array('hide_empty' => true));

		$select = '<select name="user-cluster" id="gamification-ceylon-select">
		<option value="0"> '. esc_html__('All Users', 'gamification-ceylon' ) . '</option>'."\n";
		$current = false;
		foreach($terms as $term) {
			$user_ids = get_objects_in_term($term->term_id, 'user-cluster');
			if(isset($_GET['user-cluster']) && $_GET['user-cluster'] === $term->slug) {
				$current = $term;
			}
			$select .= '<option value="'.$term->slug.'"'.selected(true, isset($_GET['user-cluster']) && $_GET['user-cluster'] === $term->slug,false).'>'.esc_html( $term->name ).'</option>'."\n";
		}

		$select .= '
	</select>';

		if($current) {
			$bgcolor = self::get_meta('cluster-color', $current->term_id);
			$color = self::get_text_color($bgcolor);
			$roleli = '';
			$role = false;
			$role_name = __('users','user-cluster');
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
			<div id="user-cluster-header">
				<h2><?php echo $colorblock; echo sprintf(__('User cluster: %s', 'gamification-ceylon'), $current->name); ?> <a href="<?php echo admin_url('edit-tags.php?action=edit&taxonomy=user-cluster&amp;tag_ID='.$current->term_id.'&post_type=post'); ?>" class="add-new-h2" style="background:#fefefe;"><?php _e('Edit User cluster', 'gamification-ceylon'); ?></a></h2>
				<?php echo wpautop($current->description); ?>
			</div>
			<p class="howto" style="font-style:normal;">
				<span><?php echo sprintf(__('Showing %s in %s','gamification-ceylon'), $role_name, '&ldquo;'.$current->name.'&rdquo;'); ?>.</span>

				<a href="<?php echo esc_url( remove_query_arg('user-cluster') );?>" class="user-cluster-user-cluster-filter"><span></span> <?php echo sprintf(__('Show all %s','gamification-ceylon'), $role_name);?></a>

				<?php if(!empty($role)) { ?>
					<a href="<?php echo esc_url( remove_query_arg('role') ); ?>" class="user-cluster-user-cluster-filter"><span></span> <?php echo sprintf(__('Show all users in "%s"','gamification-ceylon'), $current->name); ?></a>
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
		<label for="gamification-ceylon-select"><?php esc_html_e('User clusters:', 'gamification-ceylon'); ?></label>

		<form method="get" action="<?php echo esc_url( preg_replace('/(.*?)\/users/ism', 'users', add_query_arg($args, remove_query_arg('user-cluster'))) ); ?>" style="display:inline;">
			<?php  echo $select; ?>
		</form>
		<style type="text/css">
			.subsubsub li.user-cluster { display: inline-block!important; }
		</style>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				<?php if(isset($_GET['user-cluster'])) { ?>
				$('ul.subsubsub li a').each(function() {
					var $that = $(this);
					$(this).attr('href', function() {
						var sep = $that.attr('href').match(/\?/i) ? '&' : '?';
						return $(this).attr('href') + sep +'user-cluster=<?php echo esc_attr($_GET['user-cluster']); ?>';
					});
				});
				<?php } ?>
				$("#gamification-ceylon-select").change(function() {
					var action = $(this).parents("form").attr('action');
					if(action.match(/\?/i)) {
						action = action + '&user-cluster=' + $(this).val();
					} else {
						action = action + '?user-cluster=' + $(this).val();
					}

					window.location = action;
				});
			});
		</script>

		<?php
		$form = ob_get_clean();

		$views['user-cluster'] = $form;
		return $views;

	}

	function user_query($Query = '') {
		global $pagenow,$wpdb;

		if($pagenow !== 'users.php') { return; }

		if(!empty($_GET['user-cluster'])) {

			$clusters = explode(',',$_GET['user-cluster']);
			$ids = array();
			foreach($clusters as $cluster) {
				$term = get_term_by('slug', esc_attr($cluster), 'user-cluster');
				$user_ids = get_objects_in_term($term->term_id, 'user-cluster');
				$ids = array_merge($user_ids, $ids);
			}
			$ids = implode(',', wp_parse_id_list( $user_ids ) );

			$Query->query_where .= " AND $wpdb->users.ID IN ($ids)";
		}

	}

	function css_includes(){
		if(!self::is_edit_user_cluster() ) { return; }
		wp_enqueue_style('farbtastic', array('jquery'));
	}

	function js_includes() {
		if(!self::is_edit_user_cluster() ) { return; }
		wp_enqueue_script('farbtastic', array('jquery'));
	}

	function user_column_data($value, $column_name, $user_id) {

		switch( $column_name ) {
			case 'user-cluster':
				return self::get_user_user_cluster_tags( $user_id );
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

		$defaults['user-cluster'] = __('User cluster', 'gamification-ceylon');

		return $defaults;
	}

	function colorpicker() {

		if(!self::is_edit_user_cluster() ) { return; }

		?>
		<script type="text/javascript">
			/* <![CDATA[ */
			var farbtastic;
			var default_color = '#333';
			var old_color = null;

			function pickColor(color) {
				jQuery('#cluster-color').val(color).css('background', color);
				farbtastic.setColor(color);
				jQuery('#cluster-color').processColor((farbtastic.hsl[2] * 100), (farbtastic.hsl[1] * 100));
			}

			jQuery(document).ready(function( $ ) {

				$('#pickcolor,#cluster-color').click(function() {
					$('#color-picker').show();
				});

				$('#defaultcolor').click(function() {
					pickColor(default_color);
					$('#cluster-color').val(default_color).css('background', default_color)
				});

				$('#cluster-color').keyup(function() {
					var _hex = $('#cluster-color').val();
					var hex = _hex;
					if ( hex[0] != '#' )
						hex = '#' + hex;
					hex = hex.replace(/[^#a-fA-F0-9]+/, '');
					if ( hex != _hex )
						jQuery('#cluster-color').val(hex).css('background', hex);
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
				pickColor($('#cluster-color').val());
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

		$term_meta = (array) get_option('user-cluster-meta');

		if(!isset($term_meta[$term_id])) { return false; }

		if(!empty($key)) {
			return isset($term_meta[$term_id][$key]) ? $term_meta[$term_id][$key] : false;
		} else {
			return $term_meta[$term_id];
		}

	}

	static function is_edit_user_cluster($page = false) {
		global $pagenow;

		if(
			(!$page || $page === 'edit') &&
			$pagenow === 'edit-tags.php' &&
			isset($_GET['action']) && $_GET['action'] == 'edit' &&
			isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'user-cluster'
		){
			return true;
		}

		if(
			(!$page || $page === 'all') &&
			( $pagenow === 'edit-tags.php' || $pagenow === 'term.php' ) &&
			isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'user-cluster' &&
			(!isset($_GET['action']) || $_GET['action'] !== 'edit')
		) {
			return true;
		}

		return false;
	}
}


new Gamification_User_clusters();