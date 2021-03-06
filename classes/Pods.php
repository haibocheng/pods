<?php
class Pods {

    private $api;

    private $data;

    private $results;

    private $row;

    private $deprecated;

    public $display_errors = false;

    public $table;

    public $field_id = 'id';

    public $field_index = 'name';

    public $pod_data;

    public $pod;

    public $pod_id;

    public $fields;

    public $detail_page;

    public $id;

    public $limit = 15;

    public $page_var = 'pg';

    public $page = 1;

    public $pagination = true;

    public $search = true;

    public $search_var = 'search';

    public $search_mode = 'int'; // int | text | text_like

    public $ui;

    /**
     * Constructor - Pods CMS core
     *
     * @param string $pod The pod name
     * @param mixed $id (optional) The ID or slug, to load a single record; Provide array of $params to run 'find' immediately
     *
     * @license http://www.gnu.org/licenses/gpl-2.0.html
     * @since 1.0.0
     */
    public function __construct ( $pod = null, $id = null ) {
        $this->api = pods_api( $pod );
        $this->api->display_errors =& $this->display_errors;

        $this->data = pods_data( $pod, $id );
        PodsData::$display_errors =& $this->display_errors;

        if ( defined( 'PODS_STRICT_MODE' ) && PODS_STRICT_MODE ) {
            $this->page = 1;
            $this->pagination = false;
            $this->search = false;
        }
        else {
            // Get the page variable
            $this->page = pods_var( $this->page_var, 'get' );
            $this->page = ( empty( $this->page ) ? 1 : max( pods_absint( $this->page ), 1 ) );
            if ( defined( 'PODS_GLOBAL_POD_PAGINATION' ) && !PODS_GLOBAL_POD_PAGINATION ) {
                $this->page = 1;
                $this->pagination = false;
            }

            if ( defined( 'PODS_GLOBAL_POD_SEARCH' ) && !PODS_GLOBAL_POD_SEARCH )
                $this->search = false;
            if ( defined( 'PODS_GLOBAL_POD_SEARCH_MODE' ) && in_array( PODS_GLOBAL_POD_SEARCH_MODE, array(
                'int',
                'text',
                'text_like'
            ) )
            )
                $this->search_mode = PODS_GLOBAL_POD_SEARCH_MODE;
        }

        // Sync Settings
        $this->data->page =& $this->page;
        $this->data->pagination =& $this->pagination;
        $this->data->search =& $this->search;
        $this->data->search_mode =& $this->search_mode;

        // Sync Pod Data
        $this->api->pod_data =& $this->data->pod_data;
        $this->pod_data =& $this->api->pod_data;
        $this->api->pod_id =& $this->data->pod_id;
        $this->datatype_id =& $this->api->pod_id;
        $this->pod_id =& $this->datatype_id;
        $this->api->pod =& $this->data->pod;
        $this->datatype =& $this->api->pod;
        $this->pod =& $this->datatype;
        $this->api->fields =& $this->data->fields;
        $this->fields =& $this->api->fields;
        $this->detail_page =& $this->data->detail_page;
        $this->id =& $this->data->id;

        // Sync Table Data
        $this->table =& $this->data->table;
        $this->field_id =& $this->data->field_id;
        $this->field_index =& $this->data->field_index;

        if ( is_array( $id ) || is_object( $id ) )
            $this->find( $id );
    }

    /**
     * Return data array from a find
     *
     * @since 2.0.0
     */
    public function data () {
        $this->do_hook( 'data' );

        if ( empty( $this->results ) )
            return false;

        return (array) $this->results;
    }

    /**
     * Return row array for an item
     *
     * @since 2.0.0
     */
    public function row () {
        $this->do_hook( 'row' );

        if ( empty( $this->row ) )
            return false;

        return (array) $this->row;
    }

