<?php

/*
 * Plugin Name:       Wordpress Multisite Plugin Usage
 * Plugin URI:        https://github.org/midweste/wp-multisite-plugin-usage
 * Description:       Shows site usage for plugins on WordPress multisite network plugins page.
 * Author:            Midweste
 * Author URI:        https://github.org/midweste/wp-multisite-plugin-usage
 * Update URI:        https://raw.githubusercontent.com/midweste/wp-multisite-plugin-usage/main/wp-multisite-plugin-usage.php
 * License:           GPL-2.0+
 */

class MultisitePluginUsage
{
    protected $usage = [];

    public function __construct()
    {

        add_action('admin_init', function () {
            $this->usage = $this->getPluginSiteUsage();
        }, PHP_INT_MAX);


        add_action('admin_enqueue_scripts', function () {
            wp_register_style('wp-plugins-usage', false); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
            wp_enqueue_style('wp-plugins-usage');
            wp_add_inline_style('wp-plugins-usage', '
                .multisite-plugin-usages-wrapper {
                    margin-top: 8px;
                    background-color: white;
                    padding: 10px;
                    border-left: 4px solid rgb(0, 160, 210);

                    -webkit-box-shadow: 1px 1px 1px 1px rgba(0,0,0,0.1);
                    -moz-box-shadow: 1px 1px 1px 1px rgba(0,0,0,0.1);
                    box-shadow: 1px 1px 1px 1px rgba(0,0,0,0.1);
                }
                .multisite-plugin-usages-wrapper.used {
                    border-left: 4px solid rgb(179, 45, 46);
                }
                .multisite-plugin-usages-wrapper.unused {
                    border-left: 4px solid rgb(0, 160, 210);
                }
                .multisite-plugin-usages-wrapper ul li {
                    margin: 0;
                }
            ');
        }, PHP_INT_MAX);

        add_filter('plugin_row_meta', function ($plugin_meta, $plugin_file, $plugin_data, $status) {
            if ($status === 'mustuse' || $status === 'dropins') {
                return $plugin_meta;
            }

            $usage = $this->getPluginUsageHtml($plugin_file);

            if (!empty($plugin_meta)) {
                $key = array_key_last($plugin_meta);
                $plugin_meta[$key] = $plugin_meta[$key] . $usage;
            } else {
                $plugin_meta['usage'] = $usage;
            }
            return $plugin_meta;
        }, PHP_INT_MAX, 4);
    }

    protected function getPluginUsageHtml(string $plugin_file): string
    {
        $usage = [];
        $used = false;
        if (!empty($this->usage[$plugin_file])) {
            foreach ($this->usage[$plugin_file] as $domain => $site) {
                $usage[] = sprintf('<li><a href="%s://%s/wp-admin/plugins.php" target="_blank">%s</a></li>', is_ssl() ? 'https' : 'http', $domain, $domain);
            }
            $used = true;
        } else {
            $usage[] = '<li>Not activated on any site</li>';
        }

        $plugin_usage_html = implode('', $usage);

        $usage_html = <<<HTML
            <div class="multisite-plugin-usages ">
                Activated on: <ul>{$plugin_usage_html}</ul>
            </div>
        HTML;
        $used_class = $used ? 'used' : 'unused';
        return sprintf('<div class="multisite-plugin-usages-wrapper %s">%s</div>', $used_class, $usage_html);
    }

    public function getPluginSiteUsage(): array
    {
        $usage = [];
        $plugins = get_plugins();
        $sites = get_sites();
        ksort($sites);
        foreach ($plugins as $plugin_file => $plugin) {
            $usage[$plugin_file] = [];
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                // if (is_plugin_active($plugin_file)) {
                //     $usage[$plugin_file][$site->domain] = $site;
                // }
                $plugins_active = get_option('active_plugins');
                if (!empty($plugins_active) && in_array($plugin_file, $plugins_active, true)) {
                    $usage[$plugin_file][$site->domain] = $site;
                }
                restore_current_blog();
            }
        }
        return $usage;
    }
}

call_user_func(function () {
    // dont run on front end or non-multisite
    if (
        !is_admin()
        || !defined('WP_ALLOW_MULTISITE') || WP_ALLOW_MULTISITE !== true
        || !defined('MULTISITE') || MULTISITE !== true
    ) {
        return;
    }
    // return if not on url /wp-admin/network/plugins.php
    if (!isset($_SERVER['REQUEST_URI']) || strpos($_SERVER['REQUEST_URI'], '/wp-admin/network/plugins.php') === false) {
        return;
    }

    // dont run on mustuse or dropins plugins page
    if (isset($_GET['plugin_status']) && ($_GET['plugin_status'] === 'mustuse' || $_GET['plugin_status'] === 'dropins')) {
        return;
    }

    new MultisitePluginUsage();
});
