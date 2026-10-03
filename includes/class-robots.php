<?php
/**
 * robots.txt 集成。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 向 robots.txt 追加 Sitemap 行。
 */
class Morn_Sitemap_Plus_Robots {

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'filter_robots_txt' ), 20, 2 );
	}

	/**
	 * 追加 Sitemap 行。
	 *
	 * @param string $output robots.txt 内容。
	 * @param bool   $public 是否允许索引。
	 * @return string
	 */
	public static function filter_robots_txt( $output, $public ) {
		$settings = morn_sitemap_plus_get_settings();

		if ( empty( $settings['enabled'] ) || empty( $settings['robots_enabled'] ) ) {
			return $output;
		}

		$lines = array();

		if ( $public ) {
			$lines[] = 'Sitemap: ' . morn_sitemap_plus_url( 'sitemap.xml' );

			if ( ! empty( $settings['include_news'] ) ) {
				$lines[] = 'Sitemap: ' . morn_sitemap_plus_url( 'news-sitemap.xml' );
			}
		}

		/**
		 * 过滤追加到 robots.txt 的行。
		 *
		 * @param array  $lines  指令行数组。
		 * @param string $output 原始内容。
		 * @param bool   $public 是否允许索引。
		 */
		$lines = apply_filters( 'morn_sitemap_plus_robots_lines', $lines, $output, $public );

		if ( empty( $lines ) ) {
			return $output;
		}

		return rtrim( (string) $output ) . "\n\n" . implode( "\n", $lines ) . "\n";
	}
}
