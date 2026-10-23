# Worked examples: feeds and web pages

This page shows one complete import for every source of 7x SQLI Import that reads feeds or web pages: RSS and Atom
feeds, podcast feeds with their episodes, YouTube and Vimeo channel feeds, the YouTube Data API, the opt-in
youtube.com page scraper, the pages of a site by its sitemap, and the web page scraper with CSS and XPath picks. Each
example says what it achieves, shows real lines of the example file, the exact values to give the import editor, the
mapping lines, and what the dry run or the run gave, with screenshots of the documentation server.

Every option is described in [sources.md](sources.md); each example links to its section there. The other worked
examples: [files](source-examples-files.md), [databases](source-examples-databases.md),
[APIs and hosted services](source-examples-apis.md) and [platforms, commerce and documents](source-examples-platforms.md).
German: [ger-de/source-examples-feeds-web.md](ger-de/source-examples-feeds-web.md).

**What ran, and what was not fetched.** The php.net news feed is read from its public address by the demo import
"Demo: PHP news (Atom feed)" every ten minutes. Every other example ran as a dry run of an import named "Docs: ...",
with the catalogue's example file (`share/examples/`) as an upload in place of the network address, so no site was
crawled for this manual. A sitemap or web page file uploaded this way is read without any request; the YouTube Data
API example is **illustrative: it was not run against a live account**, and its records are the catalogue's
snapshot of three invented videos.

## Contents

