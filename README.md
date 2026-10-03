# Morn Sitemap Plus

增强型站点地图插件。生成符合 sitemaps.org 0.9 规范的独立 XML 站点地图与自动分片子站图，支持图片站点地图与 Google News 站点地图扩展，带 HTTP 条件请求（304）与搜索引擎 ping 通知。

**不与 WordPress 内置的 `wp-sitemap.xml` 冲突**，使用完全独立的 URL。

- 版本：1.0.0
- 需要 WordPress：6.0+
- 需要 PHP：7.4+
- 测试至：WordPress 6.6
- 许可证：MIT

## 特性

- **独立 URL**：`/sitemap.xml`（索引）+ `/sitemap-{key}.xml`（子站图）+ `/news-sitemap.xml`（新闻），与核心的 `wp-sitemap.xml` 完全并存。
- **自动分片**：条目超过设定容量时自动拆分为多个子站图，页码后缀递增。
- **多类型支持**：任意公开文章类型、任意公开分类法、作者归档。
- **规范输出**：正确的 `standalone="yes"` 声明与 namespace，含 `lastmod`、`changefreq`、`priority`。
- **智能 changefreq**：按内容最后修改时间距今天数动态计算，而非全站统一值。
- **图片扩展**：取特色图与正文图片，输出完整 `image:image` 节点。
- **Google News**：符合 Google News 0.9 规范，自动限制 30 天内内容。
- **HTTP 缓存**：发送 `Last-Modified` 与 `ETag`，命中时返回 304，节省带宽。
- **自动声明**：robots.txt 追加 Sitemap 行，`<head>` 输出 `link rel="sitemap"`。
- **定时 ping**：可开关的 Google/Bing 站点地图通知。
- **排除规则**：按内容 ID 与分类项 ID 精确排除。

## 安装

1. 将 `morn-sitemap-plus` 目录上传到 `wp-content/plugins/`。
2. 在后台「插件」中启用「Morn Sitemap Plus」。
3. 进入「站点地图 → 增强型站点地图」选择要包含的内容类型。
4. 访问 `https://你的域名/sitemap.xml` 验证输出。

若重写规则未生效，到「设置 → 固定链接」点一次「保存」。

nginx 用户可手动加入：

```nginx
location ~ ^/(news-)?sitemap([-a-z0-9]*)?\.xml$ {
    rewrite ^/(news-)?sitemap([-a-z0-9]*)?\.xml$ /index.php?morn_sitemap=$1$2 last;
}
```

> 插件内部实际注册的是三条独立规则（`sitemap.xml`、`sitemap-{key}[-{页码}].xml`、
> `news-sitemap.xml`），分别对应 `morn_sitemap_index`、`morn_sitemap_child`、
> `morn_sitemap_news` 三个查询变量。上面的 nginx 规则只是等价的合并写法。

## 地址一览

| 地址 | 内容 |
| --- | --- |
| `/sitemap.xml` | 站点地图索引，列出所有子站图 |
| `/sitemap-post.xml` | 文章（post 类型）子站图 |
| `/sitemap-page.xml` | 页面（page 类型）子站图 |
| `/sitemap-tax-category.xml` | 分类归档子站图 |
| `/sitemap-tax-post_tag.xml` | 标签归档子站图 |
| `/sitemap-authors.xml` | 作者归档子站图 |
| `/sitemap-post-2.xml` | 文章子站图第 2 页（超出容量时出现） |
| `/news-sitemap.xml` | Google News 站点地图 |

**分片键名规则**

| 内容类型 | 键名 | 地址 |
| --- | --- | --- |
| 文章类型 `{type}` | `post-{type}` | `/sitemap-post-{type}.xml` |
| 分类法 `{taxonomy}` | `tax-{taxonomy}` | `/sitemap-tax-{taxonomy}.xml` |
| 作者 | `authors` | `/sitemap-authors.xml` |

例如自定义文章类型 `portfolio` 的键名是 `post-portfolio`，地址为 `/sitemap-post-portfolio.xml`。

## 配置说明

后台路径：**站点地图 → 增强型站点地图**（顶层菜单，`manage_options` 权限）。