    /**
     * Return a field's value(s)
     *
     * @param array $params An associative array of parameters (OR the Field name)
     * @param string $orderby (optional) The orderby string, for PICK fields
     *
     * @since 2.0.0
     */
    public function field ( $params, $orderby = null ) {
        $defaults = array(
            'name' => $params,
            'orderby' => $orderby
        );

        if ( is_array( $params ) || is_object( $params ) )
            $params = (object) array_merge( $defaults, (array) $params );
        else
            $params = (object) $defaults;

        if ( is_array( $params->name ) || strlen( $params->name ) < 1 )
            return null;

        if ( false === $this->row() ) {
            if ( false !== $this->data() )
                $this->fetch();
            else
                return null;
        }

        $value = null;
        if ( isset( $this->fields[ $params->name ] ) && isset( $this->row[ $params->name ] ) )
            $value = $this->row[ $params->name ];
        else {
            // do pick / file handling
        }
        $value = $this->do_hook( 'field', $value, $this->row, $params );
        return $value;
    }

    /**
     * Return the item ID
     *
     * @return int
     * @since 2.0.0
     */
    public function id () {
        return $this->field( $this->field_id );
    }

    /**
     * Return the item name
     *
     * @return string
     * @since 2.0.0
     */
    public function index () {
        return $this->field( $this->field_index );
    }

    /**
     * Search and filter items
     *
     * @param array $params An associative array of parameters
     *
     * @since 2.0.0
     */
    public function find ( $params = null, $limit = 15, $where = null, $sql = null ) {
        $defaults = array(
            'table' => $this->table,
            'select' => 't.*',
            'join' => null,
            'where' => $where,
            'groupby' => null,
            'having' => null,
            'orderby' => "t.`{$this->field_id}` DESC",
            'limit' => (int) $limit,
            'page' => (int) $this->page,
            'search' => (boolean) $this->search,
            'search_query' => pods_var( $this->search_var, 'get', '' ),
            'search_mode' => pods_var( $this->search_var, 'get', '' ),
            'search_across' => true,
            'search_across_picks' => false,
            'fields' => $this->fields,
            'sql' => $sql
        );

        if ( is_array( $params ) )
            $params = (object) array_merge( $defaults, $params );
        else
            $params = (object) $defaults;

        $this->limit = (int) $params->limit;
        $this->page = (int) $params->page;
        $this->search = (boolean) $params->search;

        // also need to do better search/filtering using search_mode and auto join stuff for pick/file fields

        $params = $this->do_hook( 'find', $params );

        $this->data->select( $params );
        $this->results =& $this->data->data;

        return $this;
    }

    /**
     * Fetch a row
     *
     * @since 2.0.0
     */
    public function fetch ( $id = null ) {
        if ( null !== $id ) {
            $id = pods_absint( $id );
            $params = array(
                'table' => $this->table,
                'where' => "`{$this->field_id}` = {$id}",
                'orderby' => "`{$this->field_id}` DESC",
                'page' => 1,
                'limit' => 1,
                'search' => false
            );

            $params = $this->do_hook( 'fetch', $params, $id );

            $this->data->select( $params );
            $this->results =& $this->data->data;
        }
        else
            $this->do_hook( 'fetch', null, $id );

        $this->row =& $this->data->fetch( $id );

        return $this->row;
    }

    /**
     * (Re)set the MySQL result pointer
     *
     * @since 2.0.0
     */
    public function reset ( $row = 0 ) {
        $this->do_hook( 'reset' );
        $row = pods_absint( $row );
        $this->row =& $this->data->fetch( $row );
        return $this->row;
    }

    /**
     * Fetch the total row count returned
     *
     * @return int Number of rows returned by find()
     * @since 2.0.0
     */
    public function total () {
        $this->do_hook( 'total' );
        $this->total =& $this->data->total();
        return $this->total;
    }

    /**
     * Fetch the total row count total
     *
     * @return int Number of rows found by find()
     * @since 2.0.0
     */
    public function total_found () {
        $this->do_hook( 'total_found' );
        $this->total_found =& $this->data->total_found();
        return $this->total_found;
    }