- [How the feed and web examples work](#how-the-feed-and-web-examples-work)
- [RSS and Atom (`rss`)](#rss-and-atom-rss)
  - [A feed address, read at every run](#a-feed-address-read-at-every-run)
  - [An RSS file](#an-rss-file)
- [Podcast feed (`podcast`)](#podcast-feed-podcast)
- [YouTube channel feed (`video_feed`)](#youtube-channel-feed-video_feed)
- [Vimeo channel feed (`video_feed`)](#vimeo-channel-feed-video_feed)
- [YouTube Data API (`youtube`)](#youtube-data-api-youtube)
- [youtube.com page scraper (`youtube_page`, opt-in)](#youtubecom-page-scraper-youtube_page-opt-in)
- [Pages of a site by its sitemap (`sitemap`)](#pages-of-a-site-by-its-sitemap-sitemap)
- [Web page scraper (`webpage`)](#web-page-scraper-webpage)
- [See also](#see-also)

## How the feed and web examples work

The steps are those of every import ([source-examples-files.md](source-examples-files.md#how-every-file-example-works)):
**Imports, Catalogue**, **New import** on the card, then the steps Source, Processing, Mapping, Preview, Schedule
and Review. For a feed, *Where the data comes from* is usually **An address (http or https), read at every run**,
with a schedule, so the site follows the feed. Three things are particular to these sources:

- **Addresses are checked.** Only `http` and `https`; addresses of private networks are refused unless
  `[SourceSettings] AllowPrivateHosts=enabled`; the time limit and size limit of the settings apply
  ([sources.md](sources.md#where-the-data-comes-from)).
- **Nothing is downloaded in a dry run.** The transforms `image` and `file` keep the address in the preview; the
  episode file or the thumbnail is fetched by the real run.
- **Crawling is polite.** The sitemap and web page sources obey robots.txt, space their requests, send no cookies
  and run no JavaScript ([sources.md](sources.md#pages-of-a-site-by-its-sitemap-sitemap-and-the-web-page-scraper-webpage)).
  Read only public pages you may use.

## RSS and Atom (`rss`)

Options: [sources.md, RSS / Atom](sources.md#rss--atom-rss).

RSS 2.0 and Atom are detected from the file and give the same fields (`id`, `title`, `link`, `summary`, `content`,
`date`, `author`, `categories`, `enclosure`), so one mapping fits both.

### A feed address, read at every run

Demo import "Demo: PHP news (Atom feed)": run #5, 15 created; a schedule every 10 minutes keeps it current.

**Goal:** the news of php.net on the site, as articles, updated by themselves.

**Input** (the public feed `https://www.php.net/feed.atom`, an Atom entry, shortened):

```xml
<entry xmlns="http://www.w3.org/2005/Atom" xml:base="entries/2026-10-21-4.xml">
  <title>PHP 8.5.12 Released!</title>
  <id>https://www.php.net/archive/2026.php#2026-10-21-4</id>
  <published>2026-10-21T00:24:52+00:00</published>
  <updated>2026-10-21T00:24:52+00:00</updated>
  <link href="https://www.php.net/index.php#2026-10-21-4" rel="alternate" type="text/html"/>
  <category term="releases" label="New PHP release"/>
  <content type="xhtml"> ... </content>
</entry>
```

**Editor steps:**

| Step, field | Value |
|---|---|
| Source type | *RSS / Atom feed* (group Content) |
| Where the data comes from | An address: `https://www.php.net/feed.atom` |
| Key field | `id` |
| Processing, at most this many records per run | `15` |
| Mapping | class `article`, remote ID `sq688-demo-news-{id}` |
| Schedule | every 10 minutes |

![The source step of the php.net demo: an address read at every run, https://www.php.net/feed.atom, key field id](images/src-rss-url-source.png)

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `title` | `title` | `strip_tags \| trim` | yes |
| `intro` | `summary` | `default(value="PHP news") \| richtext` | |
| `body` | `content` | `richtext` | |

An Atom entry of php.net has no summary; the `default` step gives the required intro a text.

**Result:** the first run created 15 articles; each later run of the schedule leaves them unchanged and creates only
what is new in the feed.

![The run page of run #5 of the php.net demo: completed, 15 created, one row per feed entry with its php.net address and the article's remote ID](images/src-rss-demo-run.png)

### An RSS file

Docs import "Docs: News (RSS file)": dry run #139, 2 would be created.

**Input** (`share/examples/feed.rss`, the first item):

```xml
<item>
  <guid>https://example.org/news/1</guid>
  <title>Spring opening hours</title>
  <link>https://example.org/news/1</link>
  <description>From April the shop opens at 8 am.</description>
  <content:encoded><![CDATA[<p>From April the shop opens at <b>8 am</b>.</p>]]></content:encoded>
  <pubDate>Fri, 20 Mar 2026 09:00:00 GMT</pubDate>
  <category>News</category>
</item>
```

**Editor steps:** source type *RSS / Atom feed*, an uploaded file `feed.rss`, *Key field* `id`. The preset: class
`article`, remote ID `feed-{id}` (here `sq688doc-feed-{id}`), the lines above without the default.

**Result:** two articles would be created, the first with an intro from `description` and a body from
`content:encoded`. The sample shows `format: rss` and the raw elements as `raw.*`:

![The preview of the RSS file: format rss, two items with id, title, link, summary, content, date and categories, and a dry run of two articles](images/src-rss-preview.png)

## Podcast feed (`podcast`)

Options: [sources.md, Podcast feeds and YouTube or Vimeo channel feeds](sources.md#podcast-feeds-podcast-and-youtube-or-vimeo-channel-feeds-video_feed).
Docs import "Docs: Podcast episodes": dry run #140, 2 would be created.

**Goal:** every episode of the shop's podcast as a file object with the audio file, its season and episode number
and its length.

**Input** (`share/examples/podcast.rss`, the first item):

```xml
<item>
  <guid isPermaLink="false">tea-talk-2</guid>
  <title>Episode 2: Green teas</title>
  <description>Sencha, gyokuro and how to brew them.</description>
  <pubDate>Thu, 02 Apr 2026 06:00:00 GMT</pubDate>
  <enclosure url="https://podcast.example.org/audio/episode-2.mp3" length="18734012" type="audio/mpeg"/>
  <itunes:duration>38:21</itunes:duration>
  <itunes:episode>2</itunes:episode>
  <itunes:season>1</itunes:season>
  <itunes:explicit>false</itunes:explicit>
  <itunes:image href="https://podcast.example.org/episode-2.jpg"/>
</item>
```

**Editor steps:** source type *Podcast feed* (group Media), an uploaded file `podcast.rss` (in production: the feed's
address, with a daily schedule), *Key field* `id`. The preset: class `file`, remote ID `podcast-{id}` (here
`sq688doc-podcast-{id}`).

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `title` | | yes |
| `description` | `summary` | `template(text="<p>{value}</p><p>Season {season}, episode {episode}, {duration} seconds.</p>") \| richtext` | |
| `file` (binary file) | `enclosure` | `file` | |

**Result:** two file objects would be created. The source read `enclosure` (the MP3 address), `enclosure_type`
`audio/mpeg`, `enclosure_length` `18734012`, `duration` `2301` (from `38:21`; `1:02:03` gives `3723`), `episode`
`2`, `season` `1`, `explicit` and `image` (the episode's iTunes image). The dry run keeps the address in the `file`
line; the real run downloads the episode into the file attribute.

![The preview of the podcast example: two episodes with enclosure, enclosure_type, enclosure_length, duration, episode, season, explicit and image, and a dry run of two file objects](images/src-podcast-preview.png)

## YouTube channel feed (`video_feed`)

Options: [sources.md, Podcast feeds and YouTube or Vimeo channel feeds](sources.md#podcast-feeds-podcast-and-youtube-or-vimeo-channel-feeds-video_feed).
Docs import "Docs: YouTube channel feed": dry run #141, 1 would be created.

**Goal:** the latest videos of a channel as link objects, without an API key.

**Input** (`share/examples/youtube.xml`, the Atom feed YouTube publishes at
`https://www.youtube.com/feeds/videos.xml?channel_id=<channel ID>`, shortened):

```xml
<entry>
  <id>yt:video:EXAMPLE0001</id>
  <yt:videoId>EXAMPLE0001</yt:videoId>
  <yt:channelId>UCexample0000000000000000</yt:channelId>
  <title>How to brew sencha</title>
  <link rel="alternate" href="https://www.youtube.com/watch?v=EXAMPLE0001"/>
  <media:group>
    <media:thumbnail url="https://i.ytimg.com/vi/EXAMPLE0001/hqdefault.jpg" width="480" height="360"/>
    <media:description>Water at 70 degrees, one minute.</media:description>
    <media:community><media:statistics views="4321"/></media:community>
  </media:group>
</entry>
```

**Editor steps:** source type *YouTube or Vimeo channel feed*, an uploaded file `youtube.xml` (in production: the
channel's feed address), *Key field* `id`. The preset: class `link`, remote ID `video-{video_id}` (here
`sq688doc-video-{video_id}`).

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `title` | | yes |
| `description` | `media_description` | `richtext` | |
| `location` | `link` | | |

**Result:** one link would be created, `sq688doc-video-EXAMPLE0001`. The record also has `video_id`, `channel_id`,
`embed_url` (`https://www.youtube.com/embed/EXAMPLE0001`), `image` (the thumbnail) and `views` (`4321`). The feed
lists only the last 15 videos; for the whole channel use the Data API below.

![The preview of the YouTube feed: format atom, one entry with id, title, link, image, video_id, channel_id, embed_url, views and media_description, and a dry run of one link](images/src-youtube-feed-preview.png)

## Vimeo channel feed (`video_feed`)

Options: [sources.md, Podcast feeds and YouTube or Vimeo channel feeds](sources.md#podcast-feeds-podcast-and-youtube-or-vimeo-channel-feeds-video_feed).
Docs import "Docs: Vimeo channel feed": dry run #142, 1 would be created.

**Goal:** the videos of a Vimeo user (`https://vimeo.com/<user>/videos/rss`) as link objects, with the same mapping
as YouTube.

**Input** (`share/examples/vimeo.rss`, the item):

```xml
<item>
  <title>Tea harvest</title>
  <link>https://vimeo.com/123456789</link>
  <guid isPermaLink="false">tag:vimeo,2026-05-10:clip123456789</guid>
  <description>Picking tea leaves in the morning.</description>
  <media:content>
    <media:player url="https://player.vimeo.com/video/123456789"/>
    <media:thumbnail url="https://i.vimeocdn.com/video/example_640.jpg"/>
  </media:content>
</item>
```

**Editor steps:** as for YouTube, with the upload `vimeo.rss`; the line `description` takes `summary`
(`richtext`), since a Vimeo feed has no `media:description`.

**Result:** one link would be created, `sq688doc-video-123456789` (`video_id` is the Vimeo ID taken from the guid),
with `embed_url` `https://player.vimeo.com/video/123456789` and `location` `https://vimeo.com/123456789`. Because
both feeds use `video-{video_id}`, YouTube and Vimeo videos can share one folder.

## YouTube Data API (`youtube`)

Options: [sources.md, YouTube Data API](sources.md#youtube-data-api-youtube).
**Illustrative: this example was not run against a live account.** The configuration is the docs import "Docs:
YouTube channel (Data API, illustrative)", never run (its secret is a placeholder); the records are the catalogue's
snapshot `share/examples/youtube-videos.ndjson`, read by the docs import "Docs: YouTube videos (snapshot of the Data
API)", dry run #143, 3 would be created.

**Goal:** every video of a channel (not only the last 15 of its feed), with its length, tags and view counts.

**Before you start:** in the Google Cloud console, create an API key, enable the YouTube Data API v3 and restrict
the key to it. Store the key as a secret ([sources.md, Secrets](sources.md#secrets)) or give it once in *New value*
of the source step; the option holds only the secret's **name**.

**Editor steps:**

| Field | Value |
|---|---|
| Source type | *YouTube channel, playlist or search (Data API)* (group Media) |
| Where the data comes from | Read by the source itself |
| Read | The uploads of a channel |
| Channel | `@exampletea` (a handle, a `UC...` ID or the channel's address) |
| Order | Newest first (used by a search only) |
| Page size | `50` |
| Most videos | `100` (`0`: all) |
| API key (name of a secret) | `sq-demo-youtube-key` |
| Key field | `id` |
| Mapping | the preset: class `link`, remote ID `youtube-{id}` (here `sq688doc-youtube-{id}`) |

![The source options of the YouTube Data API example: read the uploads of the channel @exampletea, page size 50, most videos 100, the API key as the name of a secret, with Test connection below](images/src-youtube-api-source.png)

**Test connection** makes one request of one quota unit (`channels.list`) and reports the channel; a channel of 500
videos costs about 21 units a run (a search costs 100 per page). Nothing else is sent: only `GET` requests to
`channels`, `playlistItems`, `search` and `videos`, with the key in a header, never in an address.

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `title` | `trim` | yes |
| `description` | `description` | `richtext` | |
| `location` | `url` | | |

With a class that has an image attribute `image`, the preset adds `thumbnail` with `image`, and with `publish_date`
the line `published` with `date`.

**What comes back** (the first line of the snapshot, shortened):

```json
{"id":"Ex4mpleVid1","url":"https://www.youtube.com/watch?v=Ex4mpleVid1","embed_url":"https://www.youtube-nocookie.com/embed/Ex4mpleVid1","title":"Brewing green tea at the right temperature","published":"2026-09-14T08:00:00Z","channel_title":"Example Tea Channel","tags":["tea","green tea","brewing"],"duration_seconds":754,"duration":"0:12:34","thumbnail":"https://i.ytimg.com/vi/Ex4mpleVid1/maxresdefault.jpg","view_count":1234,"like_count":56,"comment_count":7,"privacy":"public","live_broadcast_content":"none"}
```

**Expected result:** three links. The third video is an upcoming live stream (`duration_seconds` `0`,
`live_broadcast_content` `upcoming`, `privacy` `unlisted`); map `live_broadcast_content` to an attribute if the site
should tell such videos apart.

![The preview of the YouTube snapshot: three videos with id, url, embed_url, title, description, published, channel, tags, duration, thumbnail and counts, and a dry run of three links](images/src-youtube-snapshot-preview.png)

## youtube.com page scraper (`youtube_page`, opt-in)

Options: [sources.md, YouTube channel or playlist page](sources.md#youtube-channel-or-playlist-page-youtube_page-opt-in).
**Not run, and off on the documentation server.**

**Terms of service:** reading youtube.com pages with a program is against YouTube's Terms of Service, and this source
may break whenever YouTube changes its pages. Use it only where you may, and prefer the Data API or the channel feed
above. It reads no more than the first batch of videos a page holds (about 30 on a channel page, 100 on a playlist)
and never calls YouTube's internal API.

**Switched off by default.** Until an administrator switches it on, the catalogue lists the format as disabled with
the reason, the editor does not offer it, and a run or test of an existing import is refused:

![The catalogue card of the youtube.com page scraper: disabled, the reason that reading youtube.com pages is against YouTube's Terms of Service, how to switch it on, and the warning shown with the format](images/src-youtube-page-card.png)

**How it is switched on** (only where reading the pages is allowed): in `settings/override/sqliimport.ini.append.php`

```ini
[YouTubeSettings]
AllowPageScraper=enabled
```

then clear the INI cache (`php bin/php/ezcache.php --clear-tag=ini`). The card then shows the format as enabled,
with the warning.

**Configuration:**

| Field | Value |
|---|---|
| Source type | *YouTube channel or playlist page (scraper, opt-in)* |
| Page | `https://www.youtube.com/@exampletea/videos` (or `/streams`, `/shorts`, or `https://www.youtube.com/playlist?list=...`) |
| Most videos | `30` |
| Pause (seconds) | `2` (a longer `Crawl-delay` of robots.txt wins) |
| Key field | `id` |
| Mapping | the preset of the Data API: class `link`, remote ID `youtube-{id}` |

**Expected result** (the catalogue's snapshot `share/examples/youtube-page-videos.ndjson`, the first line, shortened):

```json
{"id":"Ex4mpleVid1","url":"https://www.youtube.com/watch?v=Ex4mpleVid1","title":"Brewing green tea at the right temperature","description":"","published":null,"published_text":"5 weeks ago","duration_seconds":754,"duration":"0:12:34","thumbnail":"https://i.ytimg.com/vi/Ex4mpleVid1/hqdefault.jpg","view_count":1234,"like_count":null}
```

The page gives fewer fields than the API: no publication time (only the relative `published_text`), no
description, tags or likes. Because both use the remote ID `youtube-{id}`, a later switch to the Data API updates the
same objects.

## Pages of a site by its sitemap (`sitemap`)

Options: [sources.md, `sitemap`](sources.md#sitemap).
Docs imports "Docs: Pages of a sitemap (file)" (dry run #144, 4 would be created) and "Docs: Sitemap crawl
(snapshot)" (dry run #145, 3 would be created, 1 failed).

**Goal:** move the news pages of an old site into articles, with the title, the meta description and the main text
of each page.

**Input** (`share/examples/sitemap.xml`, shortened):

```xml
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://example.org/</loc><lastmod>2026-10-01</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>
  <url><loc>https://example.org/news/green-tea-harvest</loc><lastmod>2026-09-28T09:30:00+00:00</lastmod><priority>0.8</priority></url>
  <url><loc>https://example.org/news/new-tea-room</loc><lastmod>2026-09-12</lastmod><priority>0.8</priority></url>
  <url><loc>https://example.org/about/</loc><lastmod>2026-01-05</lastmod><changefreq>yearly</changefreq><priority>0.5</priority></url>
</urlset>
```

**Editor steps** (for the real site; the docs imports use the example files as uploads):

| Field | Value |
|---|---|
| Source type | *Pages of a site by its sitemap* (group Platforms) |
| Sitemap | `https://example.org/sitemap.xml` (a sitemap index or the site's root work too) |
| Only addresses like | `/news/*` |
| Never addresses like | empty |
| Picks | `body=article .content` and `image=meta[property=og:image]::attr(content)`, one per line |
| Pages at most | `50` |
| Pause between requests | `1` |
| Key field | `url` |
| Mapping | the preset: class `article`, remote ID `page-{url_hash}` |

![The source options of the sitemap example: the sitemap address, only addresses like /news/*, two picks, at most 50 pages, a pause of 1 second, key field url](images/src-sitemap-source.png)

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `title` | `title` | `strip_tags \| trim` | yes |
| `intro` | `description` | `richtext` | |
| `body` | `body` | `richtext` | |

**A sitemap file as an upload** lists its pages and reads none of them: the records have `url`, `url_hash`,
`lastmod`, `priority` and `changefreq` only. The docs import did that (dry run #144: four records). The patterns of
*Only addresses like* are not applied to an uploaded sitemap, so all four pages are listed; they filter the pages of
a crawl.

**What a crawl writes** is a snapshot, one JSON object per page; `share/examples/sitemap-crawl.ndjson` is such a
snapshot, read by the second docs import:

```json
{"url":"https://example.org/news/green-tea-harvest","url_hash":"8b77441c1aa08ca95f4c978f3f9a5df1e1061504","lastmod":"2026-09-28T09:30:00+00:00","status":200,"title":"The green tea harvest","description":"This year's first flush has arrived.","language":"en","image":"https://example.org/images/harvest.jpg","body":"<p>This year's <em>first flush</em> has arrived.</p><p>Taste it in the tea room.</p>"}
{"url":"https://example.org/about/","url_hash":"1f20ccbf9c009a2c3e088d352a812cc59c1ddb63","lastmod":"2026-01-05","status":0,"_error":"robots.txt of the site does not allow reading https://example.org/about/"}
```

**Result:** three articles would be created; the page that robots.txt does not allow was never requested and is a
failed row with the reason, so the run ends *completed with errors*:

![The run page of dry run #145: completed with errors, 3 created and 1 failed; the failed row https://example.org/about/ says robots.txt of the site does not allow reading it](images/src-sitemap-crawl-run.png)

## Web page scraper (`webpage`)

Options: [sources.md, `webpage`](sources.md#webpage).
Docs import "Docs: Products of a web page": dry run #174, 3 would be created.

**Goal:** the products of a list page of an old shop (no export, no feed) as folders, one per product, with CSS and
XPath picks.

**Input** (`share/examples/page-products.html`, read as if it were `https://example.org/products/`; one of three
items):

```html
<main id="list">
  <article class="product featured" data-sku="T-100">
    <h2>Sencha Kyoto</h2>
    <img src="/images/sencha.jpg" alt="Sencha leaves">
    <p class="price">12.50 EUR</p>
    <div class="description"><p>A <strong>fresh</strong> green tea from Kyoto.</p><p>Steep for two minutes.</p></div>
    <ul class="tags"><li>green</li><li>japan</li></ul>
    <a class="more" href="sencha-kyoto">Details</a>
  </article>
  ...
</main>
<nav class="pager"><a rel="next" href="?page=2">Next</a></nav>
```

**Editor steps:**

| Field | Value |
|---|---|
| Source type | *Web page scraper* (group Platforms) |
| Addresses | `https://example.org/products/` (one per line) |
| Repeating item | `.product` |
| Next page | `a[rel=next]` |
| Picks | see below |
| Pages at most | `3` |
| Pause between requests | `1` |
| Key field | `id` |

```
title=h2
sku=@data-sku
price=.price
image=img@src
body=.description
tags=.tags li
link=a.more@href
heading=xpath:.//h2
```

Each pick is `field=selector`, relative to the item: `@data-sku` is an attribute of the item itself, `img@src` an
attribute of an element in it, `.tags li` finds two elements and gives a list, `body` keeps the inner HTML (and adds
`body_text`), and `xpath:` starts an XPath expression. Relative addresses (`/images/sencha.jpg`, `sencha-kyoto`) are
made absolute.

The source step in the dark theme:

![The source options of the web page scraper in the dark theme: the address, the repeating item .product, the next page a[rel=next], the picks, at most 3 pages, a pause of 1 second](images/src-scraper-source-dark.png)

Mapping: class `folder`, remote ID `sq688doc-scrape-{sku}` (the product number, which stays when the list is
reordered).

| Attribute | Source field | Transforms | Required |
|---|---|---|---|
| `name` | `title` | `trim` | yes |
| `short_description` | `price` | `trim \| richtext` | |
| `description` | `body` | `richtext` | |
| `tags` | `tags` | `join(separator=", ")` | |

**Result:** three folders would be created (Sencha Kyoto, Darjeeling First Flush, Rooibos Vanilla), with
`image` `https://example.org/images/sencha.jpg` and `link` `https://example.org/products/sencha-kyoto`. The script
of the page was never run, and the `onclick` attribute of the second item was removed before the picks. A first
version of this mapping put `price` through `number`, which failed every record with "number: '12.50 EUR' is not a
number"; `regex_replace(pattern="/[^0-9.]/", replace="") | number(decimals=2)` would keep only the amount.

![The preview of the web page scraper: three items with id, url, page, position, status, title, sku, price, image, body, tags, link and heading, and a dry run of three folders](images/src-scraper-preview.png)

On a real site the `next` link is followed while *Pages at most* allows, robots.txt is read first, and every
request waits the pause.

## See also

- [sources.md](sources.md): every option of every format.
- [Files](source-examples-files.md): CSV, spreadsheets, JSON, XML, YAML, TOML, OPML, Avro, Arrow, media, Markdown,
  WordPress, iCalendar and vCard.
- [schedules.md](schedules.md): run a feed import every N minutes or at a time.
- [runs.md](runs.md): the run page, retrying failed rows and undoing a run.
