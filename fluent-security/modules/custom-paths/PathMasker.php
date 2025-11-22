<?php

namespace FluentSecurity\Modules\CustomPaths;

defined('ABSPATH') || exit;

class PathMasker
{
    protected $settings = [];

    protected $replacements = [];

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function register()
    {
        add_action('init', [$this, 'registerRewrites'], 20);
        add_filter('content_url', [$this, 'filterUrl'], PHP_INT_MAX);
        add_filter('includes_url', [$this, 'filterUrl'], PHP_INT_MAX);
        add_filter('site_url', [$this, 'filterUrl'], PHP_INT_MAX);
        add_filter('home_url', [$this, 'filterUrl'], PHP_INT_MAX);
        add_filter('plugins_url', [$this, 'filterUrl'], PHP_INT_MAX);
        add_filter('style_loader_src', [$this, 'filterUrl'], PHP_INT_MAX);
        add_filter('script_loader_src', [$this, 'filterUrl'], PHP_INT_MAX);
        add_filter('template_directory_uri', [$this, 'filterUrl'], PHP_INT_MAX);
        add_filter('stylesheet_directory_uri', [$this, 'filterUrl'], PHP_INT_MAX);
        add_filter('wp_get_attachment_image_src', [$this, 'filterAttachmentSrc'], PHP_INT_MAX);
        add_filter('wp_calculate_image_srcset', [$this, 'filterSrcset'], PHP_INT_MAX);
        add_action('template_redirect', [$this, 'maybeBuffer'], 0);
    }

    public function registerRewrites()
    {
        add_rewrite_rule('^' . preg_quote($this->settings['content_mask'], '/') . '/(.*)$', 'wp-content/$1', 'top');
        add_rewrite_rule('^' . preg_quote($this->settings['includes_mask'], '/') . '/(.*)$', 'wp-includes/$1', 'top');
        add_rewrite_rule('^' . preg_quote($this->settings['uploads_mask'], '/') . '/(.*)$', 'wp-content/uploads/$1', 'top');
        add_rewrite_rule('^' . preg_quote($this->settings['comments_mask'], '/') . '/?$', 'wp-comments-post.php', 'top');
    }

    public function maybeBuffer()
    {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $this->prepareReplacements();

        if (empty($this->replacements['from']) || empty($this->replacements['to'])) {
            return;
        }

        ob_start([$this, 'buffer']);
    }

    public function buffer($buffer)
    {
        if (!is_string($buffer) || strlen($buffer) < 255) {
            return $buffer;
        }

        if (!$this->isHtmlResponse()) {
            return $buffer;
        }

        return $this->replaceContent($buffer);
    }

    public function filterUrl($url)
    {
        $this->prepareReplacements();

        if (empty($this->replacements['from'])) {
            return $url;
        }

        return str_ireplace($this->replacements['from'], $this->replacements['to'], $url);
    }

    public function filterAttachmentSrc($image)
    {
        if (!is_array($image) || empty($image[0])) {
            return $image;
        }

        $image[0] = $this->filterUrl($image[0]);

        return $image;
    }

    public function filterSrcset($sources)
    {
        if (!is_array($sources)) {
            return $sources;
        }

        foreach ($sources as $key => $source) {
            if (!empty($source['url'])) {
                $sources[$key]['url'] = $this->filterUrl($source['url']);
            }
        }

        return $sources;
    }

    protected function prepareReplacements()
    {
        if (!empty($this->replacements)) {
            return;
        }

        // Avoid triggering the same filters we attach to by reading the raw option
        // values instead of calling site_url()/home_url(), which would recurse.
        $siteUrl  = untrailingslashit(get_option('siteurl'));
        $homeUrl  = untrailingslashit(get_option('home'));
        $from     = [];
        $to       = [];

        $this->appendMapping($from, $to, 'wp-content/', trailingslashit($this->settings['content_mask']));
        $this->appendMapping($from, $to, 'wp-includes/', trailingslashit($this->settings['includes_mask']));
        $this->appendMapping($from, $to, 'wp-content/uploads/', trailingslashit($this->settings['uploads_mask']));
        $this->appendMapping($from, $to, 'wp-comments-post.php', $this->settings['comments_mask']);

        $encodedFrom = array_map([$this, 'encodeUrl'], $from);
        $encodedTo   = array_map([$this, 'encodeUrl'], $to);

        $encodedFromFinal = array_map([$this, 'encodeUrlFinal'], $from);
        $encodedToFinal   = array_map([$this, 'encodeUrlFinal'], $to);

        // Add absolute versions for site/home urls to ensure theme assets and feeds are covered.
        $absoluteFrom = array_merge(
            array_map([$this, 'addDomain'], $from, array_fill(0, count($from), $siteUrl)),
            array_map([$this, 'addDomain'], $from, array_fill(0, count($from), $homeUrl))
        );
        $absoluteTo = array_merge(
            array_map([$this, 'addDomain'], $to, array_fill(0, count($to), $siteUrl)),
            array_map([$this, 'addDomain'], $to, array_fill(0, count($to), $homeUrl))
        );

        $this->replacements['from'] = array_merge($from, $absoluteFrom, $encodedFrom, $encodedFromFinal);
        $this->replacements['to']   = array_merge($to, $absoluteTo, $encodedTo, $encodedToFinal);
    }

    protected function appendMapping(array &$from, array &$to, $source, $destination)
    {
        $from[] = '/' . ltrim($source, '/');
        $to[]   = '/' . ltrim($destination, '/');
    }

    protected function encodeUrl($url)
    {
        return str_replace('/', '\\/', $url);
    }

    protected function encodeUrlFinal($url)
    {
        return rtrim($this->encodeUrl($url), '\\/');
    }

    protected function addDomain($url, $domain)
    {
        if (strpos($url, $domain) !== false) {
            return $url;
        }

        return trailingslashit($domain) . ltrim($url, '/');
    }

    protected function isHtmlResponse()
    {
        if (function_exists('is_feed') && is_feed()) {
            return false;
        }

        $contentType = '';

        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = strtolower($header);
                break;
            }
        }

        return (empty($contentType) || strpos($contentType, 'text/html') !== false);
    }

    protected function replaceContent($content)
    {
        return str_ireplace($this->replacements['from'], $this->replacements['to'], $content);
    }
}

