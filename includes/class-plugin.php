<?php
/**
 * 插件协调层。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 启动各模块并处理站点地图声明。
 */
class Morn_Sitemap_Plus_Plugin {

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		Morn_Sitemap_Plus_Sitemap::init();
		Morn_Sitemap_Plus_Robots::init();
		Morn_Sitemap_Plus_Notifier::init();
		Morn_Sitemap_Plus_Admin::init();

		// 在 HTTP 链接中声明站点地图，供不支持 robots.txt 的爬虫发现。
		add_action( 'wp_head', array( __CLASS__, 'output_link' ), 1 );
	}

	/**
	 * 在 head 中输出 Sitemap link 标签。
	 *
	 * @return void
	 */
	public static function output_link() {
		$settings = morn_sitemap_plus_get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		printf(
			'<link rel="sitemap" type="application/xml" title="XML Sitemap" href="%s" />' . "\n",
			esc_url( morn_sitemap_plus_url( 'sitemap.xml' ) )
		);

		if ( ! empty( $settings['include_news'] ) ) {
			printf(
				'<link rel="sitemap" type="application/xml" title="Google News Sitemap" href="%s" />' . "\n",
				esc_url( morn_sitemap_plus_url( 'news-sitemap.xml' ) )
			);
		}
	}
}
