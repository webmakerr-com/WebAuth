<?php
/** @var array $settings */
defined('ABSPATH') || exit;
?>
<div class="wrap fls-custom-paths">
    <h1><?php esc_html_e('Custom Path Masking', 'fluent-security'); ?></h1>

    <?php if (!empty($_GET['fls_custom_paths_saved'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Custom path settings have been saved and rewrite rules refreshed.', 'fluent-security'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="fls-card">
        <?php wp_nonce_field('fls_custom_paths_save', 'fls_custom_paths_nonce'); ?>
        <input type="hidden" name="action" value="fls_custom_paths" />

        <p class="description">
            <?php esc_html_e('Mask core WordPress paths virtually without changing any files. Requests to the masked paths will be internally routed to the original locations.', 'fluent-security'); ?>
        </p>

        <table class="form-table fls-form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="fls-content-mask"><?php esc_html_e('Custom wp-content path', 'fluent-security'); ?></label>
                </th>
                <td>
                    <input id="fls-content-mask" name="fls_custom_paths[content_mask]" type="text" class="regular-text" value="<?php echo esc_attr($settings['content_mask']); ?>" />
                    <p class="description"><?php esc_html_e('Choose a virtual folder name to replace /wp-content/.', 'fluent-security'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fls-includes-mask"><?php esc_html_e('Custom wp-includes path', 'fluent-security'); ?></label>
                </th>
                <td>
                    <input id="fls-includes-mask" name="fls_custom_paths[includes_mask]" type="text" class="regular-text" value="<?php echo esc_attr($settings['includes_mask']); ?>" />
                    <p class="description"><?php esc_html_e('Choose a virtual folder name to replace /wp-includes/.', 'fluent-security'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fls-uploads-mask"><?php esc_html_e('Custom uploads path', 'fluent-security'); ?></label>
                </th>
                <td>
                    <input id="fls-uploads-mask" name="fls_custom_paths[uploads_mask]" type="text" class="regular-text" value="<?php echo esc_attr($settings['uploads_mask']); ?>" />
                    <p class="description"><?php esc_html_e('Choose a virtual folder name to replace /wp-content/uploads/.', 'fluent-security'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fls-comments-mask"><?php esc_html_e('Custom comment processor path', 'fluent-security'); ?></label>
                </th>
                <td>
                    <input id="fls-comments-mask" name="fls_custom_paths[comments_mask]" type="text" class="regular-text" value="<?php echo esc_attr($settings['comments_mask']); ?>" />
                    <p class="description"><?php esc_html_e('Choose a virtual endpoint to mask wp-comments-post.php.', 'fluent-security'); ?></p>
                </td>
            </tr>
            </tbody>
        </table>

        <p class="description">
            <?php esc_html_e('These changes are virtual only; no WordPress core folders are modified.', 'fluent-security'); ?>
        </p>

        <?php submit_button(__('Save Custom Paths', 'fluent-security')); ?>
    </form>
</div>
