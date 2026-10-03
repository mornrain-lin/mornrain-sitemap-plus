<?php
/**
 * 站点地图内容提供者。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 收集各类内容并构造成分片条目。
 */
class Morn_Sitemap_Plus_Providers {

	/**
	 * 获取所有分片列表。
	 *
	 * @return array
	 */
	public static function get_sections() {
		$settings = morn_sitemap_plus_get_settings();
		$sections = array();

		// 文章类型。
		$post_types = is_array( $settings['post_types'] ) ? $settings['post_types'] : array();

		foreach ( $post_types as $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( ! $object || ! $object->public ) {
				continue;
			}

			$sections[] = array(
				'key'  => 'post-' . morn_sitemap_plus_provider_key( $post_type ),
				'type' => $post_type,
				'kind' => 'post_type',
			);
		}

		// 分类法。
		$taxonomies = is_array( $settings['taxonomies'] ) ? $settings['taxonomies'] : array();

		foreach ( $taxonomies as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );

			if ( ! $object || ! $object->public ) {
				continue;
			}

			$sections[] = array(
				'key'      => 'tax-' . morn_sitemap_plus_provider_key( $taxonomy ),
				'taxonomy' => $taxonomy,
				'kind'     => 'taxonomy',
			);
		}

		// 作者归档。
		if ( ! empty( $settings['include_authors'] ) ) {
			$sections[] = array(
				'key'  => 'authors',
				'kind' => 'authors',
			);
		}

		/**
		 * 过滤站点地图分片列表。
		 *
		 * @param array $sections 分片数组。
		 */
		$sections = apply_filters( 'morn_sitemap_plus_sections', $sections );

		$clean = array();

		foreach ( (array) $sections as $section ) {
			if ( empty( $section['key'] ) || empty( $section['kind'] ) ) {
				continue;
			}

			$section['key'] = sanitize_key( $section['key'] );
			$clean[]        = $section;

			if ( count( $clean ) >= 50 ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * 构建某个分片的 URL 条目。
	 *
	 * @param string $key       分片键名。
	 * @param int    $page      分页序号，从 1 开始。
	 * @param int    $per_page  每页条数。
	 * @return array
	 */
	public static function get_entries( $key, $page, $per_page ) {
		$sections = self::get_sections();
		$found    = null;

		foreach ( $sections as $section ) {
			if ( $section['key'] === $key ) {
				$found = $section;
				break;
			}
		}

		if ( null === $found ) {
			return array();
		}

		$offset = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;

		switch ( $found['kind'] ) {
			case 'post_type':
				$entries = self::get_post_entries( $found['type'], $offset, $per_page );
				break;

			case 'taxonomy':
				$entries = self::get_taxonomy_entries( $found['taxonomy'], $offset, $per_page );
				break;

			case 'authors':
				$entries = self::get_author_entries( $offset, $per_page );
				break;

			default:
				$entries = array();
				break;
		}

		/**
		 * 过滤分片内的条目。
		 *
		 * @param array  $entries 条目数组。
		 * @param string $key     分片键名。
		 * @param int    $page    分页序号。
		 */
		return apply_filters( 'morn_sitemap_plus_entries', $entries, $key, $page );
	}

	/**
	 * 统计某个分片的总条数。
	 *
	 * @param string $key 分片键名。
	 * @return int
	 */
	public static function count_entries( $key ) {
		$sections = self::get_sections();

		foreach ( $sections as $section ) {
			if ( $section['key'] !== $key ) {
				continue;
			}

			switch ( $section['kind'] ) {
				case 'post_type':
					return self::count_posts( $section['type'] );

				case 'taxonomy':
					return self::count_terms( $section['taxonomy'] );

				case 'authors':
					return self::count_authors();
			}
		}

		return 0;
	}

	/**
	 * 文章类型条目。
	 *
	 * @param string $post_type 文章类型。
	 * @param int    $offset    偏移量。
	 * @param int    $limit     数量限制。
	 * @return array
	 */
	private static function get_post_entries( $post_type, $offset, $limit ) {
		$excluded = morn_sitemap_plus_parse_ids( morn_sitemap_plus_get_setting( 'exclude_ids', '' ) );
		$settings = morn_sitemap_plus_get_settings();
		$entries  = array();

		// 排除项在查询阶段剔除，保证分页不会因跳过被排除内容而漏条目。
		$query_args = array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $limit,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'offset'                 => (int) $offset,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);

		if ( ! empty( $excluded ) ) {
			$query_args['post__not_in'] = $excluded;
		}

		$query = new WP_Query( $query_args );

		foreach ( $query->posts as $post ) {
			if ( ! morn_sitemap_plus_is_includable( $post->ID ) ) {
				continue;
			}

			$is_front = ( 'page' === $post_type && (int) get_option( 'page_on_front' ) === (int) $post->ID );
			$loc      = $is_front ? home_url( '/' ) : get_permalink( $post );

			if ( ! $loc ) {
				continue;
			}

			$entry = array(
				'loc'        => $loc,
				'lastmod'    => get_post_modified_time( 'c', true, $post ),
				'changefreq' => morn_sitemap_plus_calc_changefreq( $post->post_modified_gmt, $settings['changefreq'] ),
				'priority'   => self::get_post_priority( $post_type ),
			);

			if ( ! empty( $settings['include_images'] ) ) {
				$images = Morn_Sitemap_Plus_Images::get_images( $post->ID );

				if ( ! empty( $images ) ) {
					$entry['images'] = $images;
				}
			}

			$entries[] = $entry;
		}

		wp_reset_postdata();

		return $entries;
	}

	/**
	 * 计算文章优先级。
	 *
	 * @param string $post_type 文章类型。
	 * @return string
	 */
	private static function get_post_priority( $post_type ) {
		$settings = morn_sitemap_plus_get_settings();

		if ( 'page' === $post_type ) {
			$front = (int) get_option( 'page_on_front' );

			if ( $front > 0 ) {
				return $settings['priority_front'];
			}

			return $settings['priority_page'];
		}

		return $settings['priority_post'];
	}

	/**
	 * 分类法条目。
	 *
	 * @param string $taxonomy 分类法。
	 * @param int    $offset   偏移量。
	 * @param int    $limit    数量限制。
	 * @return array
	 */
	private static function get_taxonomy_entries( $taxonomy, $offset, $limit ) {
		$excluded = morn_sitemap_plus_parse_ids( morn_sitemap_plus_get_setting( 'exclude_terms', '' ) );
		$settings = morn_sitemap_plus_get_settings();

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => (int) $limit,
				'offset'     => (int) $offset,
				'orderby'    => 'name',
				'order'      => 'ASC',
				// 排除项在查询阶段剔除，避免分页错位与条目缺失。
				'exclude'    => ! empty( $excluded ) ? $excluded : array( 0 ),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$entries = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			// 双重保险：过滤器动态排除时仍不输出。
			if ( in_array( (int) $term->term_id, $excluded, true ) ) {
				continue;
			}

			$link = get_term_link( $term );

			if ( is_wp_error( $link ) || ! $link ) {
				continue;
			}

			$entries[] = array(
				'loc'        => $link,
				'lastmod'    => self::get_term_lastmod( $term ),
				'changefreq' => $settings['changefreq'],
				'priority'   => $settings['priority_archive'],
			);
		}

		return $entries;
	}

