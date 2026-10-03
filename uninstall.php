<?php
/**
 * 卸载清理脚本。
 *
 * 仅删除本插件自身创建的选项与定时任务。
 * 不删除任何文章、媒体附件或用户数据。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 插件设置与状态。
delete_option( 'morn_sitemap_plus_settings' );
delete_option( 'morn_sitemap_plus_last_ping' );

// 定时 ping 任务。
$morn_sm_cron = wp_next_scheduled( 'morn_sitemap_plus_ping' );

if ( $morn_sm_cron ) {
	wp_unschedule_event( $morn_sm_cron, 'morn_sitemap_plus_ping' );
}

wp_clear_scheduled_hook( 'morn_sitemap_plus_ping' );

// 移除本插件注册的 robots.txt 过滤器，避免站点残留无效引用。
global $wp_filter;

if ( isset( $wp_filter['robots_txt'] ) && is_object( $wp_filter['robots_txt'] ) ) {
	$callback = array( 'Morn_Sitemap_Plus_Robots', 'filter_robots_txt' );

	if ( isset( $wp_filter['robots_txt']->callbacks ) ) {
		foreach ( $wp_filter['robots_txt']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $key => $item ) {
				if ( isset( $item['function'][0], $item['function'][1] )
					&& is_string( $item['function'][0] )
					&& 'Morn_Sitemap_Plus_Robots' === $item['function'][0]
					&& 'filter_robots_txt' === $item['function'][1] ) {
					unset( $wp_filter['robots_txt']->callbacks[ $priority ][ $key ] );
				}
			}

			if ( empty( $wp_filter['robots_txt']->callbacks[ $priority ] ) ) {
				unset( $wp_filter['robots_txt']->callbacks[ $priority ] );
			}
		}
	}
}

// 清理重写规则。
$morn_sm_rewrite = get_option( 'rewrite_rules' );

if ( is_array( $morn_sm_rewrite ) ) {
	foreach ( $morn_sm_rewrite as $morn_sm_rule => $morn_sm_target ) {
		if ( false !== strpos( $morn_sm_target, 'morn_sitemap' ) ) {
			unset( $morn_sm_rewrite[ $morn_sm_rule ] );
		}
	}

	update_option( 'rewrite_rules', $morn_sm_rewrite );
}
