<?php 



function gamification_api_test( $post_type, $params ) {




		/**
		 * The WordPress Query class.
		 * @link http://codex.wordpress.org/Function_Reference/WP_Query
		 *
		 */
		$args = array(

					'author'      => $params['salesman'],
					'post_type'   => 'invoice',
					'post_status' => isset( $params['status'] ) ? $params['status'] : 'any',
		);
	
	$query = new WP_Query( $args );

	if($query->have_posts()){

		while($query->have_posts()){

			$query->the_post();

			$post_data = get_post(get_the_ID());

			echo '<pre>';
			//the_title();
			$data = get_fields(get_the_ID());
			var_dump($data);
			var_dump($post_data);
			echo '</pre>';
		}
	}
	



}