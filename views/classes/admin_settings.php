<?php

final class CLEVERADS {

    public function __construct() {
        add_action('admin_menu', array($this, 'google_add_admin_menu'), 9999);
        add_action('bigcommerce_settings_tabs_array', array($this, 'bigcommerce_settings_tabs_array'), 9999);
        add_action('bigcommerce_settings_tabs_cleverads', array($this, 'print_plugin_options'), 9999);
        add_action('admin_enqueue_scripts', array($this,'wpdocs_selectively_enqueue_admin_script'));
    }

    public function init() {
        if (!class_exists('BigCommerce\Plugin')) {
            return;
        }
    }

    public function google_add_admin_menu() { 

        add_menu_page( 'Clever Ads on Google', 'Clever Ads on Google', 'manage_options', 'clever_ads_on_google_for_bigcommerce', array($this, 'print_plugin_options') );

    }

    function wpdocs_selectively_enqueue_admin_script($hook) {
        if ( 'toplevel_page_clever_ads_on_google_for_bigcommerce' != $hook ) {
        return;
    }
        wp_enqueue_style( 'my_custom_styles', plugin_dir_url( __FILE__ ) . 'styles.css', array(), '1.0' );
        wp_enqueue_style( 'my_custom_font', plugin_dir_url( __FILE__ ) . 'font.css', array(), '1.0' );
        wp_enqueue_style( 'bootstrap_css', plugin_dir_url( __FILE__ ) . 'bootstrap.min.css', array(), '1.0' );
        wp_enqueue_script( 'bootrstrap_js', plugin_dir_url( __FILE__ ) . 'bootstrap.min.js', array(), '1.0' );
    }


    public function bigcommerce_settings_tabs_array($tabs) {
        $tabs['cleverads'] = __('WC Clever Google Ads', 'bigcommerce-currency');
        return $tabs;
    }

    public function render_html($pagepath, $data = array()) {
        @extract($data);
        ob_start();
        include($pagepath);
        return ob_get_clean();
    }

    public function print_plugin_options() {
        echo $this->render_html(CLEVERADS_PATH . 'views/plugin_options.tpl', CLEVERADS_STARTER::generateHmac() );
    }


}