    /**
     * Fetch the zebra switch
     *
     * @return bool Zebra state
     * @since 1.12
     */
    public function zebra () {
        $this->do_hook( 'zebra' );
        return $this->data->zebra();
    }

    /**
     * Add an item
     *
     * @since 2.0.0
     */
    public function add ( $data = null, $value = null, $id = null ) {
        if ( null !== $value )
            $data = array( $data => $value );
        if ( null === $id )
            $id = $this->id;
        $data = (array) $this->do_hook( 'add', $data, $id );
        if ( empty( $data ) )
            return;
        $params = array( 'pod' => $this->pod, 'id' => $id, 'data' => array( $data ) );
        return $this->api->save_pod_item( $params );
    }

    /**
     * Save an item
     *
     * @since 2.0.0
     */
    public function save ( $data = null, $value = null, $id = null ) {
        if ( null !== $value )
            $data = array( $data => $value );
        if ( null === $id )
            $id = $this->id;
        $data = (array) $this->do_hook( 'save', $data, $id );
        if ( empty( $data ) )
            return;
        $params = array( 'pod' => $this->pod, 'id' => $id, 'data' => array( $data ) );
        return $this->api->save_pod_item( $params );
    }

    /**
     * Delete an item
     *
     * @since 2.0.0
     */
    public function delete ( $id = null ) {
        if ( null === $id )
            $id = $this->id;
        $id = (int) $this->do_hook( 'delete', $id );
        if ( empty( $id ) )
            return;
        $params = array( 'pod' => $this->pod, 'id' => $id );
        return $this->api->delete_pod_item( $params );
    }

    /**
     * Duplicate an item
     *
     * @since 2.0.0
     */
    public function duplicate ( $id = null ) {
        if ( null === $id )
            $id = $this->id;
        $id = (int) $this->do_hook( 'duplicate', $id );
        if ( empty( $id ) )
            return;
        $params = array( 'pod' => $this->pod, 'id' => $id );
        return $this->api->duplicate_pod_item( $params );
    }

    /**
     * Export an item's data
     *
     * @since 2.0.0
     */
    public function export ( $fields = null, $id = null ) {
        if ( null === $id )
            $id = $this->id;
        $fields = (array) $this->do_hook( 'export', $fields, $id );
        if ( empty( $id ) )
            return;
        $params = array( 'pod' => $this->pod, 'id' => $id, 'fields' => $fields );
        return $this->api->export_pod_item( $params );
    }

    /**
     * Display the pagination controls
     *
     * @since 2.0.0
     */
    public function pagination ( $params = null ) {
        $defaults = array(
            'type' => 'simple',
            'label' => __( 'Go to page:', 'pods' ),
            'next_label' => __( '&lt; Previous', 'pods' ),
            'prev_label' => __( 'Next &gt;', 'pods' ),
            'first_label' => __( '&laquo; First', 'pods' ),
            'last_label' => __( 'Last &raquo;', 'pods' ),
            'limit' => (int) $this->limit,
            'page' => max( 1, (int) $this->page ),
            'total_found' => $this->total_found()
        );

        if ( empty( $params ) )
            $params = array();
        elseif ( !is_array( $params ) )
            $params = array( 'label' => $params );

        $params = (object) array_merge( $defaults, $params );

        $params->total_pages = ceil( $params->total_found / $params->limit );

        if ( $params->limit < 1 || $params->total_found < 1 || 1 == $params->total_pages )
            return $this->do_hook( 'pagination', '', $params );

        $pagination = 'extended';

        if ( 'basic' == $params->type )
            $pagination = 'basic';

        ob_start();

        pods_view( PODS_DIR . 'ui/front/pagination/' . $pagination . '.php', compact( array_keys( get_defined_vars() ) ) );

        $output = ob_get_clean();

        return $this->do_hook( 'pagination', $output, $params );
    }

