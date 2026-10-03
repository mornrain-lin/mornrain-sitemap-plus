<?php
/**
 * 图片站点地图扩展。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 提取文章的特色图与正文首图，输出 image:image 节点数据。
 */
class Morn_Sitemap_Plus_Images {

	/**
	 * 单篇文章最多输出的图片数。
	 */
	const MAX_IMAGES = 10;

	/**
	 * 获取文章的图片列表。
	 *
	 * @param int $post_id 文章 ID。
	 * @return array
	 */
	public static function get_images( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$images = array();

		// 1. 特色图。
		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );

		if ( $thumbnail_id ) {
			$image = self::build_image( $thumbnail_id );

			if ( $image ) {
				$images[ $thumbnail_id ] = $image;
			}
		}

		// 2. 正文图片。
		$content_images = self::extract_content_images( $post->post_content );

		foreach ( $content_images as $attachment_id ) {
			if ( count( $images ) >= self::MAX_IMAGES ) {
				break;
			}

			// 同 ID 去重（同 URL 的不同尺寸也归并处理）。
			if ( isset( $images[ $attachment_id ] ) ) {
				continue;
			}

			$image = self::build_image( $attachment_id );

			if ( $image ) {
				$images[ $attachment_id ] = $image;
			}
		}

		$images = array_values( $images );

		/**
		 * 过滤文章的图片列表。
		 *
		 * @param array $images  图片数组。
		 * @param int   $post_id 文章 ID。
		 */
		return apply_filters( 'morn_sitemap_plus_images', $images, $post_id );
	}

	/**
	 * 从正文 HTML 中提取图片的附件 ID。
	 *
	 * @param string $content 正文内容。
	 * @return array 附件 ID 数组。
	 */
	private static function extract_content_images( $content ) {
		if ( '' === trim( (string) $content ) ) {
			return array();
		}

		$ids = array();

		// 匹配 wp-image-{id} 类名，这是 WordPress 插入图片时最可靠的标记。
		if ( preg_match_all( '/wp-image-(\d+)/', (string) $content, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$ids[] = absint( $id );
			}
		}

		if ( count( $ids ) >= self::MAX_IMAGES ) {
			return array_slice( array_unique( $ids ), 0, self::MAX_IMAGES );
		}

		// 回退：匹配 img 标签的 src 与 srcset，尝试解析为附件 ID。
		if ( preg_match_all( '/<img\b[^>]*>/i', (string) $content, $tags ) ) {
			foreach ( $tags[0] as $tag ) {
				if ( count( $ids ) >= self::MAX_IMAGES ) {
					break;
				}

				$urls = array();

				if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $m ) ) {
					$urls[] = $m[1];
				}

				if ( preg_match( '/\bsrcset\s*=\s*["\']([^"\']+)["\']/i', $tag, $m ) ) {
					foreach ( explode( ',', $m[1] ) as $candidate ) {
						$parts = trim( $candidate );

						if ( '' !== $parts ) {
							$urls[] = preg_split( '/\s+/', $parts )[0];
						}
					}
				}

				foreach ( $urls as $url ) {
					$id = attachment_url_to_postid( $url );

					if ( $id ) {
						$ids[] = (int) $id;
						break;
					}
				}
			}
		}

		return array_unique( $ids );
	}

	/**
	 * 构造单个图片节点数据。
	 *
	 * @param int $attachment_id 附件 ID。
	 * @return array 空数组表示不可用。
	 */
	private static function build_image( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return array();
		}

		$post = get_post( $attachment_id );

		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || 'inherit' !== $post->post_status ) {
			return array();
		}

		if ( wp_attachment_is_image( $attachment_id ) !== true ) {
			return array();
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'full' );

		if ( ! $url ) {
			return array();
		}

		$image = array(
			'loc' => $url,
		);

		$meta = wp_get_attachment_metadata( $attachment_id );

		if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			$image['width']  = (int) $meta['width'];
			$image['height'] = (int) $meta['height'];
		}

		$title = get_the_title( $attachment_id );

		if ( '' !== trim( (string) $title ) ) {
			$image['title'] = $title;
		}

		$caption = $post->post_excerpt;

		if ( '' !== trim( (string) $caption ) ) {
			$image['caption'] = wp_strip_all_tags( $caption );
		}

		// 版权信息：优先用 alt 文本，缺失时用标题。
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		if ( ! is_string( $alt ) || '' === trim( $alt ) ) {
			$alt = $title;
		}

		if ( is_string( $alt ) && '' !== trim( $alt ) ) {
			$image['license'] = wp_strip_all_tags( $alt );
		}

		// 允许通过过滤器补充自定义图片信息。
		/**
		 * 过滤单个图片节点数据。
		 *
		 * @param array $image         图片数据。
		 * @param int   $attachment_id 附件 ID。
		 */
		return apply_filters( 'morn_sitemap_plus_image_data', $image, $attachment_id );
	}
}