设置页上方工具栏显示索引与新闻站图地址，并提供「立即 ping 搜索引擎」按钮。下方表格列出每个分片的条目数、分片数与可点击的预览地址。

### 内容类型

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 启用站点地图 | 启用 | 总开关。关闭后所有地址返回 404。 |
| 包含的文章类型 | `post`, `page` | 可勾选任意公开文章类型。附件类型不可选。 |
| 包含的分类法 | `category`, `post_tag` | 可勾选任意公开分类法。 |
| 包含作者归档页 | 启用 | 无已发布文章的作者会被跳过。 |
| 排除的内容 ID | 空 | 每行一个或用逗号分隔。最多 2000 个。 |
| 排除的分类项 ID | 空 | 同上。 |

**自动排除的内容**（无需配置）

草稿、待发布、私有、回收站、密码保护的文章，以及状态非 `publish` 的任何内容。

> 排除 ID 在**查询阶段**就通过 `post__not_in` 剔除，因此分页不会因为跳过被排除内容而漏掉条目。

### 输出规则

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 每个分片条目数 | `500` | 超过即自动分片。范围 1~50000（协议上限 50000）。 |
| 输出 lastmod | 启用 | 关闭后不含 `lastmod` 节点，会损失索引质量提示。**建议保持开启。** |
| 基础更新频率 | weekly | 分类与作者归档页使用此值。 |
| 首页优先级 | `1.0` | |
| 文章优先级 | `0.8` | |
| 页面优先级 | `0.6` | |
| 归档页优先级 | `0.4` | |

优先级范围均为 `0.0`~`1.0`。

**changefreq 动态计算规则**（文章与页面）

按最后修改时间距今天数：

| 距今 | changefreq |
| --- | --- |
| ≤ 7 天 | `daily` |
| ≤ 30 天 | `weekly` |
| ≤ 180 天 | `monthly` |
| > 180 天 | `yearly` |

分类与作者归档页使用配置的「基础更新频率」。

> **关于 priority 与 changefreq**：Google 已明确表示不使用这两个字段。保留它们是为了兼容其它搜索引擎与爬虫工具（如部分 RSS 阅读器、 archiving 工具），设置合理值即可。

### 扩展功能

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 包含图片站点地图 | 启用 | 取特色图 + 正文前 10 张图。 |
| 启用 Google News 站点地图 | 启用 | 关闭后 `/news-sitemap.xml` 返回 404。 |
| 新闻收录天数（最多 30 天） | `30` | Google 硬性要求 30 天，配置超出时按 30 天封顶。 |
| 发送 Last-Modified / ETag 头 | 启用 | 关闭后不再支持 304 响应。 |
| 在 robots.txt 中声明 Sitemap | 启用 | 仅在 `blog_public` 为 1 时输出。 |

**图片提取逻辑**

1. 优先取特色图（`wp_get_attachment_image_url` + 附件元数据获取宽高）
2. 扫描正文 HTML，优先识别 `wp-image-{id}` 类名（WordPress 插入图片时的标准标记）
3. 回退：解析 `<img>` 的 `src` 与 `srcset`，用 `attachment_url_to_postid()` 反查附件 ID
4. 同一附件 ID 自动去重，最多 10 张

输出字段：`image:loc`、`image:caption`、`image:title`、`image:license`、`image:width`、`image:height`。

> `image:license` 优先取图片的 alt 文本（`_wp_attachment_image_alt`），这是 Google 推荐的用法——用作版权与授权声明。缺失时回退到附件标题。

### 搜索引擎通知

| 设置项 | 默认值 | 说明 |
| --- | --- | --- |
| 启用定时 ping | **关闭** | 通过 WP-Cron 定时通知搜索引擎。 |
| ping 间隔（秒） | `86400` | 范围 3600~2592000（30 天）。 |

**ping 端点**

```text
https://www.google.com/ping?sitemap={url}
https://www.bing.com/ping?sitemap={url}
```

这两个都是搜索引擎公开的官方接口。端点列表可通过 `morn_sitemap_plus_ping_endpoints` 过滤器增删。

**ping 触发时机**

- 手动：设置页「立即 ping 搜索引擎」按钮（带 nonce 校验）
- 定时：WP-Cron 按设定间隔执行

