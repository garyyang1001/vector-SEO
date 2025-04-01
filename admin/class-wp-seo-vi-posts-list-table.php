<?php
/**
 * Creates the WP_List_Table for displaying posts in the WP SEO Vector Importer admin page.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Ensure WP_List_Table class is available (redundant check, but safe)
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WP_SEO_VI_Posts_List_Table extends WP_List_Table {

    /**
     * Instance of the database class.
     * @var WP_SEO_VI_Database
     */
    private $db;

    /**
     * Constructor.
     *
     * @param WP_SEO_VI_Database $db_instance Instance of the database handler.
     */
    public function __construct( WP_SEO_VI_Database $db_instance ) {
        $this->db = $db_instance;

        parent::__construct( [
            'singular' => __( 'Post', 'wp-seo-vector-importer' ), // singular name of the listed records
            'plural'   => __( 'Posts', 'wp-seo-vector-importer' ), // plural name of the listed records
            'ajax'     => false, // We'll handle AJAX separately if needed for actions
            'screen'   => get_current_screen() // Screen ID
        ] );
    }

    /**
     * Get a list of columns.
     *
     * @return array
     */
    public function get_columns() {
        $columns = [
            'cb'            => '<input type="checkbox" />', // Checkbox for bulk actions
            'title'         => __( 'Title', 'wp-seo-vector-importer' ),
            'post_id'       => __( 'ID', 'wp-seo-vector-importer' ),
            'status'        => __( 'Status', 'wp-seo-vector-importer' ),
            'date'          => __( 'Date', 'wp-seo-vector-importer' ),
            'vector_status' => __( 'Vector Status', 'wp-seo-vector-importer' ),
            'post_url'      => __( 'URL', 'wp-seo-vector-importer' ),
            'post_categories' => __( 'Categories', 'wp-seo-vector-importer' ),
        ];
        return $columns;
    }

    /**
     * Get a list of sortable columns.
     *
     * @return array
     */
    protected function get_sortable_columns() {
        $sortable_columns = [
            'title'   => [ 'title', false ], // true means it's already sorted
            'post_id' => [ 'ID', false ],
            'date'    => [ 'date', true ], // Default sort is date descending
            'status'  => [ 'status', false ],
        ];
        return $sortable_columns;
    }

    /**
     * Get default column value.
     *
     * @param object $item        A row's data.
     * @param string $column_name The column's name.
     * @return mixed
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'post_id':
            case 'status':
            case 'date':
            case 'post_url':
            case 'post_categories':
                return $item[ $column_name ];
            default:
                return print_r( $item, true ); // Show the whole array for troubleshooting
        }
    }

    /**
     * Render the URL column.
     *
     * @param object $item A row's data.
     * @return string
     */
    protected function column_post_url( $item ) {
        $url = isset($item['post_url']) ? $item['post_url'] : get_permalink($item['post_id']);
        if (!empty($url)) {
            return sprintf('<a href="%s" target="_blank">%s</a>', esc_url($url), esc_url($url));
        }
        return '';
    }

    /**
     * Render the Categories column.
     *
     * @param object $item A row's data.
     * @return string
     */
    protected function column_post_categories( $item ) {
        if (isset($item['post_categories']) && !empty($item['post_categories'])) {
            $categories = $item['post_categories'];
            
            // 如果是JSON格式的分類資訊，嘗試解碼
            if (is_string($categories) && $decoded = json_decode($categories, true)) {
                if (is_array($decoded)) {
                    return implode(', ', $decoded);
                }
            }
            
            // 如果不是JSON或解碼失敗，直接顯示
            return is_string($categories) ? $categories : print_r($categories, true);
        }
        
        // 如果資料中沒有分類資訊，獲取當前分類
        $post_categories = get_the_category($item['post_id']);
        $category_names = array();
        if (!empty($post_categories)) {
            foreach ($post_categories as $category) {
                $category_names[] = $category->name;
            }
            return implode(', ', $category_names);
        }
        
        return '';
    }

    /**
     * Render the checkbox column.
     *
     * @param object $item A row's data.
     * @return string
     */
    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="post_ids[]" value="%s" />',
            $item['post_id']
        );
    }

    /**
     * Render the title column with actions.
     *
     * @param object $item A row's data.
     * @return string
     */
    protected function column_title( $item ) {
        $actions = [
            'edit'         => sprintf( '<a href="%s" target="_blank">%s</a>', get_edit_post_link( $item['post_id'] ), __( 'Edit', 'wp-seo-vector-importer' ) ),
            'view'         => sprintf( '<a href="%s" target="_blank">%s</a>', get_permalink( $item['post_id'] ), __( 'View', 'wp-seo-vector-importer' ) ),
            'update_vector' => sprintf( '<a href="#" class="wp-seo-vi-update-vector" data-post-id="%d">%s</a>', $item['post_id'], __( 'Update Vector', 'wp-seo-vector-importer' ) ),
            'delete_vector' => sprintf( '<a href="#" class="wp-seo-vi-delete-vector" data-post-id="%d" style="color:#a00;">%s</a>', $item['post_id'], __( 'Delete Vector', 'wp-seo-vector-importer' ) ),
        ];

        // Remove view link for non-public post types/statuses
        if (!in_array(get_post_status($item['post_id']), ['publish', 'private']) || !is_post_type_viewable(get_post_type($item['post_id']))) {
            unset($actions['view']);
        }

        return sprintf( '%1$s %2$s',
            sprintf('<a href="%s" class="row-title" target="_blank">%s</a>', get_edit_post_link($item['post_id']), $item['title']),
            $this->row_actions( $actions )
        );
    }


    /**
     * Render the vector status column.
     *
     * @param object $item A row's data.
     * @return string
     */
    protected function column_vector_status( $item ) {
        if ( ! empty( $item['vector_status'] ) ) {
            // Format the date/time nicely
            $timestamp = strtotime( $item['vector_status'] );
            $time_diff = human_time_diff( $timestamp, current_time( 'timestamp', 1 ) );
            $formatted_date = sprintf( '%s (%s ago)', date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ), $time_diff );
            return sprintf( '<span style="color:green;">%s %s</span>', __( 'Indexed:', 'wp-seo-vector-importer' ), $formatted_date );
        } else {
            return sprintf( '<span style="color:orange;">%s</span>', __( 'Not Indexed', 'wp-seo-vector-importer' ) );
        }
    }

    /**
     * Get bulk actions.
     *
     * @return array
     */
    protected function get_bulk_actions() {
        $actions = [
            'bulk_update_vector' => __( 'Import/Update Selected Vectors', 'wp-seo-vector-importer' ),
            'bulk_delete_vector' => __( 'Delete Selected Vectors', 'wp-seo-vector-importer' ),
        ];
        return $actions;
    }

    /**
     * Display extra navigation (filters).
     *
     * @param string $which 'top' or 'bottom'.
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' === $which ) {
            echo '<div class="alignleft actions">';

            // Status Filter
            $current_status = isset( $_REQUEST['post_status'] ) ? sanitize_text_field( $_REQUEST['post_status'] ) : '';
            $stati = get_post_stati( [ 'show_in_admin_status_list' => true ], 'objects' );
            echo '<select name="post_status">';
            echo '<option value="">' . __( 'All Statuses', 'wp-seo-vector-importer' ) . '</option>';
            foreach ( $stati as $status => $status_object ) {
                printf( '<option value="%s"%s>%s</option>',
                    esc_attr( $status ),
                    selected( $current_status, $status, false ),
                    esc_html( $status_object->label )
                );
            }
            echo '</select>';

            // Category Filter
            $current_cat = isset( $_REQUEST['cat'] ) ? intval( $_REQUEST['cat'] ) : 0;
            wp_dropdown_categories( [
                'show_option_all' => __( 'All Categories', 'wp-seo-vector-importer' ),
                'taxonomy'        => 'category',
                'name'            => 'cat',
                'orderby'         => 'name',
                'selected'        => $current_cat,
                'hierarchical'    => true,
                'show_count'      => false,
                'hide_empty'      => true,
            ] );

            // Tag Filter (using dropdown for simplicity, could be text input)
            $current_tag = isset( $_REQUEST['tag_id'] ) ? intval( $_REQUEST['tag_id'] ) : 0;
             wp_dropdown_categories( [
                'show_option_all' => __( 'All Tags', 'wp-seo-vector-importer' ),
                'taxonomy'        => 'post_tag',
                'name'            => 'tag_id',
                'orderby'         => 'name',
                'selected'        => $current_tag,
                'hierarchical'    => false,
                'show_count'      => false,
                'hide_empty'      => true,
            ] );


            // Date Filter (Simple Month Dropdown)
            global $wpdb, $wp_locale;
            $months = $wpdb->get_results( $wpdb->prepare( "
                SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
                FROM $wpdb->posts
                WHERE post_type = %s AND post_status != 'auto-draft'
                ORDER BY post_date DESC
            ", 'post' ) ); // Assuming we only care about 'post' type for now

            $month_count = count( $months );
            $m = isset( $_REQUEST['m'] ) ? (int) $_REQUEST['m'] : 0;

            if ( $month_count && ! ( 1 == $month_count && 0 == $months[0]->year ) ) {
                echo '<select name="m">';
                echo '<option ' . selected( $m, 0, false ) . ' value="0">' . __( 'All dates' ) . '</option>';
                foreach ( $months as $arc_row ) {
                    if ( 0 == $arc_row->year ) {
                        continue;
                    }
                    $month = zeroise( $arc_row->month, 2 );
                    $year  = $arc_row->year;
                    printf( "<option %s value='%s'>%s</option>\n",
                        selected( $m, $year . $month, false ),
                        esc_attr( $year . $month ),
                        /* translators: 1: month name, 2: 4-digit year */
                        sprintf( __( '%1$s %2$d' ), $wp_locale->get_month( $month ), $year )
                    );
                }
                echo '</select>';
            }


            submit_button( __( 'Filter', 'wp-seo-vector-importer' ), 'button', 'filter_action', false, [ 'id' => 'post-query-submit' ] );
            echo '</div>';
        }
    }


    /**
     * Prepare the items for the table to process.
     */
    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = []; // Hidden columns
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        // Process bulk actions (needs nonce verification later)
        $this->process_bulk_action();

        // Pagination parameters
        $per_page     = $this->get_items_per_page( 'posts_per_page', 20 );
        $current_page = $this->get_pagenum();
        $total_items  = $this->get_total_posts_count(); // Get total count based on filters

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ] );

        // Fetch the data
        $this->items = $this->get_posts_data( $per_page, $current_page );

        // Fetch vector statuses for the current page items
        $post_ids = wp_list_pluck( $this->items, 'post_id' );
        if ( ! empty( $post_ids ) ) {
            $statuses = $this->db->get_vector_statuses( $post_ids );
            if ( is_array( $statuses ) ) {
                foreach ( $this->items as &$item ) {
                    $item['vector_status'] = isset( $statuses[ $item['post_id'] ] ) ? $statuses[ $item['post_id'] ] : false;
                }
                unset($item); // Unset reference
            }
        }
    }

    /**
     * Get the total number of posts based on current filters.
     *
     * @return int
     */
    private function get_total_posts_count() {
        $query_args = $this->build_query_args();
        $query_args['posts_per_page'] = -1; // Count all matching posts
        $query_args['fields'] = 'ids'; // Only fetch IDs for counting efficiency
        $query = new WP_Query( $query_args );
        return $query->found_posts;
    }

    /**
     * Build WP_Query arguments based on request parameters (filters, search, sort, pagination).
     *
     * @param int $per_page Number of items per page.
     * @param int $current_page Current page number.
     * @return array
     */
    private function build_query_args( $per_page = 20, $current_page = 1 ) {
         $query_args = [
            'post_type'      => 'post', // Adjust if supporting other post types
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            'orderby'        => 'date', // Default order
            'order'          => 'DESC',
        ];

        // Search
        if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
            $query_args['s'] = sanitize_text_field( $_REQUEST['s'] );
        }

        // Status Filter
        if ( isset( $_REQUEST['post_status'] ) && ! empty( $_REQUEST['post_status'] ) ) {
            $query_args['post_status'] = sanitize_text_field( $_REQUEST['post_status'] );
        } else {
             $query_args['post_status'] = 'any'; // Default to 'any' if not specified
        }


        // Category Filter
        if ( isset( $_REQUEST['cat'] ) && ! empty( $_REQUEST['cat'] ) ) {
            $query_args['cat'] = intval( $_REQUEST['cat'] );
        }

        // Tag Filter
        if ( isset( $_REQUEST['tag_id'] ) && ! empty( $_REQUEST['tag_id'] ) ) {
            $query_args['tag_id'] = intval( $_REQUEST['tag_id'] );
        }

        // Date Filter
        if ( isset( $_REQUEST['m'] ) && ! empty( $_REQUEST['m'] ) ) {
            $query_args['m'] = intval( $_REQUEST['m'] );
        }

        // Sorting
        if ( isset( $_REQUEST['orderby'] ) ) {
            $orderby = sanitize_key( $_REQUEST['orderby'] );
            $allowed_orderby = [ 'title', 'ID', 'date', 'status' ]; // Whitelist allowed orderby values
            if ( in_array( $orderby, $allowed_orderby ) ) {
                 // Map 'status' to 'post_status' for WP_Query
                $query_args['orderby'] = ($orderby === 'status') ? 'post_status' : $orderby;
            }
        }
        if ( isset( $_REQUEST['order'] ) ) {
            $order = strtoupper( sanitize_key( $_REQUEST['order'] ) );
            if ( in_array( $order, [ 'ASC', 'DESC' ] ) ) {
                $query_args['order'] = $order;
            }
        }

        return $query_args;
    }


    /**
     * Retrieve posts data from the database.
     *
     * @param int $per_page     Number of items per page.
     * @param int $current_page Current page number.
     * @return array
     */
    private function get_posts_data( $per_page, $current_page ) {
        $query_args = $this->build_query_args( $per_page, $current_page );
        $query = new WP_Query( $query_args );

        $posts_data = [];
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                global $post;
                // 獲取分類
                $categories = get_the_category($post->ID);
                $category_names = array();
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        $category_names[] = $category->name;
                    }
                }
                
                $posts_data[] = [
                    'post_id'       => $post->ID,
                    'title'         => get_the_title(),
                    'status'        => $post->post_status,
                    'date'          => get_the_date(),
                    'vector_status' => false, // Will be populated later
                    'post_url'      => get_permalink($post->ID),
                    'post_categories' => implode(', ', $category_names)
                ];
            }
            wp_reset_postdata(); // Restore original post data
        }
        return $posts_data;
    }

    /**
     * Handle bulk actions.
     * Needs implementation with nonce verification and calling appropriate methods.
     */
    public function process_bulk_action() {
        $action = $this->current_action();

        // Verify nonce here later

        if ( ! $action ) {
            return;
        }

        $post_ids = isset( $_REQUEST['post_ids'] ) ? array_map( 'intval', (array) $_REQUEST['post_ids'] ) : [];

        if ( empty( $post_ids ) ) {
            // Add admin notice: No items selected
            return;
        }

        switch ( $action ) {
            case 'bulk_update_vector':
                // Call AJAX handler or process directly (needs careful implementation for large batches)
                // error_log("Bulk update action triggered for IDs: " . implode(',', $post_ids));
                // Add admin notice: Update started...
                break;
            case 'bulk_delete_vector':
                 // Call AJAX handler or process directly
                 // error_log("Bulk delete action triggered for IDs: " . implode(',', $post_ids));
                 // foreach ($post_ids as $id) { $this->db->delete_vector($id); }
                 // Add admin notice: Deletion completed...
                break;
            default:
                // Do nothing for unknown actions
                break;
        }

        // Redirect after processing to avoid form resubmission (optional)
        // wp_redirect( remove_query_arg( ['action', 'action2', 'post_ids', '_wpnonce'], wp_get_referer() ) );
        // exit;
    }

    /**
     * Message to be displayed when there are no items.
     */
    public function no_items() {
        _e( 'No posts found matching your criteria.', 'wp-seo-vector-importer' );
    }
}
?>
