<?php
/**
 * 后台设置页。
 *
 * @package MornRain\MornSitemapPlus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 设置页控制器。
 */
class Morn_Sitemap_Plus_Admin {

	/**
	 * 设置分组名。
	 */
	const GROUP = 'morn_sitemap_plus_group';

	/**
	 * 菜单 slug。
	 */
	const PAGE = 'morn-sitemap-plus';

	/**
	 * 注册钩子。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
	}

	/**
	 * 注册菜单。
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_menu_page(
			__( '增强型站点地图', 'morn-sitemap-plus' ),
			__( '站点地图', 'morn-sitemap-plus' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-media-spreadsheet',
			61
		);
	}

	/**
	 * 注册设置项。
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP,
			MORN_SITEMAP_PLUS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section( 'sec_content', __( '内容类型', 'morn-sitemap-plus' ), array( __CLASS__, 'render_sec_content' ), self::PAGE );
		self::field( 'enabled', __( '启用站点地图', 'morn-sitemap-plus' ), 'checkbox', 'sec_content' );
		self::field( 'post_types', __( '包含的文章类型', 'morn-sitemap-plus' ), 'post_types', 'sec_content' );
		self::field( 'taxonomies', __( '包含的分类法', 'morn-sitemap-plus' ), 'taxonomies', 'sec_content' );
		self::field( 'include_authors', __( '包含作者归档页', 'morn-sitemap-plus' ), 'checkbox', 'sec_content' );
		self::field( 'exclude_ids', __( '排除的内容 ID（每行或逗号分隔）', 'morn-sitemap-plus' ), 'textarea', 'sec_content' );
		self::field( 'exclude_terms', __( '排除的分类项 ID（同上）', 'morn-sitemap-plus' ), 'textarea', 'sec_content' );

		add_settings_section( 'sec_output', __( '输出规则', 'morn-sitemap-plus' ), array( __CLASS__, 'render_sec_output' ), self::PAGE );
		self::field( 'per_page', __( '每个分片条目数', 'morn-sitemap-plus' ), 'number', 'sec_output' );
		self::field( 'include_lastmod', __( '输出 lastmod', 'morn-sitemap-plus' ), 'checkbox', 'sec_output' );
		self::field( 'changefreq', __( '基础更新频率', 'morn-sitemap-plus' ), 'select_freq', 'sec_output' );
		self::field( 'priority_front', __( '首页优先级', 'morn-sitemap-plus' ), 'number', 'sec_output' );
		self::field( 'priority_post', __( '文章优先级', 'morn-sitemap-plus' ), 'number', 'sec_output' );
		self::field( 'priority_page', __( '页面优先级', 'morn-sitemap-plus' ), 'number', 'sec_output' );
		self::field( 'priority_archive', __( '归档页优先级', 'morn-sitemap-plus' ), 'number', 'sec_output' );

		add_settings_section( 'sec_ext', __( '扩展功能', 'morn-sitemap-plus' ), array( __CLASS__, 'render_sec_ext' ), self::PAGE );
		self::field( 'include_images', __( '包含图片站点地图', 'morn-sitemap-plus' ), 'checkbox', 'sec_ext' );
		self::field( 'include_news', __( '启用 Google News 站点地图', 'morn-sitemap-plus' ), 'checkbox', 'sec_ext' );
		self::field( 'news_days', __( '新闻收录天数（最多 30 天）', 'morn-sitemap-plus' ), 'number', 'sec_ext' );
		self::field( 'http_cache', __( '发送 Last-Modified / ETag 头', 'morn-sitemap-plus' ), 'checkbox', 'sec_ext' );
		self::field( 'robots_enabled', __( '在 robots.txt 中声明 Sitemap', 'morn-sitemap-plus' ), 'checkbox', 'sec_ext' );

		add_settings_section( 'sec_ping', __( '搜索引擎通知', 'morn-sitemap-plus' ), array( __CLASS__, 'render_sec_ping' ), self::PAGE );
		self::field( 'auto_ping', __( '启用定时 ping', 'morn-sitemap-plus' ), 'checkbox', 'sec_ping' );
		self::field( 'ping_interval', __( 'ping 间隔（秒）', 'morn-sitemap-plus' ), 'number', 'sec_ping' );
	}

	/**
	 * 注册单个字段。
	 *
	 * @param string $key     设置键名。
	 * @param string $label   标签。
	 * @param string $type    控件类型。
	 * @param string $section 分组。
	 * @return void
	 */
	private static function field( $key, $label, $type, $section ) {
		add_settings_field(
			'morn_sm_' . $key,
			$label,
			array( __CLASS__, 'render_field' ),
			self::PAGE,
			$section,
			array(
				'key'  => $key,
				'type' => $type,
			)
		);
	}

