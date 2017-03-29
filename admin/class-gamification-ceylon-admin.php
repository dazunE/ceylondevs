<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://dasun.blog
 * @since      1.0.0
 *
 * @package    Gamification_Ceylon
 * @subpackage Gamification_Ceylon/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Gamification_Ceylon
 * @subpackage Gamification_Ceylon/admin
 * @author     Dasun Edirisinghe <dazunj4me@gmail.com>
 */
class Gamification_Ceylon_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Gamification_Ceylon_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Gamification_Ceylon_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/gamification-ceylon-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Gamification_Ceylon_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Gamification_Ceylon_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/gamification-ceylon-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Register required plugins
	 * @since    1.0.0
	 */

	public function gamification_ceylon_required_plugins() {

		$plugins = array(

			array(
			'name'               => 'MyCred', // The plugin name.
			'slug'               => 'mycred', // The plugin slug (typically the folder name).
			'required'           => true, // If false, the plugin is only 'recommended' instead of required.
			),

			array(
				'name'		=> 'Meta Boxes',
				'slug'		=> 'cmb2',
				'required'	=> true,
			),

		);

		$config = array(
			'id'           => 'gamification-ceylon',    
			'default_path' => '',                      
			'menu'         => 'gamification-required-plugins',
			'parent_slug'  => 'plugins.php',            
			'capability'   => 'manage_options',
			'has_notices'  => true,             
			'dismissable'  => true,                    
			'dismiss_msg'  => '',                     
			'is_automatic' => false,                   
			'message'      => '', 
		);

		tgmpa( $plugins, $config );
	}

	public function gamification_ceylon_user_profile_extended_api ( ) {

		register_rest_field( 'user' , 'role' , array(

			'get_callback' => function ( $user_obj ) {

				$role = array( $user_obj['roles'] )[0];
				return $role;
			},

			'schema' => array(
				'description' => __('Assigned user role'),
				'type'		  => 'string'
			)
		));

		register_rest_field( 'user' , 'status' , array(

			'get_callback' => function ( $user_obj ) {

				$status = get_user_meta($user_obj['id'],'gamification_disable_user');

				if( $status[0] == "1"){

					$status = 'inactive';

				} else if ( $status[0] == "0") {

					$status = 'active';
				}

				return $status;
			},

			'schema' => array(
				'description' => __('User account status'),
				'type'		  => 'string'
			)
		));

		register_rest_field( 'user' , 'engineering_id' , array(

			'get_callback' => function ( $user_obj ) {

				$engineering_id = get_user_meta($user_obj['id'],'engineering_id');

				return $engineering_id[0];
			},

			'schema' => array(
				'description' => __('Unique Engineering Id'),
				'type'		  => 'string'
			)
		));

		register_rest_field( 'user' , 'department' , array(
			'get_callback' => function ( $user_obj ) {

				$department_id = get_user_meta($user_obj['id'],'user_department',true);

				$term = get_term( $department_id ,'user-department');

				$department = array(

					'id' 	=> $term->term_id,
					'name' 	=> $term->name,
					'slug' 	=> $term->slug
				);
				return $department;
			},

			'schema' => array(
				'description' => __('Assigned Department'),
				'type'		  => 'string'
			)
		));

		register_rest_field( 'user' , 'cluster' , array(

			'get_callback' => function ( $user_obj ) {

				$cluster_id = get_user_meta($user_obj['id'],'user_cluster',true);

				$term = get_term( $cluster_id ,'user-cluster');

				$is_superviser = get_user_meta( $user_obj['id'],'is_supervisor', true );

				$args = array(
					 'meta_key'     => 'user_cluster',
					 'meta_value'   => $term->term_id
					);

				$users = get_users($args);

				$sub_ordinates = array();


				foreach ($users as $single_user) {
					
					array_push($sub_ordinates, $single_user->ID);
				}

				$cluster = array(

					'id' 			=> $term->term_id,
					'name' 			=> $term->name,
					'slug' 			=> $term->slug,
					'is_superviser' => $is_superviser,
					'users'			=> $sub_ordinates
				);
				return $cluster;
			},

			'schema' => array(
				'description' => __('Assigned Cluster'),
				'type'		  => 'string'
			)
		));

		register_rest_field( 'user' , 'points' , array(

			'get_callback' => function ( $user_obj ) {

				$points = (int) get_user_meta( $user_obj['id'], 'mycred_default', true);

				return $points;
			},

			'schema' => array(
				'description' => __('All points belongs to users'),
				'type'		  => 'integer'
			)
		));
	}

	/**
	* Registers a new post type
	* @uses $wp_post_types Inserts new post type object into the list
	*
	* @param string  Post type key, must not exceed 20 characters
	* @param array|string  See optional args description above.
	* @return object|WP_Error the registered post type object, or an error object
	*/
	public function gamification_ceylon_register_post_types() {

		$post_types = array(

			array(

				'Name' => 'Target',
				'slug' => 'target',
				'icon' => 'dashicons-chart-line',
				'hirachy' => false,
			),

			array(

				'Name' => 'Invoice',
				'slug' => 'invoice',
				'icon' => 'dashicons-media-spreadsheet',
				'hirachy' => true,
			),


		);

		foreach ($post_types as $post_type) {
			
			$labels = array(
			'name'                  => _x( $post_type['Name'].'s', 'Post Type General Name', 'ceylon-gamification' ),
			'singular_name'         => _x( $post_type['Name'], 'Post Type Singular Name', 'ceylon-gamification' ),
			'menu_name'             => __( $post_type['Name'].'s', 'ceylon-gamification' ),
			'name_admin_bar'        => __( $post_type['Name'], 'ceylon-gamification' ),
			'archives'              => __( $post_type['Name'].' Archives', 'ceylon-gamification' ),
			'attributes'            => __( $post_type['Name'].' Attributes', 'ceylon-gamification' ),
			'parent_item_colon'     => __( 'Parent '.$post_type['Name'].':', 'ceylon-gamification' ),
			'all_items'             => __( 'All '.$post_type['Name'].'', 'ceylon-gamification' ),
			'add_new_item'          => __( 'Add New '.$post_type['Name'], 'ceylon-gamification' ),
			'add_new'               => __( 'Add New', 'ceylon-gamification' ),
			'new_item'              => __( 'New '.$post_type['Name'], 'ceylon-gamification' ),
			'edit_item'             => __( 'Edit '.$post_type['Name'], 'ceylon-gamification' ),
			'update_item'           => __( 'Update '.$post_type['Name'], 'ceylon-gamification' ),
			'view_item'             => __( 'View '.$post_type['Name'], 'ceylon-gamification' ),
			'view_items'            => __( 'View '.$post_type['Name'].'s', 'ceylon-gamification' ),
			'search_items'          => __( 'Search '.$post_type['Name'], 'ceylon-gamification' ),
			'not_found'             => __( 'Not found', 'ceylon-gamification' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ceylon-gamification' ),
			'featured_image'        => __( 'Featured Image', 'ceylon-gamification' ),
			'set_featured_image'    => __( 'Set featured image', 'ceylon-gamification' ),
			'remove_featured_image' => __( 'Remove featured image', 'ceylon-gamification' ),
			'use_featured_image'    => __( 'Use as featured image', 'ceylon-gamification' ),
			'insert_into_item'      => __( 'Insert into item', 'ceylon-gamification' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'ceylon-gamification' ),
			'items_list'            => __( $post_type['Name'].'s list', 'ceylon-gamification' ),
			'items_list_navigation' => __( $post_type['Name'].'s list navigation', 'ceylon-gamification' ),
			'filter_items_list'     => __( 'Filter '.$post_type['Name'].'s list', 'ceylon-gamification' ),
			);
			$capabilities = array(
				'edit_post'             => 'edit_'.$post_type['slug'],
				'read_post'             => 'read_'.$post_type['slug'],
				'delete_post'           => 'delete_'.$post_type['slug'],
				'delete_others_posts'   => 'delete_others_'.$post_type['slug'].'s',
				'delete_published_posts'=> 'delete_published_'.$post_type['slug'].'s',
				'delete_private_posts'  => 'delete_private_'.$post_type['slug'].'s',
				'edit_posts'            => 'edit_'.$post_type['slug'].'s',
				'edit_others_posts'     => 'edit_others_'.$post_type['slug'].'s',
				'edit_private_posts'    => 'edit_private_'.$post_type['slug'].'s',
				'publish_posts'         => 'publish_'.$post_type['slug'].'s',
				'read_private_posts'    => 'read_private_'.$post_type['slug'].'s',
			);


			$args = array(
				'label'                 => __( $post_type['Name'], 'ceylon-gamification' ),
				'description'           => __( 'Create '.$post_type['Name'].'s', 'ceylon-gamification' ),
				'labels'                => $labels,
				'supports'              => array( 'title', 'author', 'revisions',),
				'hierarchical'          => $post_type['hirachy'],
				'public'                => true,
				'show_ui'               => true,
				'show_in_menu'          => true,
				'menu_icon'             => $post_type['icon'],
				'show_in_admin_bar'     => true,
				'show_in_nav_menus'     => true,
				'can_export'            => true,
				'has_archive'           => true,		
				'exclude_from_search'   => false,
				'publicly_queryable'    => true,
				//'capabilities'          => $capabilities,
				'show_in_rest'          => true,
				'rest_base'             => $post_type['slug'].'s',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
			);

			register_post_type( $post_type['slug'], $args );

			$status = array(
				'open'	=> 'Opened',
				'settled' => 'Settled',
				'cancelled' => 'Cancelled'
			);

			foreach ($status as $key => $value ) {
				
				register_post_status( $key, array(
				'label'                       => __( $value, 'wp-statuses' ),
				'label_count'                 => _n_noop( $value.' <span class="count">(%s)</span>', $value.'<span class="count">(%s)</span>', 'wp-statuses' ),
				'public'                      => true,
				'show_in_admin_all_list'      => true,
				'show_in_admin_status_list'   => true,
				'post_type'                   => array( 'invoice' ), // Only for posts!
				'show_in_metabox_dropdown'    => true,
				'show_in_inline_dropdown'     => true,
				'show_in_press_this_dropdown' => true,
				'labels'                      => array(
					'metabox_dropdown' => __( $value, 'wp-statuses' ),
					'inline_dropdown'  => __( $value, 'wp-statuses' ),
					),
				) 
			  );
			}

		}

	}
	


	


	public function remove_additional_admin_items ( ) {


		$items = array(
			'edit.php','upload.php','edit.php?post_type=page','themes.php','plugins.php','tools.php','options-general.php','edit-comments.php','edit.php?post_type=acf-field-group','admin.php?page=wo_manage_clients',
		);

		$user_id = get_current_user_id();

		
		if(($user_id != 1) ) {
			
			foreach ($items as $key ) {
				
				remove_menu_page( $key );
			}
		}
	}




}


