<?php
/*
Plugin Name: My Customer List
Description: This is my Customer list plugin.
*/

class Create_Customer_Table {  

function customer_table()
{      
  global $wpdb; 
  $db_table_name = $wpdb->prefix . 'customers'; 
  $charset_collate = $wpdb->get_charset_collate();

if($wpdb->get_var( "show tables like '$db_table_name'" ) != $db_table_name ) 
 {
       $sql = "CREATE TABLE $db_table_name (
                id int(11) NOT NULL auto_increment,
                customer_name varchar (255) NOT NULL,
                customer_address text NOT NULL,
                customer_city varchar (255) NOT NULL, 
                UNIQUE KEY id (id)
        ) $charset_collate;";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );
   add_option( 'test_db_version', $test_db_version );
 }
} 

}

$obj=new Create_Customer_Table();
register_activation_hook( __FILE__, array($obj,'customer_table') );

class Remove_Customer_Table {  

function customer_remove_table()
{      
  global $wpdb; 
     $table_name = $wpdb->prefix . 'customers'; 
     $sql = "DROP TABLE IF EXISTS $table_name";
     $wpdb->query($sql);
     delete_option("my_plugin_db_version");
} 

}

$obj=new Remove_Customer_Table();
register_deactivation_hook( __FILE__, array($obj,'customer_remove_table') );


if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Customers_List extends WP_List_Table {

	/** Class constructor */
	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Customer', 'sp' ),
			'plural'   => __( 'Customers', 'sp' ), 
			'ajax'     => false 
		] );

	}


	/**
	 * Retrieve customers data from the database
	 */
	public static function get_customers( $per_page = 5, $page_number = 1 ) {

		global $wpdb;

		$sql = "SELECT * FROM {$wpdb->prefix}customers";

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}


	/**
	 * Delete a customer record.
	 */
	public static function delete_customer( $id ) {
		global $wpdb;

		$wpdb->delete(
			"{$wpdb->prefix}customers",
			[ 'ID' => $id ],
			[ '%d' ]
		);
	}


	/**
	 * Returns the count of records in the database.
	 */
	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}customers";

		return $wpdb->get_var( $sql );
	}


	
    public function no_items() {
		_e( 'No customers avaliable.', 'sp' );
	}


	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'customer_name':
			case 'customer_address':
			case 'customer_city':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['ID']
		);
	}


	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_name( $item ) {

		$delete_nonce = wp_create_nonce( 'cs_delete_customer' );

		$title = '<strong>' . $item['name'] . '</strong>';

		$actions = [
			'delete' => sprintf( '<a href="?page=%s&action=%s&customer=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['ID'] ), $delete_nonce )
		];

		return $title . $this->row_actions( $actions );
	}


	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = [
			'cb'      => '<input type="checkbox" />',
			'customer_name'    => __( 'Name', 'sp' ),
			'customer_address' => __( 'Address', 'sp' ),
			'customer_city'    => __( 'City', 'sp' )
		];

		return $columns;
	}


	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'customer_name' => array( 'customer_name', true ),
			'customer_city' => array( 'customer_city', false )
		);

		return $sortable_columns;
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => 'Delete'
		];

		return $actions;
	}


	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'customers_per_page', 5 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page 
		] );

		$this->items = self::get_customers( $per_page, $current_page );
	}

	public function process_bulk_action() {

		
		if ( 'delete' === $this->current_action() ) {

			// verify the nonce.
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );

			if ( ! wp_verify_nonce( $nonce, 'cs_delete_customer' ) ) {
				die( 'Something went wrong' );
			}
			else {
				self::delete_customer( absint( $_GET['customer'] ) );

		               wp_redirect( esc_url_raw(add_query_arg()) );
				exit;
			}

		}

		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
		     || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
		) {

			$delete_ids = esc_sql( $_POST['bulk-delete'] );

			foreach ( $delete_ids as $id ) {
				self::delete_customer( $id );

			}

			 wp_redirect( esc_url_raw(add_query_arg()) );
			exit;
		}
	}

}


class Customer_Plugin {

	static $instance;
    
    public $customers_obj;

	public function __construct() {
		add_filter( 'set_screen_option', [ __CLASS__, 'set_screen' ], 10, 3 );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );
	}


	public static function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function plugin_menu() {

		$hook = add_menu_page(
			'CUSTOMER LIST',
			'Customers List',
			'manage_options',
			'wp_list_table_class',
			[ $this, 'plugin_settings_page' ]
		);

		
		add_submenu_page(
	        'wp_list_table_class',
	        'Add Customer',
	        'Add Customer', 
	        'edit_themes', 
	        'add_customer',
	         [ $this, 'add_customer_form' ]
	    );

		add_action( "load-$hook", [ $this, 'screen_option' ] );

	}


	/**
	 * Plugin create customer page
	 */

    public function add_customer_form() { ?>
    	<h2>Add Customer</h2>
         	<form action="" id="customer" method="post">
     <table>
        <tr>
            <td><label for="name">Customer Name:</label></td>
            <td><input type="text" name="customer_name" id="customer_name" value=""/></td>
        </tr>
        <tr>
            <td><label for="address">Address:</label></td>
            <td><textarea id="customer_address" name="customer_address" rows="4" cols="50">
            </textarea></td>
        </tr>
        <tr>
            <td><label for="city">City:</label></td>
            <td><input type="text" name="customer_city" id="customer_city" /></td>
        </tr>
       
        <tr>
            <td><input type="submit" name="customer_submit" value="Submit"></td>
        </tr>
    </table>
</form>

    <?php
      if ( isset( $_POST['customer_submit'] ) ){
        
         global $wpdb;
         $tablename = $wpdb->prefix.'customers';

         $insert_customer=$wpdb->insert( $tablename, array(
            'customer_name' => $_POST['customer_name'],
            'customer_address' => $_POST['customer_address'],
            'customer_city' => $_POST['customer_city'], 
              ),
            array( '%s', '%s', '%s' ) 
        );
         if($insert_customer) {
              echo 'Customer Created Succesfully';
         } else {
         	echo 'Something Went Wrong please try again';
         }
    }

     }

     /**
	 * Plugin settings page
	 */

	public function plugin_settings_page() {
		?>
		<div class="wrap">
			<h2>Customers List</h2>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php
								$this->customers_obj->prepare_items();
								$this->customers_obj->display(); ?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
		</div>
	<?php
	}

	public function screen_option() {

		$option = 'per_page';
		$args   = [
			'label'   => 'Customers',
			'default' => 5,
			'option'  => 'customers_per_page'
		];

		add_screen_option( $option, $args );

		$this->customers_obj = new Customers_List();
	}


	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}


add_action( 'plugins_loaded', function () {
	Customer_Plugin::get_instance();
} );