    /**
     * Display the list filters
     *
     * @since 2.0.0
     */
    public function filters ( $params = null ) {
        // handle $params deprecated

        ob_start();

        pods_view( PODS_DIR . 'ui/front/filters.php', compact( array_keys( get_defined_vars() ) ) );

        $output = ob_get_clean();

        return $this->do_hook( 'filters', $output, $params );
    }

    /**
     * Run a helper within a Pod Page or WP Template
     *
     * $params['helper'] string Helper name
     * $params['value'] string Value to run Helper on
     * $params['name'] string Field name
     *
     * @param array $params An associative array of parameters
     *
     * @return mixed Anything returned by the helper
     * @since 2.0.0
     *
     * @deprecated deprecated since 2.0.0
     */
    public function helper ( $helper, $value = null, $name = null ) {
        pods_deprecated( "Pods::helper", '2.0.0' );

        $params = array(
            'helper' => $helper,
            'value' => $value,
            'name' => $name
        );
        if ( is_array( $helper ) )
            $params = array_merge( $params, $helper );
        $params = (object) $params;

        if ( empty( $params->helper ) )
            return pods_error( 'Helper name required', $this );

        if ( !isset( $params->value ) )
            $params->value = null;
        if ( !isset( $params->name ) )
            $params->name = null;

        ob_start();

        $this->do_hook( 'pre_pod_helper', $params );
        $this->do_hook( "pre_pod_helper_{$params->helper}", $params );

        $helper = $this->api->load_helper( array( 'name' => $params->helper ) );
        if ( !empty( $helper ) && !empty( $helper[ 'code' ] ) ) {
            if ( !defined( 'PODS_DISABLE_EVAL' ) || PODS_DISABLE_EVAL )
                eval( "?>{$helper['code']}" );
            else
                echo $helper[ 'code' ];
        }
        elseif ( function_exists( "{$params->helper}" ) ) {
            $function_name = (string) $params->helper;
            echo $function_name( $params->value, $params->name, $params, $this );
        }

        $this->do_hook( 'post_pod_helper', $params );
        $this->do_hook( "post_pod_helper_{$params->helper}", $params );

        return $this->do_hook( 'helper', ob_get_clean(), $params );
    }

    /**
     * Display the page template
     *
     * @since 2.0.0
     */
    public function template ( $template, $code = null ) {
        ob_start();

        $this->do_hook( 'pre_template', $template, $code );
        $this->do_hook( "pre_template_{$template}", $template, $code );

        if ( empty( $code ) ) {
            $template = $this->api->load_template( array( 'name' => $template ) );
            if ( !empty( $template ) && !empty( $template[ 'code' ] ) )
                $code = $template[ 'code' ];
            elseif ( function_exists( "{$template}" ) )
                $code = $template( $this );
        }

        $code = $this->do_hook( 'template', $code, $template );
        $code = $this->do_hook( "template_{$template}", $code, $template );

        if ( !empty( $code ) ) {
            // Only detail templates need $this->id
            if ( empty( $this->id ) ) {
                while ($this->fetch()) {
                    echo $this->do_template( $code );
                }
            }
            else
                echo $this->do_template( $code );
        }

        $this->do_hook( 'post_template', $template, $code );
        $this->do_hook( "post_template_{$template}", $template, $code );

        return ob_get_clean();
    }

    /**
     * Parse a template string
     *
     * @param string $code The template string to parse
     *
     * @since 1.8.5
     */
    public function do_template ( $code ) {
        ob_start();
        if ( ( !defined( 'PODS_DISABLE_EVAL' ) || PODS_DISABLE_EVAL ) )
            eval( "?>$code" );
        else
            echo $code;
        $out = ob_get_clean();
        $out = preg_replace_callback( "/({@(.*?)})/m", array( $this, "do_magic_tags" ), $out );
        return $this->do_hook( 'do_template', $out, $code );
    }

