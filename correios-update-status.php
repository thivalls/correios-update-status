<?php
/*
Plugin Name: Correios Update Status
Plugin URI: https://github.com/thivalls/correios-update-status
Description: This plugin updates your orders automatically if it has already been delivered by Correios. Also add a field to add the tracking code more easily.
Version: 1.0.1
Author: Thiago Valls
Author URI: http://trinityweb.com.br/
Text Domain: correios-update-status
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'TWCorreiosUpdateStatus' ) ) :

    /**
     * Trinity Plugin WooCommerce Correios Update Status main class.
     */
    class TWCorreiosUpdateStatus
    {

        private $ordersToUpdate = null;
        private $objects = null;
        private $envelopeSoap;
        private $soapClient;

        /**
         * Plugin version.
         *
         * @var string
         */
        const VERSION = '1.0.1';

        /**
         * Instance of this class.
         *
         * @var object
         */
        protected static $instance = null;

        /**
         * Initialize the plugin public actions.
         */
        private function __construct()
        {
            add_action('init', array($this, 'load_plugin_textdomain'), -1);

            // Checks with WooCommerce is installed.
            if (class_exists('WC_Integration')) {

                // Includes of the plugin
                $this->includes();

                // Add custom status for orders
                $this->TWCorreiosRegisterStatus();

                // Includes of the admin area
                if (is_admin()) {
                    $this->admin_includes();
                }

                // Add field to set tracking code admin
                add_filter( 'woocommerce_admin_order_actions', array( $this, 'TwCorreiosShippingTracking' ), 10, 1 );
                add_action( 'woocommerce_admin_order_actions_end', array( $this, 'TwCorreiosShippingTrackingField' ), 10, 0 );

                // AJAX UPDATE STATUS PROCESS AND UPDATE TRACKING CODE
                add_action('wp_ajax_TwCorreiosUpdateStatusAjax', array($this, 'TwCorreiosUpdateStatusRun'));
                add_action('wp_ajax_nopriv_TwCorreiosUpdateStatusAjax', array($this, 'TwCorreiosUpdateStatusRun'));
                add_action( 'wp_ajax_TwAddCorreiosTracking', array( $this, 'TwAddCorreiosTracking' ) );
                add_action( 'wp_ajax_nopriv_TwAddCorreiosTracking', array( $this, 'TwAddCorreiosTracking' ) );

                // Adding CSS and JS files
                function TWCorreiosScripts()
                {
                    wp_enqueue_script( 'correios-update-status-js', plugins_url( '/', __FILE__ ) . '/assets/js/script.js', array(), 1.0, true );
                    wp_enqueue_style( 'correios-update-status-css', plugins_url( '/', __FILE__ ) . '/assets/css/style.css', array(), 1.0, "all" );
                }
                add_action('admin_enqueue_scripts', 'TWCorreiosScripts');

            } else {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            }
        }

        /**
         * Return an instance of this class.
         *
         * @return object A single instance of this class.
         */
        public static function get_instance()
        {
            // If the single instance hasn't been set, set it now.
            if (null === self::$instance) {
                self::$instance = new self;
            }

            return self::$instance;
        }

        /**
         * Load the plugin text domain for translation.
         */
        public function load_plugin_textdomain()
        {
            load_plugin_textdomain('correios-update-status', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }


        public function includes()
        {
            include_once dirname(__FILE__) . '/includes/SoapConfig.php';
            include_once dirname(__FILE__) . '/includes/CorreiosSoap.php';
        }

        /**
         * Admin includes.
         */
        private function admin_includes()
        {
            include_once dirname(__FILE__) . '/includes/admin/admin-page.php';
        }


        /**
         * WooCommerce fallback notice.
         */
        public function woocommerce_missing_notice() {
            include_once dirname( __FILE__ ) . '/includes/admin/views/html-admin-missing-dependencies.php';
        }

        /**
         * Get main file.
         *
         * @return string
         */
        public static function get_main_file() {
            return __FILE__;
        }

        /**
         * Get plugin path.
         *
         * @return string
         */
        public static function get_plugin_path() {
            return plugin_dir_path( __FILE__ );
        }

        public function TwGetTrakingCodeArray()
        {
            $args = array('status' => 'wc-processing','type' => 'shop_order','return' => 'id', 'limit' => -1 );
            $orders = wc_get_orders($args);

            if($orders):
                foreach ($orders as $order):
                    if(get_post_meta($order,'_correios_tracking_code')):
                        $trackingCode = get_post_meta($order,'_correios_tracking_code',true);
                        $this->objects[] = $trackingCode;
                        $this->ordersToUpdate[][$order] = $trackingCode;
                    endif;
                endforeach;
            else:
                $this->objects = null;
                $this->ordersToUpdate = null;
            endif;
        }

        public function TwCorreiosUpdateStatusRun()
        {
            $return = [];
            $this->TwGetTrakingCodeArray();

            if(!isset($soapClient)):
                $soapClient = new SoapConfig('http://webservice.correios.com.br/service/rastro/Rastro.wsdl');
            endif;

            if($this->objects && count($this->objects) > 1){
                $getSoapResult = new CorreiosSoap($soapClient,array_filter($this->objects),'buscaEventosLista');
                $itensUpdate = $getSoapResult->getResult('buscaEventosLista');
            }elseif($this->objects && count($this->objects) == 1){
                $getSoapResult = new CorreiosSoap($soapClient,$this->objects);
                $itensUpdate = $getSoapResult->getResult();
            }

            $loop = (count($this->objects) > 0 ? count($this->objects) : 0 );
            $arrayOrderStatusCompleted = [];
            $arrayOrderStatusFailed = [];

            for($i=0;$i<$loop;$i++):
                if(array_keys($itensUpdate[$i]) == array_values($this->ordersToUpdate[$i])):
                    $desc = array_values($itensUpdate[$i]);
                    $orderUpdated = array_keys($this->ordersToUpdate[$i]);
                    switch ($desc[0]):
                        case "Objeto entregue ao destinatário":
                            $arrayOrderStatusCompleted[] = $orderUpdated[0];
                            break;
                        case "Objeto devolvido ao remetente":
                            $arrayOrderStatusFailed[] = $orderUpdated[0];
                            break;
                        case "Tentativa de suspensão da entrega":
                            $arrayOrderStatusFailed[] = $orderUpdated[0];
                            break;
                        case "Objeto roubado":
                            $arrayOrderStatusFailed[] = $orderUpdated[0];
                            break;
                        default:
                            break;
                    endswitch;
                endif;
            endfor;

            if($arrayOrderStatusCompleted){
                foreach ($arrayOrderStatusCompleted as $itemID):
                    $action = wc_get_order($itemID);
                    $action->update_status('completed',__('Order has been delivered.','correios-update-status'));
                endforeach;
            }

            if($arrayOrderStatusFailed){
                foreach ($arrayOrderStatusFailed as $itemID):
                    $action = wc_get_order($itemID);
                    $action->update_status('correios-failed',__('Delivery failed.','correios-update-status'));
                endforeach;
            }

            $return['ordersCompleted'] = $arrayOrderStatusCompleted;
            $return['ordersFailed'] = $arrayOrderStatusFailed;

            wp_send_json_success( $return );
        }

        public function TWCorreiosRegisterStatus()
        {
            // Register New Order Statuses
            function TW_wc_register_post_statuses() {
                register_post_status( 'wc-correios-failed', array(
                    'label'                     => _x( 'Delivery Failed', 'Order status', 'correios-update-status' ),
                    'public'                    => false,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop( 'Delivery Failed <span class="count">(%s)</span>', 'Delivery Failed <span class="count">(%s)</span>', 'correios-update-status' ),
                ) );
            }
            add_filter( 'init', 'TW_wc_register_post_statuses' );

            // Add New Order Status to WooCommerce
            function TwAddOrderStatuses( $order_statuses ) {
                $order_statuses['wc-correios-failed'] = _x( 'Delivery Failed', 'WooCommerce Order status', 'correios-update-status' );
                return $order_statuses;
            }
            add_filter( 'wc_order_statuses', 'TwAddOrderStatuses' );
        }

        public function TwCorreiosShippingTracking( $actions )
        {
            $actions['wc-correios-tracking'] = array(
                'url'       => admin_url( 'post.php?post=' . get_the_ID() . '&action=edit' ),
                'name'      => __( 'Add Tracking Code', 'correios-update-status' ),
                'action'    => 'wc-correios-tracking',
            );

            return $actions;
        }

        public function TwCorreiosShippingTrackingField()
        {
            $tracking_code = get_post_meta( get_the_ID(), '_correios_tracking_code', true );

            echo '<input type="text" class="wc-correios-tracking-field" name="wc-correios-tracking-field" data-order-id="' . get_the_ID() . '" id="wc-correios-tracking-field" placeholder="Rastreio" value="' . $tracking_code .'" />';
        }

        public function TwAddCorreiosTracking()
        {

            $order_id = $_POST['order_id'];
            $tracking_code = $_POST['tracking'];

            if ( wc_correios_update_tracking_code( $order_id, $tracking_code ) ) {

                $return = array(
                    'message' => 'ok',
                );

                wp_send_json_success( $return );
            } else {

                $return = array(
                    'message' => 'error',
                );

                wp_send_json_error( $return );
            }

            die();

        }

    }
    add_action( 'plugins_loaded', array( 'TWCorreiosUpdateStatus', 'get_instance' ) );

endif;
