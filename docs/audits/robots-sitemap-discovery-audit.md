# Robots and sitemap discovery audit

## Finding

This repository does **not** control the site's `robots.txt` response:

- there is no tracked `robots.txt`, `.htaccess`, nginx configuration, or web-server
  configuration;
- none of the tracked plugins or theme modules hooks WordPress's `robots_txt`
  filter or `do_robots` action; and
- `sync.sh` deploys changed files only when their path starts with `wp-content/`.

The live response therefore has to be changed outside this repository. On the
deployment described by `sync.sh`, first inspect
`/home/peraukco/public_html/robots.txt`. If that file exists, replace its
contents with the block below. A physical file at the document root takes
precedence over WordPress's virtual `/robots.txt` response on the usual
WordPress web-server configurations.

If that file does not exist, inspect the hosting control panel and the active
Apache/nginx configuration for a `/robots.txt` rewrite or static response. If
neither defines the response, inspect live-only WordPress plugins and
must-use plugins for a `robots_txt` filter or `do_robots` action, and make the
change there. Do not implement the response in the theme.

## Intended live contents

```text
User-agent: *
Disallow: /wp-admin/
Disallow: /wp-content/cache/
Disallow: */trackback/
Disallow: */feed/
Disallow: /*/feed/rss/$

Sitemap: https://www.peraproperty.com/sitemap.xml
```

This corrects the observed `/cgi -bin` and `*/ feed/` whitespace/path errors,
preserves sensible administration, cache, trackback, and feed exclusions, and
does not block public CSS, JavaScript, image, theme, plugin, or uploads paths.
The questionable CGI rule is omitted rather than replaced: CGI access should
be disabled or protected by the server, not treated as a crawler-only access
control.

## Why the theme header is not the fix

`wp-content/themes/hello-elementor-child/header.php` already calls
`wp_head()`. WordPress core and SEO plugins use that hook for document-head
output. `robots.txt` is a standalone HTTP resource, while XML sitemaps are
standalone discovery endpoints; neither belongs as hardcoded markup in the
theme header.

## Post-deployment verification

After changing the live response, run:

```sh
curl -fsS https://www.peraproperty.com/robots.txt
curl -fsSI https://www.peraproperty.com/sitemap.xml
curl -fsSI https://www.peraproperty.com/wp-sitemap.xml
```

The first command should exactly match the intended contents above, with no
spaces embedded in directive paths. Both sitemap requests should continue to
return a successful response. Also confirm in a browser or search-console URL
inspection that the sitemap URLs emitted by `/sitemap.xml` resolve to the
canonical public host.
