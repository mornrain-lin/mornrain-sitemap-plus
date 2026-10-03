<?php
/**
 * Plugin Name: Morn Sitemap Plus
 * Plugin URI: https://github.com/mornrain/morn-sitemap-plus
 * Description: 增强型站点地图插件。生成符合 sitemaps.org 0.9 规范的独立 XML 站点地图与子站图分片，支持 post/page/自定义文章类型/分类/标签/作者归档，包含 lastmod、changefreq、priority，提供图片站点地图与 Google News 站点地图扩展，支持 Last-Modified/ETag 与 304、robots.txt 追加、定时 ping。零外部资源。
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.6
 * Author: MornRain
 * Author URI: https://github.com/mornrain
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: morn-sitemap-plus
 * Domain Path: /languages
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 插件版本。
 */
define( 'MORN_SITEMAP_PLUS_VERSION', '1.0.0' );

/**
 * 主文件路径。
 */
define( 'MORN_SITEMAP_PLUS_FILE', __FILE__ );

/**
 * 插件目录路径。
 */
define( 'MORN_SITEMAP_PLUS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * 插件目录 URL。
 */
define( 'MORN_SITEMAP_PLUS_URL', plugin_dir_url( __FILE__ ) );

/**
 * 设置选项名。
 */
define( 'MORN_SITEMAP_PLUS_OPTION', 'morn_sitemap_plus_settings' );

require_once MORN_SITEMAP_PLUS_DIR . 'includes/functions.php';
require_once MORN_SITEMAP_PLUS_DIR . 'includes/class-http.php';
require_once MORN_SITEMAP_PLUS_DIR . 'includes/class-providers.php';
require_once MORN_SITEMAP_PLUS_DIR . 'includes/class-images.php';
require_once MORN_SITEMAP_PLUS_DIR . 'includes/class-news.php';
require_once MORN_SITEMAP_PLUS_DIR . 'includes/class-sitemap.php';
require_once MORN_SITEMAP_PLUS_DIR . 'includes/class-robots.php';
require_once MORN_SITEMAP_PLUS_DIR . 'includes/class-notifier.php';
require_once MORN_SITEMAP_PLUS_DIR . 'includes/class-admin.php';
require_once MORN_SITEMAP_PLUS_DIR . 'includes/class-plugin.php';

/**
 * 启动插件。
 *
 * @return void
 */
function morn_sitemap_plus_boot() {
	load_plugin_textdomain( 'morn-sitemap-plus', false, dirname( plugin_basename( MORN_SITEMAP_PLUS_FILE ) ) . '/languages' );

	Morn_Sitemap_Plus_Plugin::init();
}
add_action( 'plugins_loaded', 'morn_sitemap_plus_boot' );

/**
 * 激活钩子。
 *
 * @return void
 */
function morn_sitemap_plus_activate() {
	Morn_Sitemap_Plus_Sitemap::register_rewrites();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'morn_sitemap_plus_activate' );

/**
 * 停用钩子。
 *
 * @return void
 */
function morn_sitemap_plus_deactivate() {
	flush_rewrite_rules();
	wp_clear_scheduled_hook( 'morn_sitemap_plus_ping' );
}
register_deactivation_hook( __FILE__, 'morn_sitemap_plus_deactivate' );
