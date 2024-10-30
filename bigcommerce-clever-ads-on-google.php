<?php
/**
 * Plugin Name: Clever Ads for BigCommerce
 * Plugin URI:  cleverecommerce.com
 * Description: Get your ad on Google with a Premium Google Partner. With just 5 simple steps your campaigns will be on the Adwords search network, thanks to the technology of Clever. No work from your side. We will upload all campaigns for you.
 * Version:     1.1
 * Author:      Clever Ecommerce
 * Author URI:  
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: bc-clever-google-ads
 * Domain Path: /languages 
 */

/**
 * Copyright: © 2021 CleverPPC.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}else{
    $all_plugins = apply_filters('active_plugins', get_option('active_plugins'));
    if (!stripos(implode($all_plugins), 'bigcommerce.php')) {
        exit('Ups, you need BigCommerce to run our plugin!');
    }
}


define('CLEVERADS_VERSION', '1.2.3');
define('CLEVERADS_PATH', plugin_dir_path(__FILE__));
define('CLEVERADS_LINK', plugin_dir_url(__FILE__));
define('CLEVERADS_PLUGIN_NAME', plugin_basename(__FILE__));


class CLEVERADS_STARTER {

    private $support_time = 1519919054; // Date of the old < 3.3 version support
    private $default_bc_version = 3.12;
    private $actualized = 0.0;
    private $version_key = "cleverads_bc_version";
    private $_cleverads = null;

    public function __construct() {
        $this->actualized = floatval(get_option($this->version_key, $this->default_bc_version));
    }

    public function update_version() {
        if (defined('BIGCOMMERCE_VERSION') AND ( $this->actualized !== floatval(BIGCOMMERCE_VERSION))) {
            update_option('cleverads_bc_version', BIGCOMMERCE_VERSION);
        }
    }

    public function get_actual_obj() {
        if ($this->_cleverads != null) {
            return $this->_cleverads;
        }
        include_once CLEVERADS_PATH . 'classes/admin_settings.php';      
        $this->_cleverads = new CLEVERADS();
        return $this->_cleverads;
    }

    static function install() {
        global $wpdb;
        $trk = self::genKey(7);
        $domain = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options where option_name='_transient_bigcommerce_store_url'" );
        $auth_token = self::getAuthenticationToken();
        $headers = "Authorization: {$auth_token['result']}";
        $data = self::getInformation();
        self::request('create_shop', 'POST', $data, $headers);
    }

    static function uninstall() {
        $auth_token = self::getAuthenticationToken();
        $headers = "Authorization: {$auth_token['result']}";
        $data = self::getInformation();
        self::request('uninstall_shop', 'POST', $data, $headers);
    }

    private static function request($endPoint, $verb, $data, $headers){
        $url = "https://bigcommerce.cleverecommerce.com/api/bigcommerce/{$endPoint}"; 
        // use key 'http' even if you send the request to https://...
        $args = array(
            'headers'     => array(
                'Authorization' => $headers,
                'Prueba' => 'prueba',
            ),
            'body' => $data,
        ); 

        $result = wp_remote_post($url, $args);
        $body = wp_remote_retrieve_body($result);
        return $body;
    }

    private static function getAuthData(){
        return array('email' => 'bigcommerce@cleverppc.com', 'password' => 'cleverppc');
    }

    private static function getAuthenticationToken(){
        try {
            // Prepare auth data
            $_data = self::getAuthData();
            // Perform request and get raw response object
            $_response = self::request('authenticate', 'POST', $_data, '');
            // Decoding response data
            $_decoded_data = self::decodeResponse($_response);
            // Setting result
            $_result = array('result' => $_decoded_data->auth_token, 'code' => '200');
        } catch (RequestException $e) {
            // Call to Roll-bar, later on
            $_result = array('result' => false, 'code' => $e->getCode(), 'message' => $e->getMessage());
        }
        return $_result;
    }

    protected static function decodeResponse($response){
        return json_decode($response, false);
    }

    private static function genKey($size){
        $key = '';
        /* There are no O/0 in the codes in order to avoid confusion */
        $chars = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';
        for ($i = 1; $i <= $size; ++$i) {
            $key .= $chars[rand(0, 33)];
        }
        return $key;
    }

    public static function generatePayload(){
        global $wpdb;
        $bigcommerce_store_url_0 = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'bigcommerce_store_url' " );
        $bigcommerce_store_url_1 = preg_split("/stores\//", $bigcommerce_store_url_0)[1];
        $bigcommerce_store_url = preg_split("/\//", $bigcommerce_store_url_1)[0];
        
        $account_id = $bigcommerce_store_url;
        $email = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options where option_name='admin_email'" );
        $payload = array('store_hash' => $account_id,
                                'timestamp' => time(),
                                'email' => $email);
        return $payload;
    }

    public static function generateHmac(){
        $payload = self::generatePayload();
        $encoded = json_encode($payload);
        $encoded_payload = base64_encode($encoded);
        $hash_mac = hash_hmac(self::getHashMacAlgorithm(), $encoded, self::getHashSecret());
        $payload_signature = base64_encode($hash_mac);
        $hmac = "{$encoded_payload}.{$payload_signature}";
        return $hmac;
    }

    public static function getHashMacAlgorithm(){
        return 'sha256';
    }

    public static function getHashSecret(){
        return '4n7fdidvdrzvwe5hb0i4blohf4d8crc';
    }

    //cogiendo siempre el primer dato de cada "array" que me devuelven las consultas de la base de datos
    private static function getInformation(){
        global $wpdb;
        $name = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options where option_name='blogname'" );
        $domain = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options where option_name='_transient_bigcommerce_store_url'" );
        $email = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options where option_name='admin_email'" );
        //We can get de currency code from database
        $currency = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options where option_name='bigcommerce_currency_code'" );
        //The rest of info, we have to do a get to api.bigcommerce.com/stores/{$$.env.store_hash}/v2
        $language = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options where option_name='WPLANG'" );
        $bigcommerce_store_url_0 = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'bigcommerce_store_url' " );
        $bigcommerce_store_url_1 = preg_split("/stores\//", $bigcommerce_store_url_0)[1];
        $bigcommerce_store_url = preg_split("/\//", $bigcommerce_store_url_1)[0];
        $bc_access_token = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'bigcommerce_access_token' " );

        $find = "_";
        if($language != null){
            $pos = strpos($language, $find);
            if($pos !== false){
                $language = substr($language, 0, $pos);
            }
        }else{
            $language = "en";
        }

        $_store = array(
            'name' => $name,
            'domain' => $domain,
            'email' => $email,
            'countries' => "",
            'logo_url' => '',
            'platform' => 'bigcommerce',
            'provider' => 'wordpress',
            'currency' => $currency,
            'language' => $language, //cojo el primer language pero si tiene otros plugins instalados puede tener multilanguage
            'client_id' => $bigcommerce_store_url,
            'access_token' => $bc_access_token,
            'shop_country' => ""
        );
        return $_store;
    }

    //cogiendo siempre el primer dato de cada "array" que me devuelven las consultas de la base de datos
}

register_activation_hook( __FILE__, array( 'CLEVERADS_STARTER', 'install' ) );
register_deactivation_hook( __FILE__, array( 'CLEVERADS_STARTER', 'uninstall' ) );
register_uninstall_hook( __FILE__, array( 'CLEVERADS_STARTER', 'uninstall' ) );
$CLEVERADS_STARTER = new CLEVERADS_STARTER();

$CLEVERADS = $CLEVERADS_STARTER->get_actual_obj();
$hmac = $CLEVERADS_STARTER->generateHmac();
define('CLEVERADS_HMAC', $hmac);
$GLOBALS['CLEVERADS'] = $CLEVERADS;
add_action('init', array($CLEVERADS, 'init'), 1);

?>
