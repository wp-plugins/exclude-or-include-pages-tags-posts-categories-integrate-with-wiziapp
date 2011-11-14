<?php
/**
* Plugin Name: Exclude or include Pages, Tags, Posts & Categories (integrate with WiziApp)
* Description: This plugin adds a checkbox, “Display on your web site”, for pages, tags & categories. Uncheck it to exclude content from your web site. Use Tags to uncheck Posts too.
* Author: mayerz.
* Version: 1.0.1
*/

class EIContent_Controller {

	/**
	* @var object Refer to global $wpdb
	*/
	private $_db;

	/**
	* @var object EIContent_Model instance
	*/
	private $_model;

	/**
	* @var array
	*/
	private $_checked_array = array(
	'wizi_included_site' => 'checked="checked"',
	'wizi_included_app'  => 'checked="checked"',
	);

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

		register_activation_hook( __FILE__, array( &$this, 'activate') );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate') );

		if ( is_admin() ) {
			add_action( 'admin_init', array( &$this, 'admin_init' ) );
		} else {
			add_action( 'init', array( &$this, 'site_init' ) );
		}
	}

	/**
	* Trigger of the hooks on the Site Front End.
	*
	* @return void
	*/
	public function site_init() {
		add_filter( 'get_pages', array( &$this, 'exclude_pages' ) );
		add_action( 'pre_get_posts', array( &$this, 'exclude_posts' ) );

		add_filter( 'widget_tag_cloud_args', array( &$this, 'exclude_tags' ) );
		add_filter( 'wiziapp_exclude_tags', array( &$this, 'exclude_tags' ) );
		add_filter( 'widget_categories_args', array( &$this, 'exclude_categories' ) );
		add_filter( 'wiziapp_exclude_categories', array( &$this, 'exclude_categories' ) );

		add_filter( 'get_term', array( &$this, 'fix_amount_error' ) );
		add_filter( 'get_terms', array( &$this, 'fix_amount_errors' ) );

		add_filter( 'wiziapp_albums_exclude', array( &$this, 'exclude_albums' ) );
		add_filter( 'wiziapp_audio_request', array( &$this, 'exclude_media' ) );
		add_filter( 'wiziapp_video_request', array( &$this, 'exclude_media' ) );
	}

	/**
	* Trigger of the hooks on the Site Back End.
	*
	* @return void
	*/
	public function admin_init() {
		add_action( 'post_submitbox_start', array( &$this, 'add_page_checkboxes' ) );

		add_action( 'edit_category_form', array( &$this, 'add_category_checkboxes' ) );

		add_action( 'edit_tag_form', array( &$this, 'add_tag_checkboxes' ) );
		add_action( 'add_tag_form_fields', array( &$this, 'add_tag_checkboxes' ) );
		add_action( 'tag_add_form_fields', array( &$this, 'add_tag_checkboxes' ) );

		add_action( 'save_post', array( &$this, 'update_post_exclusion' ) );
		add_action( 'edit_terms', array( &$this, 'update_term_exclusion' ) );
		add_action( 'create_term', array( &$this, 'update_term_exclusion' ) );

		add_filter( 'exclude_wiziapp_push', array( &$this, 'exclude_wiziapp_push' ) );
	}



	/*
	"Activation - Deactivation" Part
	*/

	/**
	* On Activation Event add two Exclude Include Content plugin columns
	* to WP posts and terms tables.
	*
	* @return void
	*/
	public function activate() {
		$tables_array = array( 'posts', 'terms', );
		try {
			foreach ( $tables_array as $table ) {
				// Check, if Exclude Include Content plugin columns not exists already
				$columns_names = $this->_db->get_col( "SHOW COLUMNS FROM `" . $this->_db->prefix . $table . "`", 0 );
				if ( in_array( 'wizi_included_site', $columns_names ) || in_array( 'wizi_included_app', $columns_names ) ) {
					$message = 'wizi_included_site or wizi_included_app columns exist already in ' . $table . ' table.';
					throw new Exception('Activation Unsuccessful.<br />' . $message . '<br />Try again.');
				}

				$sql =
				"ALTER TABLE `" . $this->_db->prefix . $table . "`" .
				"ADD COLUMN `wizi_included_site` TINYINT(1) UNSIGNED DEFAULT '1' NOT NULL COMMENT 'Is Post included to Site', " .
				"ADD COLUMN `wizi_included_app`  TINYINT(1) UNSIGNED DEFAULT '1' NOT NULL COMMENT 'Is Post included to WiziApp';";
				if ( ! $this->_db->query( $sql ) ) {
					$message = 'Creating new columns in ' . $table . ' table problem';
					throw new Exception('Activation Unsuccessful. ' . $message);
				}
			}
		} catch (Exception $e) {
			// If error happened, remove added columns
			$this->deactivate( TRUE );
			echo
			'<script type="text/javascript">alert("' . $e->getMessage() . '")</script>' . PHP_EOL .
			$e->getMessage();
			exit;
		}
	}

	/**
	* On Deactivation Event or unseccessful Activation Event
	* remove two Exclude Include Content plugin columns
	* from WP posts and terms tables.
	*
	* @param bool Optional, default - FALSE. Is the Exclude Include Content plugin not activated yet.
	* @return void
	*/
	public function deactivate($is_not_desactivation) {
		$message = array();
		$is_successful = TRUE;
		foreach ( array( 'posts', 'terms', ) as $table ) {
			$columns_names = $this->_db->get_col( "SHOW COLUMNS FROM `" . $this->_db->prefix . $table . "`", 0 );
			foreach ( array( 'wizi_included_site', 'wizi_included_app', ) as $column ) {
				if ( in_array( $column, $columns_names ) ) {
					// If Exclude Include Content plugin column exist...
					if ( ! $this->_db->query( "ALTER TABLE `" . $this->_db->prefix . $table . "` DROP COLUMN `" . $column . "`;" ) ) {
						$message[] = 'Delete Exclude Include Content plugin ' . $column . ' column from ' . $table . ' table problem.';
						$is_successful = FALSE;
					}
				}
			}
		}

		if ( ! $is_successful && ! $is_not_desactivation ) {
			echo
			'<script type="text/javascript">alert("' . 'Deactivation Unsuccessful.<br />' . implode('<br />', $message) . '")</script>' . PHP_EOL .
			'Deactivation Unsuccessful.<br />' . implode('<br />', $message);
			exit;
		}
	}

	/*
	"Exclude Action for End User" Part
	*/

	/**
	* Exclude Pages from show to End User accordance to
	* unchecked Exclude Include Content plugin checkboxes.
	* By filtering of the Pages objects array.
	*
	* @param array of the Pages objects
	* @return array of the Pages objects filtered
	*/
	public function exclude_pages($pages) {
		$query = "SELECT `ID` FROM `" . $this->_db->prefix . "posts` WHERE `" . $this->_model->get_element_column() . "` = 0 AND `post_type` = 'page';";
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

	public function exclude_tags($args) {
		if ( is_array( $args ) ) {
			$query = $this->_model->set_query( 'post_tag' );
			$args['exclude'] = $this->_db->get_col( $query );
		}

		return $args;
	}

	public function exclude_categories($args) {
		if ( is_array( $args ) ) {
			$query = $this->_model->set_query( 'category' );
			$args['exclude'] = $this->_db->get_col( $query );
		}

		return $args;
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

	public function exclude_wiziapp_push($post) {
		if ( is_object($post) && isset( $post->ID ) && $this->_model->is_excluded_exist( $post->ID ) ) {
			return NULL;
		}

		return $post;
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

	/*
	"Update Element Exclusion in DB" Part
	*/

	public function update_post_exclusion($post_id) {
		if ( ! isset( $_POST['wiziapp_ctrl_present'] ) ) {
			return;
		}

		$this->_model->update_element_exclusion( 'posts', array( 'ID' => $post_id ) );
	}

	public function update_term_exclusion($term_id) {
		if ( ! isset($_POST['wiziapp_ctrl_present'] ) ) {
			return;
		}

		$this->_model->update_element_exclusion( 'terms', array( 'term_id' => $term_id ) );
	}

	/*
	"Show Checkboxes in Admin Panel" Part
	*/

	public function add_page_checkboxes() {
		global $post;

		if ( is_object($post) && property_exists($post, 'post_type') && $post->post_type === 'page' ) {
			$this->_checked_array = array(
			'wizi_included_site' => ( (bool) $post->wizi_included_site ) ? 'checked="checked"' : '',
			'wizi_included_app'  => ( (bool) $post->wizi_included_app ) ? 'checked="checked"' : '',
			);

			EIContent_View::print_checkboxes( $this->_checked_array );
		}
	}

	public function add_category_checkboxes($category) {
		if ( isset( $category->wizi_included_site ) && isset( $category->wizi_included_site ) ) {
			$this->_checked_array = array(
			'wizi_included_site' => ((bool) $category->wizi_included_site ) ? 'checked="checked"' : '',
			'wizi_included_app'  => ((bool) $category->wizi_included_app ) ? 'checked="checked"' : '',
			);
		}

		EIContent_View::print_checkboxes( $this->_checked_array );
	}

	public function add_tag_checkboxes($tag) {
		if ( isset( $tag->term_id ) ) {
			$query = "SELECT `wizi_included_site`, `wizi_included_app` FROM " . $this->_db->prefix . "terms WHERE `term_id` = " . intval( $tag->term_id );
			$wiziapp_values = $this->_db->get_row( $query, ARRAY_A );

			$this->_checked_array = array(
			'wizi_included_site' => ( (bool) $wiziapp_values['wizi_included_site'] ) ? 'checked="checked"' : '',
			'wizi_included_app'  => ( (bool) $wiziapp_values['wizi_included_app'] ) ? 'checked="checked"' : '',
			);
		}

		EIContent_View::print_checkboxes( $this->_checked_array );
	}

}

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	exit( "Can not be called directly." );
}
// Define Exclude Include Content plugin root directory path
if( ! defined( 'EICONTENT_EXCLUDE_PATH' ) ) {
	define( 'EICONTENT_EXCLUDE_PATH', plugin_dir_path( __FILE__ ) );
}
require EICONTENT_EXCLUDE_PATH . '/eicontent-model.php';
require EICONTENT_EXCLUDE_PATH . '/eicontent-view.php';
// Start of the Plugin work
global $eicontent_controller;
$eicontent_controller = new EIContent_Controller();