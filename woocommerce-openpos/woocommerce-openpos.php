<?php
/*
Plugin Name: Woocommerce OpenPos
Plugin URI: http://wpos.app
Description: Quick POS system for woocommerce.
Author: anhvnit@gmail.com
Author URI: http://openswatch.com/
Version: 6.0.6.2
WC requires at least: 3.0
WC tested up to: 7.1
Text Domain: openpos
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

define('OPENPOS_DIR',plugin_dir_path(__FILE__));
define('OPENPOS_URL',plugins_url('woocommerce-openpos'));

global $OPENPOS_SETTING;
global $OPENPOS_CORE;

if(!function_exists('is_plugin_active'))
{
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

require(OPENPOS_DIR.'vendor/autoload.php');


require_once( OPENPOS_DIR.'includes/admin/Settings.php' );
require_once( OPENPOS_DIR.'lib/abtract-op-app.php' );
require_once( OPENPOS_DIR.'lib/op-payment.php' );

require_once( OPENPOS_DIR.'lib/class-op-woo.php' );
require_once( OPENPOS_DIR.'lib/class-op-woo-cart.php' );
require_once( OPENPOS_DIR.'lib/class-op-woo-order.php' );
require_once( OPENPOS_DIR.'lib/class-op-session.php' );
require_once( OPENPOS_DIR.'lib/class-op-receipt.php' );
require_once( OPENPOS_DIR.'lib/class-op-register.php' );
require_once( OPENPOS_DIR.'lib/class-op-table.php' );
require_once( OPENPOS_DIR.'lib/class-op-warehouse.php' );
require_once( OPENPOS_DIR.'lib/class-op-transaction.php' );
require_once( OPENPOS_DIR.'lib/class-op-stock.php' );
require_once( OPENPOS_DIR.'lib/class-op-exchange.php' ); 
require_once( OPENPOS_DIR.'lib/class-op-report.php' ); 
require_once( OPENPOS_DIR.'lib/class-op-help.php' ); 
require_once( OPENPOS_DIR.'lib/class-op-addon.php' ); 

require_once( OPENPOS_DIR.'includes/Core.php' );



require_once( OPENPOS_DIR.'includes/admin/Admin.php' );

global $barcode_generator;
global $op_session;
global $op_warehouse;
global $op_register;
global $op_table;
global $op_stock;
global $op_woo;
global $op_transaction;
global $op_woo_cart;
global $op_woo_order;
global $op_exchange;
global $op_report;
global $op_receipt;

global $op_addon;

//check woocommerce active
if(is_plugin_active( 'woocommerce/woocommerce.php' ))
{

    $barcode_generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
    $op_session = new OP_Session();
    $op_woo = new OP_Woo();
    $op_woo->init();
    $op_receipt = new OP_Receipt();
    $op_woo_cart = new OP_Woo_Cart();
    $op_woo_order = new OP_Woo_Order();
    $op_warehouse = new OP_Warehouse();
    $op_register = new OP_Register();
    $op_table = new OP_Table();
    $op_stock = new OP_Stock();
    $op_exchange = new OP_Exchange();
    $op_report = new OP_Report();
    $op_transaction = new OP_Transaction();
    $OPENPOS_SETTING = new Openpos_Settings();
    $OPENPOS_CORE = new Openpos_Core();
    $OPENPOS_CORE->init();

    $tmp_op_admin = new Openpos_Admin();
    $tmp_op_admin->init();

    $op_addon = new OP_Addon();

    if(!class_exists('Openpos_Front'))
    {
        if(!class_exists('WC_Discounts'))
        {
            require( dirname(OPENPOS_DIR).'/woocommerce/includes/class-wc-discounts.php' );
        }
        require( OPENPOS_DIR.'lib/class-op-discounts.php' );

        require_once( OPENPOS_DIR.'includes/front/Front.php' );
    }
    $tmp_op_front = new Openpos_Front();
    $tmp_op_front->initScripts();
    //register action on active plugin
    if(!function_exists('openpos_activate'))
    {
        function openpos_activate() {
            if ( is_plugin_active( plugin_basename( 'openpos/openpos.php' ) ) ) {
                wp_die( __( 'Seem you are using OpenPOS Lite Version - Free. Please delete it before intsall this Paid version.', 'openpos' ) );
            }
            if ( !is_plugin_active( plugin_basename( 'woocommerce/woocommerce.php' ) ) ) {
                wp_die( __( 'Seem you are forgot install woocommerce plugin. Please install woocommerce plugin before install OpenPOS', 'openpos' ) );
            }
            update_option('_openpos_product_version_0',time());
            // Activation code here...
        }
    }
    load_plugin_textdomain( 'openpos', null,  'woocommerce-openpos/languages' );
    register_activation_hook( __FILE__, 'openpos_activate' );

    
    add_action(
        'before_woocommerce_init',
        function() {
            if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'woocommerce-openpos/woocommerce-openpos.php' );
            }
        }
    );
    
    

    require_once( OPENPOS_DIR.'lib/class-op-integration.php' );
}
