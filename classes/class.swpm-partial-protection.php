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
            return $contents;
        }

        $protected_msg = SwpmUtils::_("This content is for members only.");
        return $this->show_restricted_msg($protected_msg, $attrs);
    }

}
