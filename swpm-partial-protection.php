<?php
/*
Plugin Name: SWPM Partial Protection
Plugin URI: https://simple-membership-plugin.com/
Description: Simple Membership plugin addon for applying partial or section protection to WordPress post/page content.
Author: wp.insider
Author URI: https://simple-membership-plugin.com/
Version: 1.4
*/

define( 'SWPM_PARTIAL_PROTECT_VERSION', '1.4' );
define('SWPM_PARTIAL_PROTECT_PATH', dirname(__FILE__) . '/');
define('SWPM_PARTIAL_PROTECT_URL', plugins_url('',__FILE__));
require_once ('classes/class.swpm-partial-protection.php');
add_action('plugins_loaded', 'swpm_partial_protection_addon');
function swpm_partial_protection_addon(){
    new SwpmPartialProtection();
}