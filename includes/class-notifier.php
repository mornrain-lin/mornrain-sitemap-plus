<?php
/**
 * 搜索引擎 ping 通知。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 通过 ping URL 通知搜索引擎站点地图已更新。
 */
class Morn_Sitemap_Plus_Notifier {

	/**
	 * 定时任务钩子名。
	 */
	const CRON_HOOK = 'morn_sitemap_plus_ping';

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_interval' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled' ) );
		add_action( 'save_post', array( __CLASS__, 'on_content_change' ), 20 );
		add_action( 'admin_post_morn_sitemap_plus_ping', array( __CLASS__, 'handle_manual' ) );
	}

	/**
	 * 注册自定义 cron 间隔。
	 *
	 * @param array $schedules 现有间隔。
	 * @return array
	 */
	public static function register_interval( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			return $schedules;
		}

		$interval = max( 3600, (int) morn_sitemap_plus_get_setting( 'ping_interval', 86400 ) );

		$schedules['morn_sitemap_plus_ping_interval'] = array(
			'interval' => $interval,
			'display'  => sprintf(
				/* translators: %d: 间隔秒数。 */
				__( '站点地图 ping 间隔（%d 秒）', 'morn-sitemap-plus' ),
				$interval
			),
		);

		return $schedules;
	}

	/**
	 * 注册定时任务。
	 *
	 * @return void
	 */
	public static function maybe_schedule() {
		$settings = morn_sitemap_plus_get_settings();

		if ( empty( $settings['auto_ping'] ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );

			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'morn_sitemap_plus_ping_interval', self::CRON_HOOK );
		}
	}

	/**
	 * 定时任务入口。
	 *
	 * @return void
	 */
	public static function run_scheduled() {
		$settings = morn_sitemap_plus_get_settings();

		if ( empty( $settings['auto_ping'] ) ) {
			return;
		}

		$interval = max( 3600, (int) $settings['ping_interval'] );
		$last     = (int) get_option( 'morn_sitemap_plus_last_ping', 0 );

		// 避免频繁请求外部服务。
		if ( $last > 0 && ( time() - $last ) < $interval ) {
			return;
		}

		self::ping();
	}

	/**
	 * 内容变更时重置 ping 时间戳。
	 *
	 * @return void
	 */
	public static function on_content_change() {
		$settings = morn_sitemap_plus_get_settings();

		if ( empty( $settings['auto_ping'] ) ) {
			return;
		}

		// 只记录变更，实际 ping 交给定时任务执行，避免每次保存都发外部请求。
		delete_option( 'morn_sitemap_plus_last_ping' );
	}

	/**
	 * 向搜索引擎发送 ping。
	 *
	 * @return array
	 */
	public static function ping() {
		$settings = morn_sitemap_plus_get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return array();
		}

		$url      = morn_sitemap_plus_url( 'sitemap.xml' );
		$endpoints = self::get_endpoints();

		/**
		 * 过滤 ping 端点列表。
		 *
		 * @param array $endpoints 端点 URL 数组。
		 * @param string $url      站点地图 URL。
		 */
		$endpoints = apply_filters( 'morn_sitemap_plus_ping_endpoints', $endpoints, $url );

		$results = array();

		foreach ( $endpoints as $endpoint ) {
			// 端点已带 `?sitemap=`，add_query_arg 会覆盖该参数并完成编码，
			// 此处不可再 rawurlencode，否则会双重编码。
			$target = add_query_arg( 'sitemap', $url, $endpoint );
			$result = self::request( $target );

			$results[ $endpoint ] = $result;
		}

		update_option( 'morn_sitemap_plus_last_ping', time(), false );

		/**
		 * ping 执行完成后的动作。
		 *
		 * @param array  $results 各端点结果。
		 * @param string $url     站点地图 URL。
		 */
		do_action( 'morn_sitemap_plus_pinged', $results, $url );

		return $results;
	}

	/**
	 * 获取 ping 端点列表。
	 *
	 * @return array
	 */
	public static function get_endpoints() {
		// 这些是搜索引擎公开的站点地图通知端点。
		return array(
			'https://www.google.com/ping?sitemap=',
			'https://www.bing.com/ping?sitemap=',
		);
	}

	/**
	 * 发起 ping 请求。
	 *
	 * 使用 WordPress HTTP API，禁止超时超过 5 秒，避免阻塞定时任务。
	 *
	 * @param string $url 完整请求 URL。
	 * @return array
	 */
	private static function request( $url ) {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return array(
				'success' => false,
				'code'    => 0,
				'message' => 'HTTP API unavailable',
			);
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 2,
				'sslverify'   => true,
				'user-agent'  => 'MornSitemapPlus/' . MORN_SITEMAP_PLUS_VERSION . '; ' . home_url(),
				'headers'     => array(
					'Accept' => '*/*',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'code'    => 0,
				'message' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return array(
			'success' => ( $code >= 200 && $code < 300 ),
			'code'    => $code,
			'message' => '',
		);
	}

	/**
	 * 处理手动 ping 请求。
	 *
	 * @return void
	 */
	public static function handle_manual() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '您没有权限执行此操作。', 'morn-sitemap-plus' ) );
		}

		check_admin_referer( 'morn_sitemap_plus_ping' );

		$results = self::ping();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => Morn_Sitemap_Plus_Admin::PAGE,
					'morn_pinged' => count( $results ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * 获取上次 ping 时间。
	 *
	 * @return int
	 */
	public static function get_last_ping() {
		return (int) get_option( 'morn_sitemap_plus_last_ping', 0 );
	}
}