add_filter( 'manage_invoice_posts_columns', 'set_custom_edit_invoice_columns' );
add_action( 'manage_invoice_posts_custom_column' , 'custom_invoice_column', 10, 2 );
add_filter( 'manage_edit-invoice_columns', 'set_custom_edit_invoice_columns',10, 1 );

function set_custom_edit_invoice_columns($columns) {
    
    
    unset($columns['date']);
    unset($columns['coauthors']);

    $columns['invoice_number'] 			= __( 'Invoice Number', 'gamification-ceylon' );
    $columns['author'] 					= __( 'Salesman', 'gamification-ceylon' );
    $columns['invoice_date'] 			= __( 'Invoice Date', 'gamification-ceylon' );
    $columns['total_amount']			= __( 'Total Amount', 'gamification-ceylon' );
    $columns['customer_code']			= __( 'Customer Code', 'gamification-ceylon' );
    $columns['status']			= __( 'Status', 'gamification-ceylon' );


    return $columns;
}

function custom_invoice_column( $column, $post_id ) {
    switch ( $column ) {

        case 'invoice_number' :
            $invoice_number = get_field('invoice_number', $post_id );
            echo ( $invoice_number ) ? $invoice_number : '-';
            break;

        case 'invoice_date' :
            $invoice_date = get_field('invoice_date', $post_id );
            echo ( $invoice_date ) ? $invoice_date : '-';
            break;

        case 'total_amount' :
            $total_amount = get_field('total_amount', $post_id );
            echo ( $total_amount ) ? $total_amount : '-';
            break;

        case 'customer_code' :
            $customer_code = get_field('customer_code', $post_id );
            echo ( $customer_code ) ? $customer_code : '-';
            break;

        case 'status' :
            $status = get_post_status( $post_id );
            echo ( $status ) ? $status : '-';
            break;
    }
}

