# Changelog

本项目遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

## [1.0.0] - 2026-10-02

### 新增

- 生成独立 XML 站点地图 `/sitemap.xml`（sitemapindex 结构），子站图按内容类型自动分片。
- 子站图 URL 规则：`/sitemap-{key}.xml`，超过单页容量时自动追加页码 `/sitemap-{key}-{n}.xml`。
- 支持的内容类型：文章类型（post/page/任意公开 CPT）、分类法（category/post_tag/任意公开分类法）、作者归档。
- 分片键名规则：文章类型 `post-{type}`，分类法 `tax-{taxonomy}`，作者 `authors`。
- 完整 sitemaps.org 0.9 规范输出：`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>`，正确的 namespace，含 `<lastmod>` `<changefreq>` `<priority>`。
- 排除规则：非 publish 状态、密码保护、排除 ID 列表中的内容；排除项在查询阶段用 `post__not_in` 剔除，保证分页不会漏条目。
- `changefreq` 按最后修改时间距今天数动态计算：7 天内 daily、30 天内 weekly、180 天内 monthly、更久 yearly，分类与作者页使用配置的基准值。
- 分类项与作者归档的 `lastmod` 取其名下最新文章的修改时间。
- 作者归档跳过无已发布文章的作者。
- **图片站点地图扩展**：取特色图 + 正文前 10 张图片，输出 `<image:image>` 的 loc、caption、title、license、width、height。正文图片通过 `wp-image-{id}` 类名优先识别，回退到 `attachment_url_to_postid()` 解析 src/srcset。
- **Google News 站点地图** `/news-sitemap.xml`：符合 Google News 0.9 规范，含 publication、language、publication_date、title、authors，天数上限强制 30 天。
- HTTP 条件请求：发送 `Last-Modified` 与 `ETag`，支持 `If-None-Match`（含多值与弱比较 `W/` 前缀）与 `If-Modified-Since`，命中时返回 304 且不输出响应体。
- `Last-Modified` 值从 XML 内容的全部 `<lastmod>` 节点取最大值推导，解析失败时降级为当前时间。
- robots.txt 自动追加 Sitemap 行（索引 + 新闻站图），仅在 `blog_public` 为 1 时输出。
- `<head>` 输出 `<link rel="sitemap">` 声明。
- 搜索引擎 ping 通知：Google 与 Bing 官方公开端点，5 秒超时，支持手动触发与 WP-Cron 定时触发（自定义间隔由 `cron_schedules` 过滤器注册）。
- ping 去重：内容保存时仅清除时间戳，实际请求交由定时任务执行，避免每次保存都发外部请求。
- 后台设置页列出已生成的每个分片的条目数与分片数，并提供可点击的预览链接。
- 站点设为不公开索引时返回 HTTP 403 而非暴露内容结构。

### 安全

- 所有 XML 输出经 `htmlspecialchars( ENT_XML1 | ENT_QUOTES )` 转义，并预先移除 XML 1.0 禁止的控制字符。
- 设置项经 `sanitize_callback` 白名单校验：文章类型与分类法限定为公开注册项，priority 限定 0.0~1.0，数值项均设上限。
- 手动 ping 经 `check_admin_referer()` 校验 nonce 与 `manage_options` 权限。
- 站点地图响应带 `X-Robots-Tag: noindex, follow`，避免搜索引擎索引站点地图页面本身。
- 排除 ID 限制最多 2000 个，分片数限制最多 50 个，新闻条目与图片数量均有硬上限。
- 卸载脚本移除自身注册的 `robots_txt` 过滤器与重写规则，不给站点留下失效引用。

### 兼容

- PHP 7.4 ~ 8.3 语法兼容，未使用 enum、readonly、构造器属性提升、命名参数等 PHP 8 独有特性。
- WordPress 6.0 ~ 6.6。
- 零外部资源：仅在显式启用 ping 时访问搜索引擎官方公开端点，不引用任何 CDN 或第三方脚本。
