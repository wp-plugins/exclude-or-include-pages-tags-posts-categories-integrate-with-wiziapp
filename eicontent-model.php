<?php if ( ! defined( 'EICONTENT_EXCLUDE_PATH' ) ) exit( "Can not be called directly." );

class EIContent_Model {

	private $_db;
	private $_excluded_ids_array = array();
	private $_tax_items_names = array();

	public function __construct( & $db ) {
		$this->_db = $db;
	}

	public function set_excluded_ids( array $element_name) {
		$this->_excluded_ids_array = $element_name;
	}

	public function get_tax_items( array $items_names, $as_array = TRUE) {
		$this->_tax_items_names = $items_names;

		$taxonomies = array_keys(get_taxonomies());
		$tax_items = array_filter($taxonomies, array( $this, '_filter_tags' ) );
		if ( ! $as_array ) {
			$tax_items = self::_convert_to_string($tax_items);
		}

		return $tax_items;
	}

	public function set_query( array $element_name) {
		if ( $element_name === array( 'link', ) ) {
			return
			"SELECT `link_id` " .
			"FROM `" . $this->_db->links . "` " .
			"WHERE `" . $this->get_element_column() . "` = 0;";
		} else {
			return
			"SELECT `" . $this->_db->terms . "`.`term_id` " .
			"FROM `" . $this->_db->terms . "`, `" . $this->_db->term_taxonomy . "` " .
			"WHERE `" . $this->_db->terms . "`.`term_id` = `" . $this->_db->term_taxonomy . "`.`term_id` " .
			"AND `" . $this->_db->term_taxonomy . "`.`taxonomy` IN (" . self::_convert_to_string($element_name) . ") " .
			"AND `" . $this->_db->terms . "`.`" . $this->get_element_column() . "` = 0;";
		}
	}

	public function get_posts_excluded() {
		$query =
		"SELECT `" . $this->_db->term_relationships. "`.`object_id` " .
		"FROM `" . $this->_db->terms . "`, `" . $this->_db->term_taxonomy . "`, `" . $this->_db->term_relationships . "` " .
		"WHERE `" . $this->_db->terms . "`.`term_id` = `" . $this->_db->term_taxonomy . "`.`term_id` " .
		"AND `" . $this->_db->term_taxonomy . "`.`term_taxonomy_id` = `" . $this->_db->term_relationships . "`.`term_taxonomy_id` " .
		"AND `" . $this->_db->term_taxonomy . "`.`taxonomy` IN (" . $this->get_tax_items( array( 'category', 'tags', ), FALSE ) . ") " .
		"AND `" . $this->_db->terms . "`.`wizi_included_app` = 0;";

		return $this->_db->get_col( $query );
	}

	public function get_all_excluded() {
		$pages_exclude = $this->_db->get_col( "SELECT `ID` FROM `" . $this->_db->posts . "` WHERE `wizi_included_app` = 0 AND `post_type` = 'page';" );

		return array_merge( $pages_exclude + $this->get_posts_excluded() );
	}

	public function is_excluded_exist($object_id) {
		$query =
		"SELECT `" . $this->_db->terms . "`.`term_id` " .
		"FROM `" . $this->_db->terms . "`, `" . $this->_db->term_taxonomy . "`, `" . $this->_db->term_relationships . "` " .
		"WHERE `" . $this->_db->terms . "`.`term_id` = `" . $this->_db->term_taxonomy . "`.`term_id` " .
		"AND `" . $this->_db->term_taxonomy . "`.`term_taxonomy_id` = `" . $this->_db->term_relationships . "`.`term_taxonomy_id` " .
		"AND `" . $this->_db->term_taxonomy . "`.`taxonomy` IN (" . $this->get_tax_items( array( 'category', 'tags', ), FALSE ) . ") " .
		"AND `" . $this->_db->terms . "`.`wizi_included_app` = 0 " .
		"AND `" . $this->_db->term_relationships . "`.`object_id` = " . intval( $object_id );

		return ( bool ) $this->_db->query( $query );
	}

	public function get_posts_count($term_id) {
		$query =
		"SELECT DISTINCT `" . $this->_db->term_relationships . "`.`object_id` ".
		"FROM `" . $this->_db->terms . "`, `" . $this->_db->term_taxonomy . "`, `" . $this->_db->term_relationships . "` ".
		"WHERE `" . $this->_db->terms . "`.`term_id` = `" . $this->_db->term_taxonomy . "`.`term_id` ".
		"AND `" . $this->_db->term_taxonomy . "`.`term_taxonomy_id` = `" . $this->_db->term_relationships . "`.`term_taxonomy_id` ".
		"AND `" . $this->_db->terms . "`.`wizi_included_app` = 0 ".
		"AND `" . $this->_db->term_relationships . "`.`object_id` IN ".
		"( ".
		"SELECT `" . $this->_db->term_relationships . "`.`object_id` ".
		"FROM `" . $this->_db->terms . "`, `" . $this->_db->term_taxonomy . "`, `" . $this->_db->term_relationships . "` ".
		"WHERE `" . $this->_db->terms . "`.`term_id` = `" . $this->_db->term_taxonomy . "`.`term_id` ".
		"AND `" . $this->_db->term_taxonomy . "`.`term_taxonomy_id` = `" . $this->_db->term_relationships . "`.`term_taxonomy_id` ".
		"AND `" . $this->_db->terms . "`.`term_id` = " . intval( $term_id ) .
		")";

		return intval( $this->_db->query( $query ) );
	}

	public function get_element_column() {
		if ( $this->is_wiziapp_request() ) {
			return 'wizi_included_app';
		} else {
			return 'wizi_included_site';
		}
	}

	public function update_element_exclusion($table_name, $id_array) {
		$this->_db->update(
		$this->_db->$table_name,
		array( 'wizi_included_site' => isset( $_POST['wizi_included_site'] ), 'wizi_included_app'  => isset( $_POST['wizi_included_app'] ), ),
		$id_array,
		array( '%d', '%d' ),
		array( '%d' )
		);
	}

	public function exclude_posts($page) {
		return ! in_array( $page->ID, $this->_excluded_ids_array );
	}

	public function exclude_media($media) {
		return ! in_array( $media['content_id'], $this->_excluded_ids_array );
	}

	public function is_wiziapp_request() {
		return class_exists( 'WiziappContentHandler' ) && ( WiziappContentHandler::getInstance()->isInApp() || WiziappContentHandler::getInstance()->isHTML() );
	}

	private function _filter_tags($taxonomy) {
		foreach ( $this->_tax_items_names as $name ) {
			if ( strpos( $taxonomy, substr($name, 0, -1) ) !== FALSE && $taxonomy !== 'link_category' ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	private static function _convert_to_string( array $taxonomy_array) {
		$taxonomy_string = '';

		foreach ($taxonomy_array as $string) {
			$taxonomy_string .= "'".$string."',";
		}

		return substr($taxonomy_string, 0, -1);
	}
}