内容保存时**只清除时间戳**，不立即发请求——这样编辑文章不会触发大量外部请求，真正的 ping 交给定时任务。

> **关于 WP-Cron**：它依赖访问量触发。访问量低的站点，定时任务可能延迟数小时到一天。这是 WordPress 的已知限制，可改用系统 cron 调用 `wp-cron.php`。

## HTTP 缓存与 304

站点地图内容是静态的，适合条件请求：

```text
响应头：
  Content-Type: application/xml; charset=UTF-8
  Cache-Control: public, max-age=3600
  Last-Modified: Mon, 02 Oct 2026 10:00:00 GMT
  ETag: "a1b2c3d4e5f6..."
  X-Robots-Tag: noindex, follow
```

**条件请求处理逻辑**

1. 若请求带 `If-None-Match`：比较 ETag（支持多值逗号分隔、支持 `W/` 弱比较前缀、支持 `*` 匹配）。命中则返回 304。**存在 `If-None-Match` 时不再看 `If-Modified-Since`**（符合 HTTP 规范优先级）。
2. 否则若带 `If-Modified-Since`：比较时间，客户端时间 ≥ 服务器时间则返回 304。
3. 304 响应不输出响应体，只发状态码。

`Last-Modified` 的值不是简单用当前时间，而是**解析 XML 中全部 `<lastmod>` 节点取最大值**推导，这样条件请求才真正有意义。

## Hook 列表

### 动作（do_action）

| Hook | 回调 | 优先级 | 用途 |
| --- | --- | --- | --- |
| `plugins_loaded` | `morn_sitemap_plus_boot` | 10 | 加载文本域并启动插件。 |
| `init` | `Morn_Sitemap_Plus_Sitemap::register_rewrites` | 10 | 注册三条重写规则。 |
| `init` | `Morn_Sitemap_Plus_Notifier::maybe_schedule` | 10 | 注册/清理 ping 定时任务。 |
| `cron_schedules` | `Morn_Sitemap_Plus_Notifier::register_interval` | 10 | 注册 `morn_sitemap_plus_ping_interval` 自定义间隔。 |
| `template_redirect` | `Morn_Sitemap_Plus_Sitemap::maybe_render` | 0 | 输出站点地图并终止请求。 |
| `wp_head` | `Morn_Sitemap_Plus_Plugin::output_link` | 1 | 输出 `link rel="sitemap"`。 |
| `wp_head` | — | 1 | 可通过过滤器追加自定义声明标签。 |
| `save_post` | `Morn_Sitemap_Plus_Notifier::on_content_change` | 20 | 重置 ping 时间戳。 |
| `morn_sitemap_plus_ping` | `Morn_Sitemap_Plus_Notifier::run_scheduled` | — | 定时 ping 入口。 |
| `morn_sitemap_plus_pinged` | — | — | **ping 完成后触发**，`do_action( 'morn_sitemap_plus_pinged', array $results, string $url )`。 |
| `admin_post_morn_sitemap_plus_ping` | `Morn_Sitemap_Plus_Notifier::handle_manual` | — | 手动 ping。 |

### 过滤器（apply_filters）

