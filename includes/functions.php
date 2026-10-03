<?php
/**
 * 插件通用辅助函数。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 读取设置并与默认值合并。
 *
 * @return array
 */
function morn_sitemap_plus_get_settings() {
	$defaults = array(
		'enabled'            => 1,
		'post_types'         => array( 'post', 'page' ),
		'taxonomies'         => array( 'category', 'post_tag' ),
		'include_authors'    => 1,
		'per_page'           => 500,
		'include_images'     => 1,
		'include_news'       => 1,
		'news_days'          => 30,
		'exclude_ids'        => '',
		'exclude_terms'      => '',
		'changefreq'         => 'weekly',
		'priority_front'     => '1.0',
		'priority_post'      => '0.8',
		'priority_page'      => '0.6',
		'priority_archive'   => '0.4',
		'auto_ping'          => 0,
		'ping_interval'      => 86400,
		'robots_enabled'     => 1,
		'http_cache'         => 1,
		'include_lastmod'    => 1,
	);

	$saved = get_option( MORN_SITEMAP_PLUS_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	/**
	 * 过滤站点地图插件设置。
	 *
	 * @param array $settings 合并默认值后的设置。
	 */
	return apply_filters( 'morn_sitemap_plus_settings', array_merge( $defaults, $saved ) );
}

/**
 * 读取单个设置项。
 *
 * @param string $key     设置键名。
 * @param mixed  $default 默认值。
 * @return mixed
 */
function morn_sitemap_plus_get_setting( $key, $default = null ) {
	$settings = morn_sitemap_plus_get_settings();

	if ( ! array_key_exists( $key, $settings ) ) {
		return $default;
	}

	return $settings[ $key ];
}

/**
 * 把多行文本按行拆为数组。
 *
 * @param string $value 多行文本。
 * @return array
 */
function morn_sitemap_plus_split_lines( $value ) {
	if ( ! is_string( $value ) ) {
		return array();
	}

	$lines = preg_split( '/\r\n|\r|\n/', $value );
	$out   = array();

	foreach ( (array) $lines as $line ) {
		$line = trim( $line );

		if ( '' !== $line ) {
			$out[] = $line;
		}
	}

	return $out;
}

/**
 * 把逗号分隔文本拆为 ID 数组。
 *
 * @param string $value 原始值。
 * @return array
 */
function morn_sitemap_plus_parse_ids( $value ) {
	$ids = array();

	foreach ( morn_sitemap_plus_split_lines( (string) $value ) as $line ) {
		foreach ( explode( ',', $line ) as $part ) {
			$part = trim( $part );

			if ( '' !== $part ) {
				$ids[] = absint( $part );
			}
		}
	}

	return array_values( array_filter( $ids ) );
}

/**
 * XML 文本转义。
 *
 * 用于生成合法 XML 文档。
 *
 * @param string $text 原始文本。
 * @return string
 */
function morn_sitemap_plus_esc_xml( $text ) {
	$text = (string) $text;

	// 移除 XML 1.0 不允许的控制字符。
	$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text );

	// htmlspecialchars 会把单引号转成 &#039;，XML 中合法。
	return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

/**
 * 获取文章类型在站点地图中的键名。
 *
 * @param string $post_type 文章类型。
 * @return string
 */
function morn_sitemap_plus_provider_key( $post_type ) {
	return sanitize_key( str_replace( '_', '-', $post_type ) );
}

/**
 * 根据时间距今计算 changefreq。
 *
 * @param string $date_gmt GMT 日期。
 * @param string $base    基础频率设置。
 * @return string
 */
function morn_sitemap_plus_calc_changefreq( $date_gmt, $base = 'weekly' ) {
	$timestamp = strtotime( $date_gmt . ' UTC' );

	if ( ! $timestamp ) {
		return $base;
	}

	$days = (int) floor( ( time() - $timestamp ) / DAY_IN_SECONDS );

	// 近 7 天内更新过的内容更可能变化，给更高频的声明。
	if ( $days <= 7 ) {
		return 'daily';
	}

	if ( $days <= 30 ) {
		return 'weekly';
	}

	if ( $days <= 180 ) {
		return 'monthly';
	}

	return 'yearly';
}

/**
 * 判断内容是否应出现在站点地图中。
 *
 * @param int $post_id 文章 ID。
 * @return bool
 */
function morn_sitemap_plus_is_includable( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	if ( 'publish' !== $post->post_status ) {
		return false;
	}

	if ( post_password_required( $post ) ) {
		return false;
	}

	$settings = morn_sitemap_plus_get_settings();
	$excluded = morn_sitemap_plus_parse_ids( $settings['exclude_ids'] );

	if ( in_array( (int) $post->ID, $excluded, true ) ) {
		return false;
	}

	/**
	 * 过滤单篇内容是否应包含在站点地图中。
	 *
	 * @param bool     $include 是否包含。
	 * @param WP_Post  $post    文章对象。
	 * @param int      $post_id 文章 ID。
	 */
	return (bool) apply_filters( 'morn_sitemap_plus_include_post', true, $post, $post->ID );
}

/**
 * 获取站点地图的公共 URL。
 *
 * @param string $path 路径。
 * @return string
 */
function morn_sitemap_plus_url( $path = '' ) {
	return home_url( '/' . ltrim( $path, '/' ) );
}
