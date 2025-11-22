<?php

namespace FluentSecurity\Modules\CustomPaths;

defined('ABSPATH') || exit;

class Module
{
    protected $optionKey = 'fls_custom_paths';

    protected $defaults = [
        'content'  => 'wp-content',
        'includes' => 'wp-includes',
        'uploads'  => 'uploads',
        'comments' => 'wp-comments-post'
    ];

    public function register()
    {
        add_action('init', [$this, 'addRewriteRules'], 20);
        add_action('template_redirect', [$this, 'startBuffering'], 0);
        add_action('admin_menu', [$this, 'addSettingsPage'], 25);
        add_action('admin_post_fls_custom_paths', [$this, 'handleSettingsPost']);
        add_action('fluent_security_save_settings', [$this, 'saveSettings']);
    }

    public function addRewriteRules()
    {
        $paths = $this->getSettings();

        add_rewrite_rule('^' . preg_quote($paths['content'], '/') . '/(.*)$', 'wp-content/$1', 'top');
        add_rewrite_rule('^' . preg_quote($paths['includes'], '/') . '/(.*)$', 'wp-includes/$1', 'top');
        add_rewrite_rule('^' . preg_quote($paths['uploads'], '/') . '/(.*)$', 'wp-content/uploads/$1', 'top');
        add_rewrite_rule('^' . preg_quote($paths['comments'], '/') . '/?$', 'wp-comments-post.php', 'top');
    }

    public function startBuffering()
    {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $paths = $this->getSettings();
        $replacements = $this->getReplacements($paths);

        if (!$replacements) {
            return;
        }

        ob_start(function ($buffer) use ($replacements) {
            return strtr($buffer, $replacements);
        });
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

    protected function getReplacements(array $paths)
    {
        $siteUrl = untrailingslashit(home_url());
        $commentsPath = '/' . ltrim($paths['comments'], '/');

        return [
            '/wp-content/uploads/'        => '/' . trim($paths['uploads'], '/') . '/',
            $siteUrl . '/wp-content/uploads/' => $siteUrl . '/' . trim($paths['uploads'], '/') . '/',
            '/wp-content/'                => '/' . trim($paths['content'], '/') . '/',
            $siteUrl . '/wp-content/'         => $siteUrl . '/' . trim($paths['content'], '/') . '/',
            '/wp-includes/'               => '/' . trim($paths['includes'], '/') . '/',
            $siteUrl . '/wp-includes/'        => $siteUrl . '/' . trim($paths['includes'], '/') . '/',
            '/wp-comments-post.php'       => $commentsPath,
            $siteUrl . '/wp-comments-post.php' => $siteUrl . $commentsPath
        ];
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
