<?php
/*
Plugin Name: WooCommerce TiU Internetkassa Gateway
Plugin URI: https://bartbroere.eu/
Description: Extends WooCommerce with a TiU Internetkassa gateway. TiU Internetkassa handles online payments for Tilburg University. Made for the Tilburg Center for Cognition and Communication.
Version: 1.0
Author: Bart Broere <mail@bartbroere.eu>
Author URI: https://bartbroere.eu/
Copyright: © 2016 Bart Broere.
*/

function woocommerce_tiu_internetkassa_init() {

  if (!class_exists('WC_Payment_Gateway')) return;

  load_plugin_textdomain('tiu-internetkassa', false, dirname(plugin_basename(
      __FILE__)) . '/languages');

  class WC_Gateway_Tiu_Internetkassa extends WC_Payment_Gateway {

    public function __construct() {
      $this->id = 'internetkassa';
      $this->has_fields = false;
      $this->init_form_fields();
      $this->init_settings();
      $this->title = $this->settings['title'];
      $this->description = $this->settings['description'];
      add_action('woocommerce_update_options_payment_gateways_'.$this->id, array(&$this, 'process_admin_options'));
      add_action('woocommerce_email_before_order_table', array(&$this, 'email_instructions'), 10, 2);
    }

    public function init_form_fields() {

      $this->form_fields = array(
        'enabled' => array(
          'title'   => __('Enable/Disable Internetkassa', 'woocommerce'),
          'type'    => 'checkbox',
          'label'   => __('Enable TiU Internetkassa in Checkout', 'woocommerce'),
          'default' => 'yes'), //WooCommerce is stringly typed unfortunately
        'production' => array(
          'title'   => __('Enable production mode', 'woocommerce'),
          'description' => __('Setting this option will enable production mode. All transactions will actually happen. Disable this option for test transactions.'),
          'type'        => 'checkbox',
          'label'       => __('Enable production mode', 'woocommerce'),
          'default'     => 'no'),
        'title' => array(
          'title'       => __('Title', 'woocommerce'),
          'type'        => 'text',
          'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
          'default'     => __('TiU Internetkassa (iDeal & Creditcard)', 'TiU Internetkassa (iDeal & Creditcard)', 'woocommerce'),
          'desc_tip'    => true,),
        'description' => array(
          'title'       => __('Description', 'woocommerce'),
          'type'        => 'textarea',
          'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
          'default'     => __('Using TiU Internetkassa, you can pay with iDeal (for Dutch Banks) or Creditcard.', 'woocommerce'),
          'desc_tip'    => true,),
        'api_endpoint_testing' => array(
          'title'       => __('API endpoint testing', 'woocommerce'),
          'type'        => 'text',
          'default'     => __('https://uvtapp.uvt.nl/payplf_tst/', 'woocommerce'),
          'description' => __('URL of the testing environment endpoint, ends with /. This probably should not be changed, unless Tilburg University changes something (like their primary domain).', 'woocommerce'),),
        'api_secret_testing' => array(
          'title'       => __('API Secret Testing', 'woocommerce'),
          'type'        => 'text',
          'description' => __('API key (also called SHA1 salt in documentation) for the test environment. This should match the SHA1 salt set in the backend of TiU Internetkassa.', 'woocommerce'),),
        'api_endpoint_production' => array(
          'title'       => __('API endpoint production', 'woocommerce'),
          'type'        => 'text',
          'default'     => __('https://uvtapp.uvt.nl/payplf/', 'woocommerce'),
          'description' => __('URL of the production environment endpoint, ends with /. This probably should not be changed, unless Tilburg University changes something (like their primary domain).', 'woocommerce'),),
        'api_secret_production' => array(
          'title'       => __('API Secret Production', 'woocommerce'),
          'type'        => 'text',
          'description' => __('API key (also called SHA1 salt in documentation) for the production environment. This should match the SHA1 salt set in the backend of TiU Internetkassa.', 'woocommerce'),),
        'application_name' => array(
          'title'       => __('Application name', 'woocommerce'),
          'type'        => 'text',
          'description' => __('Application name assigned by the administrator of TiU Internetkassa. This should match the name that can be seen in the backend of TiU Internetkassa.', 'woocommerce'),),
        'payment_prefix' => array(
          'title'       => __('Payment prefix', 'woocommerce'),
          'type'        => 'text',
          'description' => __('Payment prefix assigned by the administrator of TiU Internetkassa. All transaction numbers will be this prefix with four numbers appended. This should match the payment prefix number that can be seen in the backend of TiU Internetkassa.', 'woocommerce'),),);
    }

    public function admin_options() {
      echo '<h3>' . _e('TiU Internetkassa', 'woothemes') . '</h3>';
      echo '<p>' . _e('Accept iDeal and Creditcard payments with TiU Internetkassa' . '</p>');
      echo '<table class="form-table">';
      $this->generate_settings_html();
      echo '</table>';
    }

    public function email_instructions( $order, $sent_to_admin ) {
      return;
    }

    function payment_fields() {
      if ($this->description) echo wpautop(wptexturize($this->description));
    }

    function thankyou_page() {
      if ($this->description) echo wpautop(wptexturize($this->description));
    }

    function process_payment($order_id) {
      global $woocommerce;
      $order = new WC_Order($order_id);
      $order->update_status('on-hold', __('Waiting for payment confirmation from TiU Internetkassa.', 'woothemes'));
      if ($this->get_option('production') == "no") { $base_url = $this->get_option('api_endpoint_testing'); $shakey = $this->get_option('api_secret_testing'); }
      else { $base_url = $this->get_option('api_endpoint_production'); $shakey = $this->get_option('api_secret_production'); }
      $generatedtransactionid = $this->get_option('payment_prefix').str_pad((string)$order_id, 4, "0", STR_PAD_LEFT);
      $generatedsignature = strtoupper(sha1('AMOUNT='.($order->order_total*100).$shakey."ORDERID=".$generatedtransactionid.$shakey));
      $generatedurl = $base_url."!pay_plf.make_transaction?"."v_app=".$this->get_option('application_name')."&v_lan=en"."&SHASIGN=".$generatedsignature."&orderID=".$generatedtransactionid."&amount=".$order->order_total*100;
      update_post_meta (
        $order_id,
        'internetkassa_transaction_id',
        $generatedtransactionid
      );
      return array(
        'result' => 'success',
        'redirect' => $generatedurl);
      }
    }
  }

