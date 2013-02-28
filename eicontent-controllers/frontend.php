<?php

class EIContent_Frontend {

	/**
	* @var object Refer to global $wpdb
	*/
	private $_db;

	/**
	* @var object EIContent_Model instance
	*/
	private $_model;

	/**
	* Set _db and _model properties.
	* Activate activation and unactivation hooks.
	* Choose to trigger Site Front End or Back End hooks
	*
	* @return void
	*/
	public function __construct() {
		$this->_db = &$GLOBALS['wpdb'];
		$this->_model = new EIContent_Model($this->_db);
	}

	/**
	* Exclude Pages from show to End User accordance to
	* unchecked Exclude Include Content plugin checkboxes.
	* By filtering of the Pages objects array.
	*
	* @param array of the Pages objects
	* @return array of the Pages objects filtered
	*/
	public function exclude_pages($pages) {
		$query = "SELECT `ID` FROM `" . $this->_db->posts . "` WHERE `" . $this->_model->get_element_column() . "` = 0 AND `post_type` = 'page';";
		$this->_model->set_excluded_ids( $this->_db->get_col( $query ) );

		return array_filter( $pages, array( $this->_model, 'exclude_posts' ) );
	}

	/**
	* Exclude Posts from show to End User accordance to
	* unchecked Exclude Include Content plugin checkboxes.
	* By setting category__not_in and post_tag__not_in elements of the uery_vars array
	* (an array of the query variables and their respective values).
	*
	* @param object of the Pages objects
	* @return object of the Pages objects filtered
	*/
	public function exclude_posts($query_request) {
		foreach ( array( 'category' => 'category', 'post_tag' => 'tag',) as $key => $value ) {
			$query = $this->_model->set_query( $key );
			$query_request->set( $value . '__not_in', $this->_db->get_col( $query ) );
		}

		return $query_request;
	}

	public function exclude_categories($args) {
		return $this->_exclude_elements( $args, 'category' );
	}

	public function exclude_tags($args) {
		return $this->_exclude_elements( $args, 'post_tag' );
	}

	public function exclude_links_categories($args) {
		return $this->_exclude_elements( $args, 'link_category' );
	}

	public function exclude_links($args) {
		return $this->_exclude_elements( $args, 'link' );
	}

	public function exclude_wiziapp_links($links_categories, $taxonomies, $args) {
		if ( $this->_model->is_wiziapp_request() && is_array( $links_categories ) && $taxonomies[0] === 'link_category' ) {
			$links_categories_filtered = array();

			foreach ( $links_categories as $links_category ) {
				if ( ( is_object( $links_category) && isset( $links_category->wizi_included_app ) && $links_category->wizi_included_app === '0' ) ) {
					continue;
				}
				$links_categories_filtered[] = $links_category;
			}

			return $links_categories_filtered;
		}

		return $links_categories;
	}

	public function fix_amount_error($end_result_term) {
		$condition =
		$this->_model->is_wiziapp_request() &&
		is_object($end_result_term) &&
		isset( $end_result_term->term_id )&&
		isset( $end_result_term->taxonomy )&&
		in_array( $end_result_term->taxonomy, array( 'category', 'post_tag', ) );

		if ( $condition	) {
			$count = intval( $end_result_term->count ) - $this->_model->get_posts_count( $end_result_term->term_id );
			$end_result_term->count = sprintf( '%s', ( $count > 0 ) ? $count : 0 );
		}

		return $end_result_term;
	}

	public function fix_amount_errors($end_result_terms) {
		if ( is_array( $end_result_terms ) ) {
			foreach ( $end_result_terms as $object ) {
				$this->fix_amount_error( $object );
			}
		}

		return $end_result_terms;
	}

	public function exclude_albums($albums) {
		$filtered_albums = array();
		$all_excluded = $this->_model->get_all_excluded();
		foreach ( $albums as $key => $value ) {
			if ( in_array( sprintf( '%s', $key ), $all_excluded ) ) {
				continue;
			}
			$filtered_albums[$key] = $value;
		}

		return $filtered_albums;
	}

	public function exclude_media($media) {
		$this->_model->set_excluded_ids( $this->_model->get_all_excluded() );

		return array_filter( $media, array( $this->_model, 'exclude_media' ) );
	}

	private function _exclude_elements($array_arguments, $taxonomy) {
		if ( is_array( $array_arguments ) && in_array( $taxonomy, array( 'category', 'post_tag', 'link_category', 'link', ) ) ) {
			$query = $this->_model->set_query( $taxonomy );

			if ( $taxonomy === 'link_category' ) {
				$array_arguments['exclude_category'] = implode( ',', $this->_db->get_col( $query ) );
			} elseif ( $taxonomy === 'link' ) {
				$array_arguments['exclude'] = implode( ',', $this->_db->get_col( $query ) );
			} else {
				$array_arguments['exclude'] = $this->_db->get_col( $query );
			}
		}

		return $array_arguments;
	}

}