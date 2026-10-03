<?php
/**
 * Google News 站点地图。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 生成符合 Google News 标准的新闻站点地图。
 */
class Morn_Sitemap_Plus_News {

	/**
	 * Google News 最多收录的天数。
	 */
	const MAX_DAYS = 30;

	/**
	 * 构造新闻站点地图 XML。
	 *
	 * @return string
	 */
	public static function get_xml() {
		$settings = morn_sitemap_plus_get_settings();

		// Google News 只接受 30 天内的内容，配置超出时按 30 天封顶。
		$days = max( 1, min( self::MAX_DAYS, (int) $settings['news_days'] ) );
		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 1000,
			'date_query'          => array(
				array(
					'after'     => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
					'inclusive' => true,
				),
			),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		$excluded = morn_sitemap_plus_parse_ids( $settings['exclude_ids'] );

		if ( ! empty( $excluded ) ) {
			$args['post__not_in'] = $excluded;
		}

		$query = new WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! morn_sitemap_plus_is_includable( $post->ID ) ) {
				continue;
			}

			$item = self::build_item( $post );

			if ( $item ) {
				$items[] = $item;
			}
		}

		wp_reset_postdata();

		$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

		foreach ( $items as $item ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . morn_sitemap_plus_esc_xml( $item['loc'] ) . "</loc>\n";
			$xml .= "\t\t<news:news>\n";
			$xml .= "\t\t\t<news:publication>\n";
			$xml .= "\t\t\t\t<news:name>" . morn_sitemap_plus_esc_xml( $item['publication'] ) . "</news:name>\n";
			$xml .= "\t\t\t\t<news:language>" . morn_sitemap_plus_esc_xml( $item['language'] ) . "</news:language>\n";
			$xml .= "\t\t\t</news:publication>\n";
			$xml .= "\t\t\t<news:publication_date>" . morn_sitemap_plus_esc_xml( $item['date'] ) . "</news:publication_date>\n";
			$xml .= "\t\t\t<news:title>" . morn_sitemap_plus_esc_xml( $item['title'] ) . "</news:title>\n";

			if ( '' !== $item['author'] ) {
				$xml .= "\t\t\t<news:authors>\n";
				$xml .= "\t\t\t\t<news:author>\n";
				$xml .= "\t\t\t\t\t<news:name>" . morn_sitemap_plus_esc_xml( $item['author'] ) . "</news:name>\n";
				$xml .= "\t\t\t\t</news:author>\n";
				$xml .= "\t\t\t</news:authors>\n";
			}

			$xml .= "\t\t</news:news>\n";
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>' . "\n";

		/**
		 * 过滤新闻站点地图 XML。
		 *
		 * @param string $xml  XML 文本。
		 * @param array  $items 条目数组。
		 */
		return apply_filters( 'morn_sitemap_plus_news_xml', $xml, $items );
	}

	/**
	 * 构造单条新闻数据。
	 *
	 * @param WP_Post $post 文章对象。
	 * @return array 空数组表示跳过。
	 */
	private static function build_item( $post ) {
		$link = get_permalink( $post );

		if ( ! $link ) {
			return array();
		}

		$title = wp_strip_all_tags( get_the_title( $post ) );

		// Google News 要求标题不能为空。
		if ( '' === trim( $title ) ) {
			return array();
		}

		$language = get_bloginfo( 'language' );
		$language = str_replace( '_', '-', $language );

		$item = array(
			'loc'         => $link,
			'title'       => $title,
			'publication' => get_bloginfo( 'name' ),
			'language'    => $language,
			'date'        => get_post_time( 'c', true, $post ),
			'author'      => '',
		);

		$author_id = (int) $post->post_author;

		if ( $author_id > 0 ) {
			$name = get_the_author_meta( 'display_name', $author_id );

			if ( is_string( $name ) && '' !== trim( $name ) ) {
				$item['author'] = wp_strip_all_tags( $name );
			}
		}

		/**
		 * 过滤单条新闻数据。
		 *
		 * @param array   $item 新闻数据。
		 * @param WP_Post $post 文章对象。
		 */
		return apply_filters( 'morn_sitemap_plus_news_item', $item, $post );
	}
}