function callbacks() {
  $transactionid = $_REQUEST['orderID'];
  if (strpos($_SERVER['REQUEST_URI'], 'callback-success/')) {
    $options = new WC_Gateway_Tiu_Internetkassa();
    $orderid = (int)substr($transactionid, -4);
    $order = new WC_Order($orderid);
    if(checkcallbacksignature()) {
      if ($_REQUEST['STATUS'] == 9) {
        $order->payment_complete();
        $order->update_status('completed', 'Payment received, download administration for more details.');
      }
      $keys = array("amount", "BR", "CN", "currency", "ED", "orderID", "PAYID", "PM", "STATUS");
      foreach ($keys as $key) {
        add_post_meta($orderid, "internetkassa_".$key, $_REQUEST[$key], true);
      }
    }
    else {
      echo("{\"status\": \"error\"}");
    }
  }
  elseif (strpos($_SERVER['REQUEST_URI'], 'callback-fail/')) {
    $args = array(
      'post_type' => 'shop_order',
      'meta_key' => 'internetkassa_transaction_id',
      'meta_value' => $transactionid);
    $query = new WP_Query($args);
    while ($query->have_posts()) : $query->the_post();
      $orderid = $post->ID;
    endwhile;
    $order = new WC_Order($orderid);
    $order->update_status('on-hold', __('TiU Internetkassa reported a problem with the transaction, check administration for more details.', 'woothemes'));
    if (checkcallbacksignature()) {
      $keys = array("amount", "BR", "CN", "currency", "ED", "orderID", "PAYID", "PM", "STATUS");
      foreach ($keys as $key) {
        add_post_meta($orderid, "internetkassa_".$key, $_REQUEST[$key], true);
      }
    }
  }
  elseif (strpos($_SERVER['REQUEST_URI'], 'callback-error/')) {
    //The university will send an e-mail to the developer address (specified in their backend)
  }
}

function checkcallbacksignature() {
  $keys = array("amount", "BR", "CN", "currency", "ED", "orderID", "PAYID", "PM", "STATUS");
  $options = new WC_Gateway_Tiu_Internetkassa();
  if ($options->get_option('production') == "no") { $shakey = $options->get_option('api_secret_testing'); } else { $shakey = $options->get_option('api_secret_production'); }
  foreach ($keys as $key) {
    if (isset($_REQUEST[$key]) && strlen($_REQUEST[$key]) > 0) {
      $hashinput .= strtoupper($key)."=".$_REQUEST[$key].$shakey;
    }
  }
  $generated = strtoupper(sha1($hashinput));
  return ($generated === $_REQUEST['SHASIGN']);
}

function woocommerce_add_tiu_internetkassa_gateway($methods) {
  $methods[] = 'WC_Gateway_Tiu_Internetkassa';
  return $methods;
}

add_filter('woocommerce_payment_gateways', 'woocommerce_add_tiu_internetkassa_gateway');
add_action('template_redirect', 'callbacks');
add_action('plugins_loaded', 'woocommerce_tiu_internetkassa_init', 0);