add_filter( 'manage_edit-invoice_sortable_columns', 'my_sortable_invoice_column' );

function my_sortable_invoice_column( $columns ) {

	unset($columns['author']);
    $columns['author'] = 'slaseman';
    $columns['invoice_number'] = 'invoice_number';
 	$columns['invoice_date'] = 'invoice_date';
	$columns['total_amount'] =  'total_amount';
	$columns['customer_code'] = 'customer_code';
 
    return $columns;
}

add_action( 'pre_get_posts', 'invoice_custom_order' );

function invoice_custom_order( $query ) {
  if ( ! is_admin() )
    return;

  $orderby = $query->get( 'orderby');

  if ( 'invoice_number' == $orderby ) {
    $query->set( 'meta_key', 'invoice_number' );
    $query->set( 'orderby', 'meta_value_num' );
  }

  if ( 'invoice_date' == $orderby ) {
    $query->set( 'meta_key', 'invoice_date' );
    $query->set( 'orderby', 'meta_value_num' );
  }

  if ( 'total_amount' == $orderby ) {
    $query->set( 'meta_key', 'total_amount' );
    $query->set( 'orderby', 'meta_value_num' );
  }

  if ( 'customer_code' == $orderby ) {
    $query->set( 'meta_key', 'customer_code' );
    $query->set( 'orderby', 'meta_value_num' );
  }

   if ( 'customer_code' == $orderby ) {
    $query->set( 'meta_key', 'customer_code' );
    $query->set( 'orderby', 'meta_value_num' );
  }

  if ( 'slaseman' == $orderby ) {
    $query->set( 'orderby', 'author' );
  }

}