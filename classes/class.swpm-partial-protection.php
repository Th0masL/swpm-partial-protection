<?php

/**
 * SwpmPartialProtection class
 */
class SwpmPartialProtection {

    var $style_injected = false;

    public function __construct() {
        if (class_exists('SimpleWpMembership')) {
            add_shortcode('swpm_protected', array(&$this, 'protect'));
        }
    }

    private function show_restricted_msg($msg, $attrs) {
        if (!$attrs['do_not_show_protected_msg']) {
            if ($attrs['custom_msg'] !== false) {
                $msg = $attrs['custom_msg'];
            } else {
                $msg = SwpmUtils::_($msg);
            }
            $login_link = '';
            if ($attrs['show_login_link']) {
                $login_link = '<br />' . SwpmMiscUtils::get_login_link();
            }
            
            $classes = 'swpm-partial-protection';
            $style = '';
            $icon = '';
            if ($attrs['format_protected_msg'] == 1) {
                $classes .= ' swpm-formatted-msg';//Add the formattd msg class to the box.
                $icon = '<div class="swpm-partial-protection-icon"><span class="dashicons dashicons-info"></span></div>';
                //in order to prevent injecting same style on the page multiple times, let's check if we already have injected it
                if (!$this->style_injected) {
                    wp_enqueue_style('dashicons'); //Dashicons style
                    $style = "\r\n<style>";
                    $style .= '.swpm-formatted-msg{display:inline-block;overflow:hidden;position:relative;width:100%;border:1px solid #DDDDDD;border-left:5px solid #EED202;padding:5px;margin:10px 0;}';
                    $style .= '.swpm-formatted-msg div.swpm-partial-protection-icon{width:auto;height:100%;position:absolute;top:50%;left:5px;margin-top:-20px;}';
                    $style .= '.swpm-formatted-msg span.dashicons{font-size:40px;color:#EED202;height:auto;width:auto;}';
                    $style .= '.swpm-formatted-msg span.swpm-partial-protection-text{float:left;margin-left:45px;padding: 5px}';
                    $style .= "</style>\r\n";

                    $this->style_injected = true;
                }
            }
            
            return '<div class="' . $classes . '">' . $icon . '<span class="swpm-partial-protection-text">' . $msg . $login_link . '</span></div>' . $style;
        }
        return '';
    }

    // Function that extract the elements that match a specific string, from an array
    public function array_search_x( $array, $search ) {
        $result = array();
        foreach( $array as $item ){
            if (strpos($item, $search) !== false) { // if the element match the search, save this element
                array_push($result, $item);
            }
        }
        if (!empty($result)) { return $result; }
        else { return false; }
    }

