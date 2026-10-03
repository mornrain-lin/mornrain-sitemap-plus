<?php
/**
 * 条件请求与 HTTP 缓存头。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 处理 Last-Modified / ETag 与 304 响应。
 */
class Morn_Sitemap_Plus_Http {

	/**
	 * 计算内容的 ETag。
	 *
	 * @param string $content 内容。
	 * @return string
	 */
	public static function make_etag( $content ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.hash_md5_quantum -- 用于生成 ETag，非安全用途。
		return '"' . md5( (string) $content ) . '"';
	}

	/**
	 * 发送条件请求响应头。
	 *
	 * 若客户端缓存仍然有效则直接返回 304 并终止。
	 *
	 * @param string $content 已生成的内容。
	 * @return void
	 */
	public static function send_conditional( $content ) {
		$settings = morn_sitemap_plus_get_settings();
		$lastmod  = self::get_last_modified( $content );
		$etag     = self::make_etag( $content );

		if ( ! empty( $settings['http_cache'] ) ) {
			header( 'Last-Modified: ' . $lastmod );
			header( 'ETag: ' . $etag );
		}

		if ( self::is_not_modified( $lastmod, $etag ) ) {
			// 304 不应包含响应体。
			status_header( 304 );
			exit;
		}
	}

	/**
	 * 判断客户端缓存是否仍然有效。
	 *
	 * @param string $lastmod Last-Modified 值。
	 * @param string $etag    ETag 值。
	 * @return bool
	 */
	public static function is_not_modified( $lastmod, $etag ) {
		if ( ! empty( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			$client_etag = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) );

			// 支持多值与弱比较。
			$client_tags = array_map( 'trim', explode( ',', $client_etag ) );

			foreach ( $client_tags as $tag ) {
				$normalized = ltrim( $tag, 'W/' );

				if ( $normalized === $etag || '*' === $tag ) {
					return true;
				}
			}

			// If-None-Match 存在时不看 If-Modified-Since。
			return false;
		}

		if ( ! empty( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
			$client_time = strtotime( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) );

			if ( $client_time && $client_time >= strtotime( $lastmod ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 从内容中提取最新的修改时间。
	 *
	 * 解析所有 <lastmod> 节点取最大值。解析失败时退化为当前时间。
	 *
	 * @param string $content XML 内容。
	 * @return string HTTP 日期格式。
	 */
	public static function get_last_modified( $content ) {
		$max = 0;

		if ( preg_match_all( '#<lastmod>([^<]+)</lastmod>#', (string) $content, $matches ) ) {
			foreach ( $matches[1] as $value ) {
				$timestamp = strtotime( trim( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) ) );

				if ( $timestamp && $timestamp > $max ) {
					$max = (int) $timestamp;
				}
			}
		}

		if ( $max <= 0 ) {
			$max = time();
		}

		return gmdate( 'D, d M Y H:i:s', $max ) . ' GMT';
	}

	/**
	 * 发送 XML 响应头。
	 *
	 * @param bool $cacheable 是否允许中间层缓存。
	 * @return void
	 */
	public static function send_headers( $cacheable = true ) {
		if ( headers_sent() ) {
			return;
		}

		header( 'Content-Type: application/xml; charset=' . get_bloginfo( 'charset' ) );
		header( 'X-Robots-Tag: noindex, follow', true );

		if ( $cacheable ) {
			$ttl = HOUR_IN_SECONDS;
			header( 'Cache-Control: public, max-age=' . (int) $ttl );
		} else {
			header( 'Cache-Control: no-cache, must-revalidate' );
		}
	}
}