	/**
	 * 获取分类项的最新内容修改时间。
	 *
	 * @param WP_Term $term 分类项。
	 * @return string
	 */
	private static function get_term_lastmod( $term ) {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- 分类项数量有限。
					array(
						'taxonomy' => $term->taxonomy,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
			)
		);

		if ( ! empty( $posts ) ) {
			return get_post_modified_time( 'c', true, $posts[0] );
		}

		return gmdate( 'c' );
	}

	/**
	 * 作者归档条目。
	 *
	 * @param int $offset 偏移量。
	 * @param int $limit  数量限制。
	 * @return array
	 */
	private static function get_author_entries( $offset, $limit ) {
		$settings = morn_sitemap_plus_get_settings();

		$users = get_users(
			array(
				'number'  => (int) $limit,
				'offset'  => (int) $offset,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		$entries = array();

		foreach ( $users as $user ) {
			$user_id  = (int) $user->ID;
			$has_post = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'author'         => $user_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			// 没有任何已发布文章的作者不生成归档页条目。
			if ( empty( $has_post ) ) {
				continue;
			}

			$entries[] = array(
				'loc'        => get_author_posts_url( $user_id ),
				'lastmod'    => self::get_author_lastmod( $user_id ),
				'changefreq' => $settings['changefreq'],
				'priority'   => $settings['priority_archive'],
			);
		}

		return $entries;
	}

	/**
	 * 获取作者最新文章的修改时间。
	 *
	 * @param int $user_id 用户 ID。
	 * @return string
	 */
	private static function get_author_lastmod( $user_id ) {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'author'         => (int) $user_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( ! empty( $posts ) ) {
			return get_post_modified_time( 'c', true, $posts[0] );
		}

		return gmdate( 'c' );
	}

	/**
	 * 统计某文章类型的有效条目数。
	 *
	 * @param string $post_type 文章类型。
	 * @return int
	 */
	private static function count_posts( $post_type ) {
		$object = get_post_type_object( $post_type );

		if ( ! $object || ! $object->public ) {
			return 0;
		}

		$counts = wp_count_posts( $post_type );

		if ( ! isset( $counts->publish ) ) {
			return 0;
		}

		$total = (int) $counts->publish;

		// get_entries 会在查询阶段剔除排除项，这里同步扣减，
		// 否则索引页会多分片并产生 404 子站图。
		$excluded = morn_sitemap_plus_parse_ids( morn_sitemap_plus_get_setting( 'exclude_ids', '' ) );

		if ( ! empty( $excluded ) ) {
			$total = max( 0, $total - self::count_excluded_published( $post_type, $excluded ) );
		}

		return $total;
	}

	/**
	 * 统计给定 ID 中属于某文章类型且已发布的数量。
	 *
	 * @param string $post_type 文章类型。
	 * @param array  $excluded  排除的 ID 列表。
	 * @return int
	 */
	private static function count_excluded_published( $post_type, $excluded ) {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'post__in'               => $excluded,
				'posts_per_page'         => count( $excluded ),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$found = count( $query->posts );

		wp_reset_postdata();

		return $found;
	}

	/**
	 * 统计某分类法的有效条目数。
	 *
	 * @param string $taxonomy 分类法。
	 * @return int
	 */
	private static function count_terms( $taxonomy ) {
		$excluded = morn_sitemap_plus_parse_ids( morn_sitemap_plus_get_setting( 'exclude_terms', '' ) );

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'fields'     => 'ids',
			'exclude'    => ! empty( $excluded ) ? $excluded : array( 0 ),
		);

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return 0;
		}

		return count( $terms );
	}

	/**
	 * 统计有效作者数。
	 *
	 * @return int
	 */
	private static function count_authors() {
		$users = get_users( array( 'fields' => 'ID' ) );
		$count = 0;

		foreach ( $users as $user ) {
			$has_post = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'author'         => (int) $user->ID,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			if ( ! empty( $has_post ) ) {
				$count++;
			}
		}

		return $count;
	}
}
