<?php
/**
 * Safe Paths manager.
 *
 * Handles virtual paths that are rewritten to WordPress core assets
 * without touching constants or physical directories.
 *
 * @package HMWP\Classes
 */

defined( 'ABSPATH' ) || die( 'Cheatin\' uh?' );

class HMWP_Classes_SafePaths {
/**
 * Option key for stored settings.
 */
const OPTION_KEY = 'hmwp_safe_paths';

/**
 * Marker used inside .htaccess.
 */
const MARKER = 'HMWP_SAFE_PATHS';

/**
 * Default safe path values.
 *
 * @return array
 */
public function get_defaults() {
return array(
'content'  => 'my-static-content',
'includes' => 'engine',
'uploads'  => 'assets-uploaded',
'comments' => 'comment-post',
);
}

/**
 * Hook into WordPress.
 */
public function __construct() {
add_action( 'admin_menu', array( $this, 'register_menu' ) );
add_action( 'admin_init', array( $this, 'maybe_handle_form' ) );
}

/**
 * Register settings page under Settings.
 */
public function register_menu() {
add_options_page(
esc_html__( 'Safe Paths', 'hide-my-wp' ),
esc_html__( 'Safe Paths', 'hide-my-wp' ),
'manage_options',
'hmwp-safe-paths',
array( $this, 'render_page' )
);
}

/**
 * Render the settings page.
 */
public function render_page() {
if ( ! current_user_can( 'manage_options' ) ) {
return;
}

$options  = $this->get_options();
$defaults = $this->get_defaults();
?>
<div class="wrap">
<h1><?php esc_html_e( 'Safe Virtual Paths', 'hide-my-wp' ); ?></h1>
<p><?php esc_html_e( 'Map fake paths to core WordPress locations using .htaccess rewrite rules only. Physical folders and constants remain untouched.', 'hide-my-wp' ); ?></p>
<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=hmwp-safe-paths' ) ); ?>">
<?php wp_nonce_field( 'hmwp_safe_paths_save', 'hmwp_safe_paths_nonce' ); ?>
<table class="form-table" role="presentation">
<tbody>
<tr>
<th scope="row"><label for="hmwp_safe_paths_content"><?php esc_html_e( 'Custom wp-content path', 'hide-my-wp' ); ?></label></th>
<td>
<input type="text" class="regular-text" id="hmwp_safe_paths_content" name="hmwp_safe_paths[content]" value="<?php echo esc_attr( $options['content'] ); ?>" placeholder="<?php echo esc_attr( $defaults['content'] ); ?>" />
<p class="description"><?php esc_html_e( 'This creates a safe virtual path that rewrites to wp-content.', 'hide-my-wp' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="hmwp_safe_paths_includes"><?php esc_html_e( 'Custom wp-includes path', 'hide-my-wp' ); ?></label></th>
<td>
<input type="text" class="regular-text" id="hmwp_safe_paths_includes" name="hmwp_safe_paths[includes]" value="<?php echo esc_attr( $options['includes'] ); ?>" placeholder="<?php echo esc_attr( $defaults['includes'] ); ?>" />
<p class="description"><?php esc_html_e( 'This creates a safe virtual path that rewrites to wp-includes.', 'hide-my-wp' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="hmwp_safe_paths_uploads"><?php esc_html_e( 'Custom uploads path', 'hide-my-wp' ); ?></label></th>
<td>
<input type="text" class="regular-text" id="hmwp_safe_paths_uploads" name="hmwp_safe_paths[uploads]" value="<?php echo esc_attr( $options['uploads'] ); ?>" placeholder="<?php echo esc_attr( $defaults['uploads'] ); ?>" />
<p class="description"><?php esc_html_e( 'This creates a safe virtual path that rewrites to wp-content/uploads.', 'hide-my-wp' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="hmwp_safe_paths_comments"><?php esc_html_e( 'Custom comment processor path', 'hide-my-wp' ); ?></label></th>
<td>
<input type="text" class="regular-text" id="hmwp_safe_paths_comments" name="hmwp_safe_paths[comments]" value="<?php echo esc_attr( $options['comments'] ); ?>" placeholder="<?php echo esc_attr( $defaults['comments'] ); ?>" />
<p class="description"><?php esc_html_e( 'This creates a safe virtual path that rewrites to wp-comments-post.php.', 'hide-my-wp' ); ?></p>
</td>
</tr>
</tbody>
</table>
<?php submit_button( esc_html__( 'Save Safe Paths', 'hide-my-wp' ) ); ?>
</form>
</div>
<?php
}

/**
 * Handle saving the settings.
 */
public function maybe_handle_form() {
if ( ! isset( $_POST['hmwp_safe_paths_nonce'] ) ) {
return;
}

if ( ! current_user_can( 'manage_options' ) ) {
return;
}

if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hmwp_safe_paths_nonce'] ) ), 'hmwp_safe_paths_save' ) ) {
return;
}

$raw   = isset( $_POST['hmwp_safe_paths'] ) && is_array( $_POST['hmwp_safe_paths'] ) ? wp_unslash( $_POST['hmwp_safe_paths'] ) : array();
$paths = $this->sanitize_paths( $raw );

update_option( self::OPTION_KEY, $paths );

$this->refresh_rewrites( $paths );

add_settings_error( 'hmwp_safe_paths', 'hmwp_safe_paths_saved', esc_html__( 'Safe paths updated and rewrite rules refreshed.', 'hide-my-wp' ), 'updated' );
set_transient( 'settings_errors', get_settings_errors(), 30 );
wp_safe_redirect( admin_url( 'options-general.php?page=hmwp-safe-paths' ) );
exit;
}

/**
 * Get options merged with defaults.
 *
 * @return array
 */
public function get_options() {
$saved   = get_option( self::OPTION_KEY, array() );
$options = wp_parse_args( is_array( $saved ) ? $saved : array(), $this->get_defaults() );

return array_map( array( $this, 'trim_path' ), $options );
}

/**
 * Sanitize submitted paths.
 *
 * @param array $raw Raw values.
 *
 * @return array
 */
private function sanitize_paths( $raw ) {
$defaults = $this->get_defaults();

return array(
'content'  => $this->sanitize_segment( isset( $raw['content'] ) ? $raw['content'] : $defaults['content'] ),
'includes' => $this->sanitize_segment( isset( $raw['includes'] ) ? $raw['includes'] : $defaults['includes'] ),
'uploads'  => $this->sanitize_segment( isset( $raw['uploads'] ) ? $raw['uploads'] : $defaults['uploads'] ),
'comments' => $this->sanitize_segment( isset( $raw['comments'] ) ? $raw['comments'] : $defaults['comments'], true ),
);
}

/**
 * Trim a path.
 *
 * @param string $value Path.
 *
 * @return string
 */
private function trim_path( $value ) {
return trim( (string) $value );
}

/**
 * Sanitize a single path segment.
 *
 * @param string $value Value to sanitize.
 * @param bool   $allow_empty Whether empty values are allowed.
 *
 * @return string
 */
private function sanitize_segment( $value, $allow_empty = false ) {
$value = is_string( $value ) ? $value : '';
$value = trim( $value );
$value = trim( $value, "/\t\n\r\0\x0B" );
$value = str_replace( '.', '', $value );
$value = preg_replace( '/[^A-Za-z0-9\\-_]/', '', $value );
$value = trim( $value, '/\\' );

if ( $value === '' && ! $allow_empty ) {
return '';
}

return $value;
}

/**
 * Build rewrite rules.
 *
 * @param array $paths Paths to use.
 *
 * @return string
 */
private function build_rules( $paths ) {
$rules = array( '# BEGIN ' . self::MARKER, '<IfModule mod_rewrite.c>', 'RewriteEngine On' );

if ( ! empty( $paths['content'] ) ) {
$rules[] = '# Fake wp-content';
$rules[] = 'RewriteRule ^' . preg_quote( $paths['content'], '/' ) . '\/(.*)$ wp-content/$1 [L]';
}

if ( ! empty( $paths['includes'] ) ) {
$rules[] = '# Fake wp-includes';
$rules[] = 'RewriteRule ^' . preg_quote( $paths['includes'], '/' ) . '\/(.*)$ wp-includes/$1 [L]';
}

if ( ! empty( $paths['uploads'] ) ) {
$rules[] = '# Fake uploads';
$rules[] = 'RewriteRule ^' . preg_quote( $paths['uploads'], '/' ) . '\/(.*)$ wp-content/uploads/$1 [L]';
}

if ( ! empty( $paths['comments'] ) ) {
$rules[] = '# Fake comments processor';
$rules[] = 'RewriteRule ^' . preg_quote( $paths['comments'], '/' ) . '$ wp-comments-post.php [L]';
}

$rules[] = '</IfModule>';
$rules[] = '# END ' . self::MARKER;

$rules = array_filter( $rules );

if ( count( $rules ) <= 3 ) {
return '';
}

return implode( PHP_EOL, $rules ) . PHP_EOL;
}

/**
 * Update .htaccess with new rules.
 *
 * @param array $paths Paths to use.
 */
private function refresh_rewrites( $paths = null ) {
if ( null === $paths ) {
$paths = $this->get_options();
}

$config_file   = trailingslashit( HMWP_Classes_Tools::getRootPath() ) . '.htaccess';
$wp_filesystem = HMWP_Classes_ObjController::initFilesystem();

if ( ! $wp_filesystem ) {
return;
}

$contents = $wp_filesystem->exists( $config_file ) ? $wp_filesystem->get_contents( $config_file ) : '';
$contents = $this->strip_existing_rules( $contents );

$rules = $this->build_rules( $paths );
if ( $rules ) {
$contents = rtrim( $contents ) . PHP_EOL . PHP_EOL . $rules;
}

$wp_filesystem->put_contents( $config_file, $contents );
flush_rewrite_rules( false );
}

/**
 * Remove existing marker rules.
 *
 * @param string $contents File contents.
 *
 * @return string
 */
private function strip_existing_rules( $contents ) {
$pattern = '/# BEGIN ' . self::MARKER . '.*?# END ' . self::MARKER . '\n?/s';

return preg_replace( $pattern, '', $contents );
}

/**
 * Add default options on activation.
 */
public function activate() {
if ( ! get_option( self::OPTION_KEY ) ) {
add_option( self::OPTION_KEY, $this->get_defaults() );
}

$this->refresh_rewrites( $this->get_options() );
}

/**
 * Remove rules on deactivation.
 */
public function deactivate() {
$config_file   = trailingslashit( HMWP_Classes_Tools::getRootPath() ) . '.htaccess';
$wp_filesystem = HMWP_Classes_ObjController::initFilesystem();

if ( ! $wp_filesystem ) {
return;
}

$contents = $wp_filesystem->exists( $config_file ) ? $wp_filesystem->get_contents( $config_file ) : '';
$contents = $this->strip_existing_rules( $contents );
$wp_filesystem->put_contents( $config_file, $contents );
flush_rewrite_rules( false );
}
}