    /**
     * Replace magic tags with their values
     *
     * @param string $tag The magic tag to evaluate
     *
     * @since 1.x
     */
    private function do_magic_tags ( $tag ) {
        $tag = trim( $tag, ' {@}' );
        $tag = explode( ',', $tag );
        if ( empty( $tag ) || !isset( $tag[ 0 ] ) || 0 < strlen( trim( $tag[ 0 ] ) ) )
            return;
        foreach ( $tag as $k => $v ) {
            $tag[ $k ] = trim( $v );
        }
        $field_name = $tag[ 0 ];
        if ( 'detail_url' == $field_name )
            $value = get_bloginfo( 'url' ) . '/' . $this->do_template( $this->detail_page );
        elseif ( 'type' == $field_name )
            $value = $this->pod;
        else
            $value = $this->field( $field_name );
        $helper_name = $before = $after = '';
        if ( isset( $tag[ 1 ] ) && !empty( $tag[ 1 ] ) ) {
            $helper_name = $tag[ 1 ];
            $value = $this->helper( $helper_name, $value, $field_name );
        }
        if ( isset( $tag[ 2 ] ) && !empty( $tag[ 2 ] ) )
            $before = $tag[ 2 ];
        if ( isset( $tag[ 3 ] ) && !empty( $tag[ 3 ] ) )
            $after = $tag[ 3 ];

        $value = $this->do_hook( 'do_magic_tags', $value, $field_name, $helper_name, $before, $after );
        if ( null !== $value && false !== $value )
            return $before . $value . $after;
        return;

    }

    /**
     * Build form for handling add / edit
     *
     * @param array $params
     * @param string $label
     * @param string $thank_you
     *
     * @since 2.0.0
     */
    public function form ( $params, $label = null, $thank_you = null ) {
        $defaults = array(
            'fields' => $params,
            'label' => $label,
            'thank_you' => $thank_you
        );

        if ( isset( $params[ 'fields' ] ) )
            $params = array_merge( $defaults, $params );

        $pod =& $this;
        $fields = $params[ 'fields' ];
        $label = $params[ 'label' ];
        $thank_you = $params[ 'thank_you' ];

        ob_start();

        pods_view( PODS_DIR . 'ui/front/form.php', compact( array_keys( get_defined_vars() ) ) );

        $output = ob_get_clean();

        return $this->do_hook( 'form', $output, $fields, $label, $thank_you, $this, $this->id() );
    }

    /**
     * Handle filters / actions for the class
     *
     * @since 2.0.0
     */
    private function do_hook () {
        $args = func_get_args();
        if ( empty( $args ) )
            return false;
        $name = array_shift( $args );
        return pods_do_hook( 'pods', $name, $args, $this );
    }

    /**
     * Handle methods that have been deprecated
     *
     * @since 2.0.0
     */
    public function __call ( $name, $args ) {
        $name = (string) $name;

        if ( !isset( $this->deprecated ) ) {
            require_once( PODS_DIR . 'deprecated/classes/Pods.php' );
            $this->deprecated = new Pods_Deprecated( $this );
        }

        if ( method_exists( $this->deprecated, $name ) ) {
            $arg_count = count( $args );
            if ( 0 == $arg_count )
                $this->deprecated->{$name}();
            elseif ( 1 == $arg_count )
                $this->deprecated->{$name}( $args[ 0 ] );
            elseif ( 2 == $arg_count )
                $this->deprecated->{$name}( $args[ 0 ], $args[ 1 ] );
            elseif ( 3 == $arg_count )
                $this->deprecated->{$name}( $args[ 0 ], $args[ 1 ], $args[ 2 ] );
            else
                $this->deprecated->{$name}( $args[ 0 ], $args[ 1 ], $args[ 2 ], $args[ 3 ] );
        }
        else
            pods_deprecated( "Pods::{$name}", '2.0.0' );
    }
}