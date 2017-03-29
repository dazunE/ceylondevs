<?php 


function get_gamifcation_target_by_user_id ( $user_id ) {

		/**
		 * The WordPress Query class.
		 * @link http://codex.wordpress.org/Function_Reference/WP_Query
		 *
		 */

		$user = get_user_by('id',$user_id['id'] );

		$args = array(
			
			'post_type'   	=> 'target',
			'post_status' 	=> 'publish',
			'author_name'      	=> $user->user_login,
			'post_per_page' => -1,

		);

		$target = array();

		$query = new WP_Query( $args );

		if($query->have_posts()){

			while ($query->have_posts()) {
				
			$query->the_post();

			$months = array('april','may','june','july','august','september','octomber','november','december','january','february','march');

			$salesman = get_field_object('assign_target');

			foreach ($months as $month) {

				$month_filed = $month.'_target';
				
				if(get_field($month_filed)){

					$target_item = array();
					$total_gp    = array();
					$total_to    = array();

					while(the_flexible_field($month_filed)){


							$single_target = array(
						      "productGroup"=> get_sub_field("product_group")->name,
					          "turnover" 	=> get_sub_field("turnover"),
					          "grossProfit" => get_sub_field("gross_profit"),
					          "quantity" 	=> get_sub_field("quantity"),
							);

							array_push($total_gp, get_sub_field("gross_profit"));
							array_push($total_to, get_sub_field("turnover"));
							array_push($target_item, $single_target);
						
					}

				} else {

					$target_item = array();
					$total_gp    = array();
					$total_to    = array();
				}

				$monthly_target = array(

					'Year'  	=> get_field('finnacial_year', get_the_ID()),
					'Month' 	=> $month,
					'totalTO' 	=> array_sum($total_gp),
					'totalGP' 	=> array_sum($total_to),
					'target'  	=> $target_item
				);

				array_push($target,$monthly_target);
			 }

			}
		}

		return array('Year' => $target);
	
}

add_action( 'rest_api_init', function () {
  register_rest_route( 'wp/v2', '/target/user/(?P<id>\d+)', array(
    'methods' => 'GET',
    'callback' => 'get_gamifcation_target_by_user_id',
  ) );
} );


function get_user_by_engineering_id( $engineering_id ) {

	$args = array(
			'meta_key' 		=> 'engineering_id',
			'meta_value' 	=>  $engineering_id
	);

	$user = new WP_User_Query($args);

	$user_id = $user->results[0]->ID; 

	return $user_id;
}

// Register REST API endpoints
class Gamification_Invoice_Endpoint {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public static function register_endpoints() {
		// endpoints will be registered here
		register_rest_route( 'gamification/v1', '/invoice/users/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( 'Gamification_Invoice_Endpoint', 'get_invoice_by_user' ),
			'args'	=> array(
				 'id' => array(
        		 	'validate_callback' => function($param, $request, $key) {
          				return is_numeric( $param );
        			},
        			'required' => true
      		),),
      		'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			}

		) );