| Hook | 签名 | 说明 |
| --- | --- | --- |
| `morn_sitemap_plus_settings` | `apply_filters( 'morn_sitemap_plus_settings', array $settings )` | 过滤合并默认值后的设置。 |
| `morn_sitemap_plus_sections` | `apply_filters( 'morn_sitemap_plus_sections', array $sections )` | 增删分片（追加自定义内容源）。 |
| `morn_sitemap_plus_entries` | `apply_filters( 'morn_sitemap_plus_entries', array $entries, string $key, int $page )` | 过滤分片内的条目。 |
| `morn_sitemap_plus_include_post` | `apply_filters( 'morn_sitemap_plus_include_post', bool $include, WP_Post $post, int $post_id )` | 控制单篇内容是否入图。**可用于对接自定义 noindex 插件。** |
| `morn_sitemap_plus_images` | `apply_filters( 'morn_sitemap_plus_images', array $images, int $post_id )` | 过滤文章图片列表。 |
| `morn_sitemap_plus_image_data` | `apply_filters( 'morn_sitemap_plus_image_data', array $image, int $attachment_id )` | 过滤单个图片节点数据。 |
| `morn_sitemap_plus_news_item` | `apply_filters( 'morn_sitemap_plus_news_item', array $item, WP_Post $post )` | 过滤单条新闻数据。 |
| `morn_sitemap_plus_news_xml` | `apply_filters( 'morn_sitemap_plus_news_xml', string $xml, array $items )` | 过滤新闻站图 XML。 |
| `morn_sitemap_plus_index_xml` | `apply_filters( 'morn_sitemap_plus_index_xml', string $xml )` | 过滤索引页 XML。 |
| `morn_sitemap_plus_child_xml` | `apply_filters( 'morn_sitemap_plus_child_xml', string $xml, string $key, int $page )` | 过滤子站图 XML。 |
| `morn_sitemap_plus_robots_lines` | `apply_filters( 'morn_sitemap_plus_robots_lines', array $lines, string $output, bool $public )` | 追加 robots.txt 指令行。 |
| `morn_sitemap_plus_ping_endpoints` | `apply_filters( 'morn_sitemap_plus_ping_endpoints', array $endpoints, string $url )` | 过滤 ping 端点列表。 |

### 使用示例

**对接 SEO 插件的 noindex 设置**

让本插件与其它 SEO 插件的 noindex 规则保持一致：

```php
add_filter( 'morn_sitemap_plus_include_post', function ( $include, $post, $post_id ) {
    // 你的 SEO 插件存储 noindex 的元键。
    if ( get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) === '1' ) {
        return false;
    }

    return $include;
}, 10, 3 );
```

**添加自定义内容源**

```php
add_filter( 'morn_sitemap_plus_sections', function ( $sections ) {
    $sections[] = array(
        'key'  => 'my-products',
        'kind' => 'post_type',
        'type' => 'product',
    );

    return $sections;
} );
```

**排除特定文章类型的所有内容**

```php
add_filter( 'morn_sitemap_plus_sections', function ( $sections ) {
    return array_values(
        array_filter( $sections, function ( $section ) {
            return 'post' !== ( $section['type'] ?? '' );
        } )
    );
} );
```

**为图片补充版权信息**

```php
add_filter( 'morn_sitemap_plus_image_data', function ( $image, $attachment_id ) {
    $copyright = get_post_meta( $attachment_id, '_custom_copyright', true );

    if ( $copyright ) {
        $image['license'] = $copyright;
    }

    return $image;
}, 10, 2 );
```

**添加额外的 ping 端点**

```php
add_filter( 'morn_sitemap_plus_ping_endpoints', function ( $endpoints ) {
    $endpoints[] = 'https://your-notification-service.example/notify?sitemap=';

    return $endpoints;
} );
```

## FAQ

**Q：为什么 `/sitemap.xml` 404？**
A：按顺序排查：1) 后台「启用站点地图」是否开启；2) 到「设置 → 固定链接」点一次「保存」刷新重写规则；3) nginx 用户需添加上文给出的 rewrite 配置；4) 检查是否被其它安全插件拦截。

**Q：会与 WordPress 自带的 `wp-sitemap.xml` 冲突吗？**
A：不会。两者 URL 不同，可同时存在。若想只保留一个，可到「设置 → 搜索可见性」把 WordPress 的站点地图关掉。

**Q：为什么我的分类没有出现在站点地图里？**
A：1) 确认在「包含的分类法」中勾选了对应分类法；2) 分类下必须有已发布内容（使用 `hide_empty => true`，空分类不生成条目）；3) 检查是否被「排除的分类项 ID」排除。

**Q：为什么某个作者没有出现在站点地图里？**
A：该作者名下必须至少有 1 篇已发布文章。没有任何已发布内容的作者会被自动跳过——为其生成归档页 URL 会返回 404，对 SEO 无益。

**Q：ping 失败怎么办？**
A：1) 确认「启用定时 ping」已勾选；2) 部分主机的出站网络被限制（`allow_url_fopen` 关闭或防火墙拦截），可检查主机商设置；3) 设置页会显示上次 ping 时间；4) **ping 本身不是必需的**——Google 会通过 robots.txt 与 `<link rel="sitemap">` 自动发现站点地图，ping 只是加速通知。

