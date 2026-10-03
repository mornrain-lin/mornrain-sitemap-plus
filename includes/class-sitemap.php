<?php
/**
 * XML 站点地图生成与路由。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 处理 /sitemap.xml、/sitemap-{key}.xml 与 /news-sitemap.xml 请求。
 */
class Morn_Sitemap_Plus_Sitemap {

	/**
	 * 索引页查询变量。
	 */
	const QUERY_VAR_INDEX = 'morn_sitemap_index';

	/**
	 * 子站图查询变量。
	 */
	const QUERY_VAR_CHILD = 'morn_sitemap_child';

	/**
	 * 新闻站图查询变量。
	 */
	const QUERY_VAR_NEWS = 'morn_sitemap_news';

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 0 );
	}

	/**
	 * 注册重写规则。
	 *
	 * @return void
	 */
	public static function register_rewrites() {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?' . self::QUERY_VAR_INDEX . '=1', 'top' );
		add_rewrite_rule( '^sitemap-([a-z0-9\-]+)\.xml$', 'index.php?' . self::QUERY_VAR_CHILD . '=$matches[1]', 'top' );
		add_rewrite_rule( '^news-sitemap\.xml$', 'index.php?' . self::QUERY_VAR_NEWS . '=1', 'top' );
	}

	/**
	 * 注册查询变量。
	 *
	 * @param array $vars 查询变量。
	 * @return array
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR_INDEX;
		$vars[] = self::QUERY_VAR_CHILD;
		$vars[] = self::QUERY_VAR_NEWS;

		return $vars;
	}

	/**
	 * 判断当前请求并输出对应站点地图。
	 *
	 * @return void
	 */
	public static function maybe_render() {
		$settings = morn_sitemap_plus_get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		$is_index = (bool) get_query_var( self::QUERY_VAR_INDEX );
		$child    = (string) get_query_var( self::QUERY_VAR_CHILD );
		$is_news  = (bool) get_query_var( self::QUERY_VAR_NEWS );

		if ( ! $is_index && '' === $child && ! $is_news ) {
			return;
		}

		// 站点设为不公开时不输出站点地图。
		if ( '1' !== (string) get_option( 'blog_public' ) ) {
			status_header( 403 );
			Morn_Sitemap_Plus_Http::send_headers( false );
			echo '<error>' . morn_sitemap_plus_esc_xml( __( '本站已设置为不公开索引，站点地图不可用。', 'morn-sitemap-plus' ) ) . '</error>';
			exit;
		}

		if ( $is_news ) {
			if ( empty( $settings['include_news'] ) ) {
				status_header( 404 );
				exit;
			}

			$content = self::render_news();

			Morn_Sitemap_Plus_Http::send_headers();
			Morn_Sitemap_Plus_Http::send_conditional( $content );
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML 已内部转义。
			exit;
		}

		if ( '' !== $child ) {
			$content = self::render_child( sanitize_key( $child ) );

			if ( '' === $content ) {
				status_header( 404 );
				nocache_headers();
				exit;
			}

			Morn_Sitemap_Plus_Http::send_headers();
			Morn_Sitemap_Plus_Http::send_conditional( $content );
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML 已内部转义。
			exit;
		}

		$content = self::render_index();

		Morn_Sitemap_Plus_Http::send_headers();
		Morn_Sitemap_Plus_Http::send_conditional( $content );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML 已内部转义。
		exit;
	}

	/**
	 * 渲染站点地图索引页。
	 *
	 * @return string
	 */
	public static function render_index() {
		$settings = morn_sitemap_plus_get_settings();
		$per_page = max( 1, (int) $settings['per_page'] );
		$lastmod  = self::get_site_lastmod();

		$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		// sitemapindex 的每个 <loc> 必须是子站图地址，不能放页面 URL，
		// 否则整个索引会被搜索引擎判定为非法而丢弃。
		foreach ( Morn_Sitemap_Plus_Providers::get_sections() as $section ) {
			$total = Morn_Sitemap_Plus_Providers::count_entries( $section['key'] );

			// 条目为 0 的分片不写入索引，避免产出空的 urlset 与 404。
			if ( $total <= 0 ) {
				continue;
			}

			$pages = max( 1, (int) ceil( $total / $per_page ) );

			for ( $page = 1; $page <= $pages; $page++ ) {
				$url = 'sitemap-' . $section['key'] . ( $page > 1 ? '-' . $page : '' ) . '.xml';

				$xml .= "\t<sitemap>\n";
				$xml .= "\t\t<loc>" . morn_sitemap_plus_esc_xml( morn_sitemap_plus_url( $url ) ) . "</loc>\n";
				$xml .= "\t\t<lastmod>" . morn_sitemap_plus_esc_xml( $lastmod ) . "</lastmod>\n";
				$xml .= "\t</sitemap>\n";
			}
		}

		if ( ! empty( $settings['include_news'] ) ) {
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . morn_sitemap_plus_esc_xml( morn_sitemap_plus_url( 'news-sitemap.xml' ) ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . morn_sitemap_plus_esc_xml( $lastmod ) . "</lastmod>\n";
			$xml .= "\t</sitemap>\n";
		}

		$xml .= '</sitemapindex>' . "\n";

		/**
		 * 过滤站点地图索引页 XML。
		 *
		 * @param string $xml XML 文本。
		 */
		return apply_filters( 'morn_sitemap_plus_index_xml', $xml );
	}

	/**
	 * 渲染子站图。
	 *
	 * @param string $key 分片键名，可带 "-页码" 后缀。
	 * @return string 空字符串表示无效分片。
	 */
	public static function render_child( $key ) {
		$settings = morn_sitemap_plus_get_settings();
		$per_page = max( 1, (int) $settings['per_page'] );

		$page = 1;
		$base = $key;

		// 解析末尾的 -N 页码。
		if ( preg_match( '/^(.*)-(\d+)$/', $key, $matches ) ) {
			$base = $matches[1];
			$page = max( 1, (int) $matches[2] );
		}

		$sections = Morn_Sitemap_Plus_Providers::get_sections();
		$valid    = false;

		foreach ( $sections as $section ) {
			if ( $section['key'] === $base ) {
				$valid = true;
				break;
			}
		}

		if ( ! $valid ) {
			return '';
		}

		// 页码上限保护：避免爬虫请求超大 OFFSET 造成昂贵的数据库扫描。
		$max_pages = 2000;

		if ( $page > $max_pages ) {
			return '';
		}

		$entries = Morn_Sitemap_Plus_Providers::get_entries( $base, $page, $per_page );
		$has_images = ! empty( $settings['include_images'] );

		$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . morn_sitemap_plus_esc_xml( $entry['loc'] ) . "</loc>\n";

			if ( ! empty( $settings['include_lastmod'] ) && ! empty( $entry['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . morn_sitemap_plus_esc_xml( $entry['lastmod'] ) . "</lastmod>\n";
			}

			if ( ! empty( $entry['changefreq'] ) ) {
				$xml .= "\t\t<changefreq>" . morn_sitemap_plus_esc_xml( $entry['changefreq'] ) . "</changefreq>\n";
			}

		if ( isset( $entry['priority'] ) ) {
			$xml .= "\t\t<priority>" . morn_sitemap_plus_esc_xml( number_format( (float) $entry['priority'], 1 ) ) . "</priority>\n";
		}

			if ( $has_images && ! empty( $entry['images'] ) ) {
				$xml .= self::render_image_nodes( $entry['images'] );
			}

			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>' . "\n";

		/**
		 * 过滤子站图 XML。
		 *
		 * @param string $xml     XML 文本。
		 * @param string $key     分片键名。
		 * @param int    $page    页码。
		 */
		return apply_filters( 'morn_sitemap_plus_child_xml', $xml, $key, $page );
	}

	/**
	 * 渲染图片节点。
	 *
	 * @param array $images 图片数组。
	 * @return string
	 */
	private static function render_image_nodes( $images ) {
		$xml = '';

		foreach ( $images as $image ) {
			if ( empty( $image['loc'] ) ) {
				continue;
			}

			$xml .= "\t\t<image:image>\n";
			$xml .= "\t\t\t<image:loc>" . morn_sitemap_plus_esc_xml( $image['loc'] ) . "</image:loc>\n";

			if ( ! empty( $image['lastmod'] ) ) {
				$xml .= "\t\t\t<image:lastmod>" . morn_sitemap_plus_esc_xml( $image['lastmod'] ) . "</image:lastmod>\n";
			}
		if ( ! isset( $image['caption'] ) ) {
			$xml .= "\t\t\t<image:caption>" . morn_sitemap_plus_esc_xml( __( '图片', 'morn-sitemap-plus' ) ) . "</image:caption>\n";
		} else {
				$xml .= "\t\t\t<image:caption>" . morn_sitemap_plus_esc_xml( $image['caption'] ) . "</image:caption>\n";
			}
			if ( isset( $image['title'] ) ) {
				$xml .= "\t\t\t<image:title>" . morn_sitemap_plus_esc_xml( $image['title'] ) . "</image:title>\n";
			}
			if ( isset( $image['license'] ) ) {
				$xml .= "\t\t\t<image:license>" . morn_sitemap_plus_esc_xml( $image['license'] ) . "</image:license>\n";
			}
			if ( isset( $image['width'] ) && isset( $image['height'] ) ) {
				$xml .= "\t\t\t<image:width>" . (int) $image['width'] . "</image:width>\n";
				$xml .= "\t\t\t<image:height>" . (int) $image['height'] . "</image:height>\n";
			}

			$xml .= "\t\t</image:image>\n";
		}

		return $xml;
	}

	/**
	 * 渲染新闻站点地图。
	 *
	 * @return string
	 */
	public static function render_news() {
		return Morn_Sitemap_Plus_News::get_xml();
	}

	/**
	 * 获取站点最近修改时间。
	 *
	 * @return string
	 */
	public static function get_site_lastmod() {
		global $wpdb;

		$latest = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_modified_gmt FROM {$wpdb->posts} WHERE post_status = %s AND post_type = %s ORDER BY post_modified_gmt DESC LIMIT 1",
				'publish',
				'post'
			)
		);

		$timestamp = $latest ? strtotime( $latest . ' UTC' ) : time();

		return gmdate( 'c', (int) max( $timestamp, time() - YEAR_IN_SECONDS ) );
	}
}