		register_rest_route( 'gamification/v1' , '/invoice/search', array(

				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array('Gamification_Invoice_Endpoint', 'search_invoice'),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				}

			)
		);


		register_rest_route( 'gamification/v1', '/invoice', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( 'Gamification_Invoice_Endpoint', 'create_invoice' ),
			'args'	=> array(
				 	'name' => array(
        		 	'validate_callback' => function($param, $request, $key) {
          				return is_string( $param );
        			},
        			'required' => true
      				),

      				'invoice_number' => array(
        		 	'validate_callback' => function($param, $request, $key) {
          				return is_string( $param );
        			},
        			'required' => true
      				),

      				'salesman_code' => array(
        		 	'validate_callback' => function($param, $request, $key) {
          				return is_string( $param );
        			},
        			'required' => true
      				),
				),
			'permission_callback' => function () {
					return current_user_can( 'manage_options' );
			}

		) );
	}

	/**
	 * Search all the invoices
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Request
	 */

	public static function search_invoice( $resquest ) {

			$params = $resquest->get_json_params();

			/**
			 * The WordPress Query class.
			 * @link http://codex.wordpress.org/Function_Reference/WP_Query
			 *
			 */

			if(isset($params['salesman'])) {

				$args = array(
					'author'      => $params['salesman'],
					'post_type'   => 'invoice',
					'post_status' => isset( $params['status'] ) ? $params['status'] : 'any',
				);

				if(isset( $params['invoice_number'])) {

					
					$args['meta_query'] 	= array(
							array(
								'key'	  => 'invoice_number',
								'value'		  => $params['invoice_number'],
								'compare'	  => 'LIKE'
							)
						);

				} 

				if ( isset( $params['invoice_date']) ) {

				
					$args['meta_query'] 	= array(
							array(
								'key'	  => 'invoice_date',
								'value'		  => $params['invoice_date'],
								'compare'	  => 'LIKE'
							)
						);

				} 

				if( isset( $params['customer_code']) ) {

				
					$args['meta_query'] 	= array(
							array(
								'key'	  => 'customer_code',
								'value'		  => $params['customer_code'],
								'compare'	  => 'LIKE'
							)
					);

				}

				if( isset( $params['date_range'])) {

					$range = array (
						$params['date_range']['start_date'],
						$params['date_range']['end_date'],
					);

					$args['meta_query'] = array(
						array(
							'meta_key' => 'invoice_date',
							'value'	   =>  $range,
							'compare'  => 'BETWEEN',
							'type'	   =>  'date',
						)
					);

				}
			}


		
		$query = new WP_Query( $args );

		if($query->have_posts()){

			$response = array();

			$data = $query->posts;

			foreach ($data as $invoice ) {

				$response_data = array(

					'ID' 				=> $invoice->ID,
					'status' 			=> $invoice->post_status,
					'invoice_number' 	=> get_field('invoice_number',$invoice->ID),
					'invoice_date' 		=> get_field('invoice_date',$invoice->ID),
					'invoice_time' 		=> get_field('invoice_time',$invoice->ID),
					'total_amount'		=> get_field('total_amount',$invoice->ID),
					'modified_date' 	=> get_field('modified_date',$invoice->ID),
					'customer_code' 	=> get_field('customer_code',$invoice->ID),
					'customer_name' 	=> get_field('customer_name',$invoice->ID),
					'salesman_code' 	=> get_field('salesman_code',$invoice->ID),
					'salesman_name' 	=> get_field('salesman_name',$invoice->ID),
					'meterials'		    => get_field('materials',$invoice->ID)
				);
				
				array_push( $response, $response_data );
			}
			
			

		} else {

			$response = array(array(
				'error' => '204',
				'error_description' => 'invoice ne yako'
			));
		}

		return new WP_REST_Response( $response, 200 );

	}

	/**
	 * Get all the invoices
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Request
	 */
	public static function get_invoice_by_user( $request ) {

		$data = get_posts( array(
			'post_type'      => 'invoice',
			'post_status'    => 'any',
			'author'		 =>  $request['id'],
			'posts_per_page' => 20,
		));

		$response = array();

		foreach ($data as $invoice ) {


			$response_data = array(

				'ID' 				=> $invoice->ID,
				'status' 			=> $invoice->post_status,
				'invoice_number' 	=> get_field('invoice_number',$invoice->ID),
				'invoice_date' 		=> get_field('invoice_date',$invoice->ID),
				'invoice_time' 		=> get_field('invoice_time',$invoice->ID),
				'total_amount'		=> get_field('total_amount',$invoice->ID),
				'modified_date' 	=> get_field('modified_date',$invoice->ID),
				'customer_code' 	=> get_field('customer_code',$invoice->ID),
				'customer_name' 	=> get_field('customer_name',$invoice->ID),
				'salesman_code' 	=> get_field('salesman_code',$invoice->ID),
				'salesman_name' 	=> get_field('salesman_name',$invoice->ID),
				'meterials'		    => get_field('materials',$invoice->ID)
			);
			
			array_push( $response, $response_data );
		}
		

		return new WP_REST_Response( $response, 200 );
	}


	/**
	 * Add a new invoice
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Request
	 */
	public static function create_invoice( $request ) {

		$params = $request->get_json_params();

		$user =  get_user_by_engineering_id($params['salesman_code']);

		$post_id = wp_insert_post( array(

			'post_title'    => isset( $params['name']    ) ? $params['name'].'-'.$params['invoice_number']: 'Untitled Invoice',
			'post_content'  => isset( $params['details'] ) ? $params['details'] : '',
			'post_type'     => 'invoice',
			'post_status'   => isset( $params['status'] ) ? $params['status'] : 'draft',
			'post_author'		=> isset( $params['salesman_code'] ) ? $user : 1,

		));

		

		$meta_data = array(

			'invoice_number' 	=> $params['invoice_number'],
			'invoice_date' 		=> $params['invoice_date'],
			'invoice_time' 		=> $params['invoice_time'],
			'total_amount'		=> $params['total_amount'],
			'modified_date' 	=> $params['modified_date'],
			'customer_code' 	=> $params['customer_code'],
			'customer_name' 	=> $params['customer_name'],
			'salesman_code' 	=> $params['salesman_code'],
			'salesman_name' 	=> $params['salesman_name'],
			'meterials'		    => $params['materials']

		);

		foreach ($meta_data as $key => $value) {

			if( $key != 'materials' ) {
			
			update_field( $key, $value, $post_id);

			}

		}

		$materials = $params['materials'];

		$i = 1;

		foreach ($materials as $material ) {

			$i++;
			
			$row = array(

				'material_code'			=> $material["material_code"],
				'material_description' 	=> $material["material_description"],
				'quantity' 				=> $material["quantity"],
				'gross_profit' 			=> $material["gross_profit"]
			);

			update_row('materials', $i, $row , $post_id );
		}


		// @TODO do your magic here
		return new WP_REST_Response( $post_id, 200 );
	}
}
add_action( 'rest_api_init', array( 'Gamification_Invoice_Endpoint', 'register_endpoints' ) );

