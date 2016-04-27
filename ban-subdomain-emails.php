<?php
/*
Plugin Name: Ban Subdomain Emails
Plugin URI: https://github.com/jamiechong/wp-ban-subdomain-emails
Description: Prevent people from registering with emails that contain a subdomain to help reduce spam.
Author: Jamie Chong
Version: 1.0.0
Author URI: http://jamiechong.ca
License: GPLv3
*/

/*  Copyright 2016 Jamie Chong

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


class BanSubdomainEmails {
    protected $blocked;
    protected $optionKey = 'bse_blocked_list';
    private static $_instance = null;

    private static function instance() {
        if (self::$_instance === null) {
            self::$_instance = new BanSubdomainEmails();
        }
    }

    public static function init() {
        self::instance();
    }

    public function __construct() {
        $this->blocked = $this->get_blocked_list();
        add_action('register_post', array($this, 'on_register'), 9999, 3);    // handle action very late to give other spam blocking plugins a chance to block
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'plugin_info'), 10, 2);
        add_action('wp_ajax_ban_subdomain_emails', array($this, 'report'));
    }

    // TODO don't consider country specific TLDs as subdomains such as .co.fr, .co.uk
    public function on_register($username, $email, $errors) {
        list($name, $domain) = explode('@', $email);
        $count = substr_count($domain, '.');

        if ($count > 1) {
            $this->block_email($email);
            $errors->add('invalid_email', __('Invalid email.'));    
        }
    }

    public function plugin_info($links) {
        $url = wp_nonce_url(admin_url('admin-ajax.php?action=ban_subdomain_emails'), 'ban_subdomain_emails');

        array_unshift($links, '<a href="'.$url.'" target="_blank">Blocked '.count($this->blocked).' emails</a>');
        return $links;
    }

    public function get_blocked_list() {
        $list = get_option($this->optionKey);
        if (!$list) {
            $list = array();
        }
        return $list;
    }

    public function block_email($email) {
        $list = $this->get_blocked_list();
        if (!in_array($email, $list)) {
            $list[] = $email;    
        }
        update_option($this->optionKey, $list, false);
    }

    public function report() {
        if(empty($_GET['action']) || !is_user_logged_in() || !check_admin_referer($_GET['action'])) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        $list = $this->get_blocked_list();
        echo '<pre>';
        echo implode("\n", $list);
        echo '</pre>';
        exit;
    }

}

add_action('init', 'BanSubdomainEmails::init');