	/**
	 * 校验设置。
	 *
	 * @param mixed $input 原始输入。
	 * @return array
	 */
	public static function sanitize( $input ) {
		$old   = morn_sitemap_plus_get_settings();
		$input = is_array( $input ) ? $input : array();
		$clean = $old;

		$checkboxes = array(
			'enabled',
			'include_authors',
			'include_lastmod',
			'include_images',
			'include_news',
			'http_cache',
			'robots_enabled',
			'auto_ping',
		);

		foreach ( $checkboxes as $key ) {
			$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		// 文章类型白名单。
		$allowed_types = array_keys( get_post_types( array( 'public' => true ), 'names' ) );
		unset( $allowed_types['attachment'] );

		$types = array();

		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $type ) {
				$type = sanitize_key( (string) $type );

				if ( in_array( $type, $allowed_types, true ) ) {
					$types[] = $type;
				}
			}
		}

		$clean['post_types'] = $types;

		// 分类法白名单。
		$allowed_taxonomies = array_keys( get_taxonomies( array( 'public' => true ), 'names' ) );
		$taxonomies         = array();

		if ( isset( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ) {
			foreach ( $input['taxonomies'] as $taxonomy ) {
				$taxonomy = sanitize_key( (string) $taxonomy );

				if ( in_array( $taxonomy, $allowed_taxonomies, true ) ) {
					$taxonomies[] = $taxonomy;
				}
			}
		}

		$clean['taxonomies'] = $taxonomies;

		$clean['per_page'] = isset( $input['per_page'] )
			? max( 1, min( 50000, absint( $input['per_page'] ) ) )
			: 500;

		$clean['news_days'] = isset( $input['news_days'] )
			? max( 1, min( 30, absint( $input['news_days'] ) ) )
			: 30;

		$clean['ping_interval'] = isset( $input['ping_interval'] )
			? max( 3600, min( 2592000, absint( $input['ping_interval'] ) ) )
			: 86400;

		$freqs = array( 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' );
		$clean['changefreq'] = isset( $input['changefreq'] ) && in_array( $input['changefreq'], $freqs, true )
			? $input['changefreq']
			: 'weekly';

		$priorities = array( 'priority_front', 'priority_post', 'priority_page', 'priority_archive' );

		foreach ( $priorities as $key ) {
			$value = isset( $input[ $key ] ) ? (float) $input[ $key ] : (float) $old[ $key ];
			$clean[ $key ] = number_format( max( 0.0, min( 1.0, $value ) ), 1 );
		}

		foreach ( array( 'exclude_ids', 'exclude_terms' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$ids    = morn_sitemap_plus_parse_ids( $input[ $key ] );
				$ids    = array_slice( array_unique( $ids ), 0, 2000 );
				$clean[ $key ] = implode( "\n", array_map( 'strval', $ids ) );
			}
		}

		return $clean;
	}

	/**
	 * 渲染设置页。
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '您没有权限访问此页面。', 'morn-sitemap-plus' ) );
		}

		$settings = morn_sitemap_plus_get_settings();
		$sections = Morn_Sitemap_Plus_Providers::get_sections();
		$per_page = max( 1, (int) $settings['per_page'] );
		?>
		<div class="wrap morn-sm-wrap">
			<h1><?php echo esc_html__( '增强型站点地图', 'morn-sitemap-plus' ); ?></h1>

			<div class="morn-sm-toolbar">
				<div class="morn-sm-urls">
					<p>
						<strong><?php echo esc_html__( '站点地图索引：', 'morn-sitemap-plus' ); ?></strong>
						<a href="<?php echo esc_url( morn_sitemap_plus_url( 'sitemap.xml' ) ); ?>" target="_blank" rel="noopener">
							<code><?php echo esc_html( morn_sitemap_plus_url( 'sitemap.xml' ) ); ?></code>
						</a>
					</p>
					<?php if ( ! empty( $settings['include_news'] ) ) : ?>
						<p>
							<strong><?php echo esc_html__( '新闻站点地图：', 'morn-sitemap-plus' ); ?></strong>
							<a href="<?php echo esc_url( morn_sitemap_plus_url( 'news-sitemap.xml' ) ); ?>" target="_blank" rel="noopener">
								<code><?php echo esc_html( morn_sitemap_plus_url( 'news-sitemap.xml' ) ); ?></code>
							</a>
						</p>
					<?php endif; ?>
				</div>

				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'morn_sitemap_plus_ping', admin_url( 'admin-post.php' ) ), 'morn_sitemap_plus_ping' ) ); ?>">
					<?php echo esc_html__( '立即 ping 搜索引擎', 'morn-sitemap-plus' ); ?>
				</a>
			</div>

			<?php if ( ! empty( $sections ) ) : ?>
				<h2><?php echo esc_html__( '已生成的分片', 'morn-sitemap-plus' ); ?></h2>
				<table class="widefat striped morn-sm-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( '类型', 'morn-sitemap-plus' ); ?></th>
							<th><?php echo esc_html__( '条目数', 'morn-sitemap-plus' ); ?></th>
							<th><?php echo esc_html__( '分片数', 'morn-sitemap-plus' ); ?></th>
							<th><?php echo esc_html__( '地址', 'morn-sitemap-plus' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $sections as $section ) :
							$total = Morn_Sitemap_Plus_Providers::count_entries( $section['key'] );
							$pages = max( 1, (int) ceil( $total / $per_page ) );
							$label = self::get_section_label( $section );
							?>
							<tr>
								<td><?php echo esc_html( $label ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $total ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $pages ) ); ?></td>
								<td>
									<?php if ( $total > 0 ) : ?>
										<a href="<?php echo esc_url( morn_sitemap_plus_url( 'sitemap-' . $section['key'] . '.xml' ) ); ?>" target="_blank" rel="noopener">
											<code>sitemap-<?php echo esc_html( $section['key'] ); ?>.xml</code>
										</a>
										<?php if ( $pages > 1 ) : ?>
											<span class="description">
												<?php
												printf(
													/* translators: %d: 分片数量。 */
													esc_html__( '共 %d 个分片', 'morn-sitemap-plus' ),
													(int) $pages
												);
												?>
											</span>
										<?php endif; ?>
									<?php else : ?>
										<span class="description"><?php echo esc_html__( '暂无可输出条目', 'morn-sitemap-plus' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * 分片类型的中文标签。
	 *
	 * @param array $section 分片信息。
	 * @return string
	 */
	private static function get_section_label( $section ) {
		if ( 'authors' === $section['kind'] ) {
			return __( '作者归档', 'morn-sitemap-plus' );
		}

		if ( 'taxonomy' === $section['kind'] ) {
			$object = get_taxonomy( $section['taxonomy'] );

			return $object ? $object->labels->name : $section['taxonomy'];
		}

		$object = get_post_type_object( $section['type'] );

		return $object ? $object->labels->name : $section['type'];
	}

	/**
	 * 内容类型分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_content() {
		echo '<p class="description">' . esc_html__( '草稿、待发布、密码保护与被排除的内容不会出现在站点地图中。', 'morn-sitemap-plus' ) . '</p>';
	}

	/**
	 * 输出规则分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_output() {
		echo '<p class="description">' . esc_html__( '每个分片条目数建议不超过 50000（协议上限）。changefreq 与 priority 多数搜索引擎已不再使用，但保留有助于其它爬虫判断更新频率。', 'morn-sitemap-plus' ) . '</p>';
	}

	/**
	 * 扩展功能分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_ext() {
		echo '<p class="description">' . esc_html__( '图片扩展取特色图与正文前 10 张图片。Google News 站点地图仅收录最近 30 天内的文章，这是 Google 的硬性要求。', 'morn-sitemap-plus' ) . '</p>';
	}

	/**
	 * ping 分组说明。
	 *
	 * @return void
	 */
	public static function render_sec_ping() {
		$last = Morn_Sitemap_Plus_Notifier::get_last_ping();

		echo '<p class="description">';
		if ( $last > 0 ) {
			printf(
				/* translators: %s: 上次 ping 时间。 */
				esc_html__( '上次 ping：%s。', 'morn-sitemap-plus' ),
				esc_html( date_i18n( 'Y-m-d H:i:s', $last + ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) )
			);
		} else {
			echo esc_html__( '尚未执行过 ping。', 'morn-sitemap-plus' );
		}
		echo ' ';
		echo esc_html__( 'ping 端点为搜索引擎公开的官方接口，WP-Cron 在访问量低时可能延迟触发。', 'morn-sitemap-plus' );
		echo '</p>';
	}

	/**
	 * 渲染字段控件。
	 *
	 * @param array $args 字段参数。
	 * @return void
	 */
	public static function render_field( $args ) {
		$settings = morn_sitemap_plus_get_settings();
		$key      = $args['key'];
		$type     = $args['type'];
		$name     = MORN_SITEMAP_PLUS_OPTION . '[' . $key . ']';
		$id       = 'morn-sm-' . $key;
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

		switch ( $type ) {
			case 'checkbox':
				?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( 1, (int) $value ); ?> />
					<?php echo esc_html__( '启用', 'morn-sitemap-plus' ); ?>
				</label>
				<?php
				break;

			case 'post_types':
				$selected = is_array( $value ) ? $value : array();
				$types    = get_post_types( array( 'public' => true ), 'objects' );
				unset( $types['attachment'] );
				?>
				<fieldset class="morn-sm-checks">
					<?php foreach ( $types as $slug => $obj ) : ?>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected, true ) ); ?> />
							<?php echo esc_html( $obj->labels->name ); ?>
						</label><br />
					<?php endforeach; ?>
				</fieldset>
				<?php
				break;

			case 'taxonomies':
				$selected = is_array( $value ) ? $value : array();
				$taxes    = get_taxonomies( array( 'public' => true ), 'objects' );
				?>
				<fieldset class="morn-sm-checks">
					<?php foreach ( $taxes as $slug => $obj ) : ?>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected, true ) ); ?> />
							<?php echo esc_html( $obj->labels->name ); ?>
						</label><br />
					<?php endforeach; ?>
				</fieldset>
				<?php
				break;

			case 'select_freq':
				$freqs = array(
					'always'  => __( 'always（每次抓取）', 'morn-sitemap-plus' ),
					'hourly'   => __( 'hourly（每小时）', 'morn-sitemap-plus' ),
					'daily'    => __( 'daily（每天）', 'morn-sitemap-plus' ),
					'weekly'   => __( 'weekly（每周）', 'morn-sitemap-plus' ),
					'monthly'  => __( 'monthly（每月）', 'morn-sitemap-plus' ),
					'yearly'   => __( 'yearly（每年）', 'morn-sitemap-plus' ),
					'never'   => __( 'never（不再变化）', 'morn-sitemap-plus' ),
				);
				?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( $freqs as $key_value => $label ) : ?>
						<option value="<?php echo esc_attr( $key_value ); ?>" <?php selected( $key_value, (string) $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php
				break;

			case 'textarea':
				?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="5" class="large-text code"><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php
				break;

			case 'number':
				$step = in_array( $key, array( 'priority_front', 'priority_post', 'priority_page', 'priority_archive' ), true ) ? '0.1' : '1';
				?>
				<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" step="<?php echo esc_attr( $step ); ?>" class="small-text" />
				<?php
				break;

			default:
				?>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" class="regular-text" />
				<?php
				break;
		}
	}

	/**
	 * 加载后台资源。
	 *
	 * @param string $hook 当前后台页。
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'morn-sitemap-plus-admin',
			MORN_SITEMAP_PLUS_URL . 'assets/css/admin.css',
			array(),
			MORN_SITEMAP_PLUS_VERSION
		);
	}

	/**
	 * 输出提示信息。
	 *
	 * @return void
	 */
	public static function render_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'toplevel_page_' . self::PAGE !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 仅用于显示提示。
		if ( isset( $_GET['morn_pinged'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: 已通知的搜索引擎数量。 */
						__( '已向 %d 个搜索引擎发送 ping 通知。', 'morn-sitemap-plus' ),
						absint( $_GET['morn_pinged'] )
					)
				)
			);
		}

		$settings = morn_sitemap_plus_get_settings();
		$has_types = ! empty( $settings['post_types'] );

		if ( ! $has_types ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( '您没有勾选任何文章类型，站点地图只会输出分类与作者归档页。', 'morn-sitemap-plus' );
			echo '</p></div>';
		}
	}
}