    public function protect($attrs, $contents, $codes = '') {
        global $post;
        $auth = SwpmAuth::get_instance();
        $contents = do_shortcode($contents);

        $attrs = shortcode_atts(array(
            'member_id' => false,
            'not_for' => false,
            'for' => false,
            'custom_msg' => false,
            'visible_to' => false,
            'do_not_show_expired_msg' => false,
            'do_not_show_protected_msg' => false,
            'format_protected_msg' => false,
            'show_login_link' => false,
                ), $attrs);

        $attrs['do_not_show_protected_msg'] = ($attrs['do_not_show_protected_msg'] == 1 ? true : false);
        $attrs['show_login_link'] = ($attrs['show_login_link'] == 1 ? true : false);

        if (!$auth->is_logged_in()) {
            // Show the content to anyone who is not logged in
            if ($attrs['visible_to'] == "not_logged_in_users_only") {
                return $contents;
            }
        }
        // If the Newsletter plugin is installed, we verify the 'visible_to' parameters that contains 'newsletter_subscriber', if any
        if (class_exists('Newsletter') && !empty($attrs['visible_to']) && strpos($attrs['visible_to'], 'newsletter_subscriber') !== false) {

            // We need at least either the email or the nk GET parameter to be able to test for the Newsletter plugin
            if (isset($_GET['email']) || isset($_GET['nk'])) {

                // Explode the visible_to value in an array, so we can get the name of the Newsletter Filter
                $visible_to_array = explode("-", $attrs['visible_to']);

                foreach ($visible_to_array as $filter_name) {
                    if (strpos($filter_name, 'newsletter_subscriber') !== false) {

                        // Init some vars
                        $mysql_list_when = "";
                        $filter = "";
                        $filter_value = "";
                        $filter_type = "";
                        $list_number = "";

                        // If we want to filter with a List, we extract the list number
                        if (strpos($filter_name, 'newsletter_subscriber_in_list_') !== false) {
                            // Set the type of filter
                            if (strpos($filter_name, 'newsletter_subscriber_not_in_list_') !== false) {
                                $filter_type = "not_in_list";
                                // Extract the number of the Newsletter List we want to filter with
                                $list_number = str_replace("newsletter_subscriber_not_in_list_", "", $filter_name);
                            }
                            else {
                                $filter_type = "in_list";
                                // Extract the number of the Newsletter List we want to filter with
                                $list_number = str_replace("newsletter_subscriber_in_list_", "", $filter_name);
                            }

                            // If we found a valid Newsletter Subscriber List Number we keept it
                            if (!is_numeric($list_number)) {
                                $list_number = "";
                            }
                            else { // If we found a valid list number, reduce by 1 because the arrays starts by 0
                                $list_number = (int)$list_number - 1;
                            }
                        } // And we detect the filter type
                        else if ($filter_name == 'newsletter_subscriber_in_no_list') {
                            $filter_type = "in_no_list";
                        }
                        else if ($filter_name == 'newsletter_subscriber') {
                            $filter_type = "subscriber";
                        }
                        else if ($filter_name == 'not_newsletter_subscriber') {
                            $filter_type = "not_subscriber";
                        }

                        if (isset($_GET['email'])) {
                            $filter = "email";
                            $filter_value = filter_input( INPUT_GET, 'email', FILTER_SANITIZE_EMAIL );
                        }
                        else if (isset($_GET['nk'])) {
                            $filter = "token";
                            $nk = filter_input( INPUT_GET, 'nk', FILTER_SANITIZE_STRING );
                            $array_nk = explode ("-", $nk);
                            $filter_value = $array_nk[1];
                        }

                        // If we found some correct values to search with, we run the MYSQL Query against the Newsletter database
                        if (!empty($filter_type) && !empty($filter) && !empty($filter_value)) {
                            global $wpdb;
                            $results = $wpdb->get_results("SELECT name,email,token,status,CONCAT_WS('-',list_1,list_2,list_3,list_4,list_5,list_6,list_7,list_8,list_9,list_10) as lists FROM {$wpdb->prefix}newsletter WHERE ".$filter." = '".$filter_value."'", OBJECT );

                            unset($array_lists);
                            $array_list = array();
                            $at_least_in_one_list = false;

                            // If we got some results, detect if the user is at least in some lists or not
                            if (!empty($results) && isset($results[0]->lists)) {
                                $in_lists = $results[0]->lists;

                                // Explode the lists into an array, so we can easily search
                                $array_lists = explode("-", $in_lists);

                                // Check the value is not empty, that means that the user is at least in one list
                                if (!empty(str_replace("0", "", implode("", $array_lists)))) {
                                    $at_least_in_one_list = true;
                                }
                            }

                            // We show the content if the result of the mysql query is not null and if 
                            // we want to show to subscribers
                            // or to members that are in a specific list
                            // or to members that are not in a specific list
                            // or to members that are not in any list at all
                            if ($filter_type == "subscriber" || ($filter_type == "in_list" && !empty($array_lists[$list_number])) || ($filter_type == "not_in_list" && empty($array_lists[$list_number])) || ($filter_type == "in_no_list" && $at_least_in_one_list == false)) {
                                if (!empty($results[0]->status)) {
                                    return $contents;
                                }
                            }
                            // Else if we want to show only to Non-Subscriber, we show it if the result of the mysql query is null
                            else if ($filter_type == "not_subscriber") {
                                if (empty($results[0]->status)) {
                                    return $contents;
                                }
                            }
                        }
                    }
                }
            }
            return '';
        }
        if ($auth->is_logged_in()) {
            // Do not show the content to anyone who is logged in
            if ($attrs['visible_to'] == "not_logged_in_users_only") {
                return "";
            }

            // Show content to anyone who is logged in
            if ($attrs['visible_to'] == "logged_in_users_only") {
                return $contents;
            }

            $account_status = $auth->userData->account_state;
            $level = $auth->userData->membership_level; //$auth->get('membership_level');            

            if ($attrs['visible_to'] == 'expired') { //Show content to expired members
                if ($account_status == 'expired') { //member account expired
                    //When "for" parameter is NOT set in shortcode.
                    if (empty($attrs['for'])){
                        //Member account is expired and no "for" parameter value is set for this section. Show the content to any expired members.
                        return $contents;
                    }
                    
                    //When "for" parameter is set in shortcode.
                    if (isset($attrs['for']) && in_array($level, explode('-', $attrs['for']))) {
                        return $contents;
                    } else { 
                        //Member account is expired, but level is not in the specified group. Don't show the content.
                        return '';
                    }
                } else {
                    //Member account not expired
                    return '';
                }
                    
            }

            if ($account_status == 'expired' && $attrs['do_not_show_expired_msg'] != '1') {//Show the renewal message as this account is expired
                return SwpmMiscUtils::get_renewal_link();
            }

            $member_id = $auth->get('member_id');
            if ($attrs['member_id'] && in_array($member_id, explode('-', $attrs['member_id']))) {
                return $contents;
            } else if ($attrs['member_id']) {
                $protected_msg = SwpmUtils::_("You do not have permission to view this content.");
                return $this->show_restricted_msg($protected_msg, $attrs);
            }

            if ($attrs['for'] && in_array($level, explode('-', $attrs['for']))) {
                return $contents;
            } else if ($attrs['for']) {
                $protected_msg = SwpmUtils::_("Your membership level does not have permission to view this content.");
                return $this->show_restricted_msg($protected_msg, $attrs);
            }
            if ($attrs['not_for'] && !in_array($level, explode('-', $attrs['not_for']))) {
                return $contents;
            } else if ($attrs['not_for']) {
                $protected_msg = SwpmUtils::_("Your membership level does not have permission to view this content.");
                return $this->show_restricted_msg($protected_msg, $attrs);
            }

            // If WordPress Affiliates Manager is installed, we verify the 'visible_to' parameters that starts with 'wpam_', if any
            if (class_exists('WPAM_Pages_AffiliatesHome') && !empty($attrs['visible_to']) && strpos($attrs['visible_to'], 'wpam_') !== false) {

                // Get the email of this user
                $member_email = $auth->get('email');

                // Query the DB table 'wpam_affiliates' to get the status of the WPAM account of this user
                global $wpdb;
                $results = $wpdb->get_results( "SELECT status FROM {$wpdb->prefix}wpam_affiliates WHERE email = '".$member_email."'", OBJECT );

                // Save the user's WPAM account status in a variable, and set to 'unregistered' if the user doesn't have an WPAM account yet
                $wpam_account_status = !empty($results[0]->status) ? $results[0]->status : 'unregistered';

                // Append 'wpam_' in front of the account status, so we can match it with the values of the 'visible_to' filter
                $wpam_account_status = "wpam_".$wpam_account_status;

                // If the filter 'visible_to' contains 'wpam_registered' and if the user has an WPAM account, then we show the content (no matter what is the account status)
                if (in_array("wpam_registered", explode('-', $attrs['visible_to'])) && $wpam_account_status != "wpam_unregistered") {
                    return $contents;
                }

                // For any other 'visible_to' values, we show the content only if the WPAM account status match the 'visible_to' filter
                if (in_array($wpam_account_status, explode('-', $attrs['visible_to']))) {
                    return $contents;
                }

                // And if the WPAM account status does not match any of the values in the 'visible_to' filter, then we do not show the content
                return '';
            }
            return $contents;
        }

        $protected_msg = SwpmUtils::_("This content is for members only.");
        return $this->show_restricted_msg($protected_msg, $attrs);
    }

}