**Q：Google News 站点地图需要额外配置吗？**
A：需要额外在 Google Search Console 中提交 `news-sitemap.xml` 地址。**内容要求**：必须有独特的高质量新闻内容、每篇需含标题与发布日期、每篇文章需包含 1~5 张图片。这些是 Google 的审核要求，插件无法自动满足。

**Q：分片数太多了怎么办？**
A：调大「每个分片条目数」。注意 sitemaps.org 协议规定单个站点地图文件不得超过 50000 个 URL，单个文件未压缩不得超过 50MB。

**Q：`changefreq` 和 `priority` 设了有用吗？**
A：Google 明确表示忽略这两个字段。保留是为了兼容其它爬虫。设置合理值即可，不必纠结。

**Q：站点地图会影响页面速度吗？**
A：几乎没有影响。站点地图是独立 URL，不在正常页面加载路径上。只有当爬虫访问时才生成一次。

**Q：为什么设置了排除 ID 还是出现在站点地图里？**
A：确认输入的是**文章 ID**（不是 slug 或标题），且格式为数字，每行一个或用逗号分隔。保存后需重新访问站点地图（响应头带 1 小时缓存，浏览器/CDN 也可能有缓存）。

**Q：如何验证生成的 XML 是否合法？**
A：可用以下方式：
1. 浏览器直接访问 `/sitemap.xml`，应看到结构清晰的 XML
2. 用 XML 校验工具检查
3. 在 Google Search Console 提交，观察抓取错误
4. 检查浏览器控制台是否有 304 响应（说明条件请求正常工作）

**Q：能输出到自定义 URL 吗？**
A：默认地址固定。如需改路径，可通过服务器 rewrite 规则重定向，或使用 `morn_sitemap_plus_index_xml` 等过滤器自定义内容。

## 目录说明

```
morn-sitemap-plus/
├── morn-sitemap-plus.php   # 主文件：插件头、启动、激活/停用钩子
├── uninstall.php            # 卸载清理（选项、定时任务、过滤器、重写规则）
├── README.md
├── LICENSE                  # MIT
├── CHANGELOG.md
├── .gitignore
├── .gitattributes
├── assets/
│   └── css/admin.css        # 后台分片列表与设置页样式
└── includes/
    ├── functions.php            # 设置读取、XML 转义、changefreq 计算、排除判定
    ├── class-plugin.php         # 协调层与 head 声明
    ├── class-sitemap.php        # 路由、索引页与子站图渲染
    ├── class-providers.php      # 内容提供者：文章/分类法/作者条目与计数
    ├── class-images.php         # 图片站点地图扩展
    ├── class-news.php           # Google News 站点地图
    ├── class-http.php           # Last-Modified / ETag / 304 条件请求
    ├── class-robots.php         # robots.txt 集成
    ├── class-notifier.php       # 搜索引擎 ping 通知与定时任务
    └── class-admin.php          # 设置页、分片列表、sanitize
```

**存储的选项**

| 选项名 | 类型 | 说明 |
| --- | --- | --- |
| `morn_sitemap_plus_settings` | array | 插件全部设置。 |
| `morn_sitemap_plus_last_ping` | integer | 上次 ping 的时间戳。 |

**查询变量**

`morn_sitemap_index`、`morn_sitemap_child`、`morn_sitemap_news`

**定时任务**：`morn_sitemap_plus_ping`（自定义间隔 `morn_sitemap_plus_ping_interval`）

## 卸载说明

在后台「插件」中点击「删除」并确认卸载时，`uninstall.php` 会执行以下清理：

- 删除选项 `morn_sitemap_plus_settings`、`morn_sitemap_plus_last_ping`
- 取消定时任务 `morn_sitemap_plus_ping`
- 移除本插件注册到 `robots_txt` 过滤器的回调（避免站点留下失效引用）
- 移除三条重写规则（`sitemap.xml`、`sitemap-*.xml`、`news-sitemap.xml`）

**不会删除**：任何文章、页面、媒体附件、用户数据或其它插件的数据。站点地图是动态生成的，无需清理任何文件。

## License

MIT License
Copyright (c) 2026 MornRain

详见 [LICENSE](LICENSE)。
