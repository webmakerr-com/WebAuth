<?php

namespace FluentSecurity\Modules\CustomPaths;

defined('ABSPATH') || exit;

require_once __DIR__ . '/PathMasker.php';

class Module
{
    protected $optionKey = 'fls_custom_paths';

    protected $defaults = [
        'content_mask'  => 'wp-content',
        'includes_mask' => 'wp-includes',
        'uploads_mask'  => 'uploads',
        'comments_mask' => 'wp-comments-post.php',
    ];

    public function register()
    {
        $settings = $this->getSettings();
        (new PathMasker($settings))->register();

        add_action('admin_menu', [$this, 'addSettingsPage'], 25);
        add_action('admin_post_fls_custom_paths', [$this, 'handleSettingsPost']);
        add_action('fluent_security_save_settings', [$this, 'saveSettings']);
    }

    public function addSettingsPage()
    {
        $permission = $this->getPermission();

        add_submenu_page(
            'fluent-auth',
            __('Custom Path Masking', 'fluent-security'),
            __('Custom Path Masking', 'fluent-security'),
            $permission,
            'fluent-auth-custom-paths',
            [$this, 'renderSettingsPage']
        );
    }

    public function renderSettingsPage()
    {
        $settings = $this->getSettings();

        include FLUENT_AUTH_PLUGIN_PATH . 'modules/custom-paths/settings.blade.php';
    }

    public function handleSettingsPost()
    {
        if (!current_user_can($this->getPermission())) {
            wp_die(esc_html__('You do not have permission to manage these settings.', 'fluent-security'));
        }

        check_admin_referer('fls_custom_paths_save', 'fls_custom_paths_nonce');

        $data = isset($_POST['fls_custom_paths']) ? (array) wp_unslash($_POST['fls_custom_paths']) : [];

        do_action('fluent_security_save_settings', $data);

        wp_safe_redirect(add_query_arg(
            'fls_custom_paths_saved',
            '1',
            wp_get_referer() ?: admin_url('admin.php?page=fluent-auth-custom-paths')
        ));
        exit;
    }

    public function saveSettings($settings)
    {
        $sanitized = $this->sanitizePaths((array) $settings);

        update_option($this->optionKey, $sanitized, false);
        flush_rewrite_rules(false);
    }

    protected function getSettings()
    {
        $stored = get_option($this->optionKey, []);
        $settings = wp_parse_args((array) $stored, $this->defaults);

        return $this->sanitizePaths($settings);
    }

    protected function sanitizePaths(array $paths)
    {
        foreach ($this->defaults as $key => $default) {
            if (empty($paths[$key])) {
                $paths[$key] = $default;
                continue;
            }

            $value = trim(wp_unslash($paths[$key]));
            $value = trim($value, "/ ");
            $value = preg_replace('/[^a-zA-Z0-9-_\.]/', '', $value);

            if (!$value) {
                $value = $default;
            }

            $paths[$key] = $value;
        }

        return $paths;
    }

    protected function getPermission()
    {
        if (class_exists('FluentAuth\\App\\Helpers\\Helper')) {
            $permission = \FluentAuth\App\Helpers\Helper::getAppPermission();
            if ($permission) {
                return $permission;
            }
        }

        return 'manage_options';
    }
}

