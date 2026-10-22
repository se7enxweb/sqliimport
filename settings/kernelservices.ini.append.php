<?php /* #?ini charset="utf-8"?

# The services of 7x SQLI Import (Exponential's kernel service container, settings/kernelservices.ini).
# A site adds a source, a handler or a transform with a block of its own tagged sqliimport.source,
# sqliimport.handler or sqliimport.transform, and can replace these by a block of the same name
# (Enabled=false switches one off; sqliimport.ini [Source_<id>] Status=example or disabled does too).

[Service_sqliimport.registry]
Class=expImportRegistry
Scope=worker
Description=7x SQLI Import: the catalogue of sources, handlers and transforms

# 7x SQLI Import in Exponential's transfer registry (kernel 6.0.15 and later). Older kernels ignore the block.
[Service_transfer.sqliimport]
Class=expKernelTransferTool
Factory=expKernelTransferTool::fromSettings
Arguments[]=transfer.sqliimport
Scope=worker
Tags[]=transfer.tool
Description=Bring rows of a spreadsheet, CSV, XML, JSON or a feed in as content items: map the columns to a class, preview, run now or on a schedule, undo.
Name=Imports (7x SQLI Import)
Direction=import
Scopes[]=subtree
Scopes[]=class
Scopes[]=data
Formats[]=CSV
Formats[]=TSV
Formats[]=XLSX
Formats[]=ODS
Formats[]=XML
Formats[]=JSON
Formats[]=NDJSON
Formats[]=YAML
Formats[]=TOML
Formats[]=RSS
Formats[]=OPML
Formats[]=WXR
Formats[]=Markdown
Formats[]=iCalendar
Formats[]=vCard
Formats[]=ZIP
EntryURL=sqliimport/list
Permission=sqliimport/manage|sqliimport/run|sqliimport/manageimports|sqliimport/listimports
HelpPage=ext/sqliimport/README.md
RoundTrip[]=transfer.xrowextract
RoundTrip[]=transfer.bccie
RoundTrip[]=transfer.rss_export
RoundTrip[]=transfer.sqliimport
Keywords[]=import csv
Keywords[]=spreadsheet
Keywords[]=excel
Keywords[]=from another system
Keywords[]=shop
Keywords[]=crm
Keywords[]=wordpress
Keywords[]=mapping
Keywords[]=Tabelle
Keywords[]=importieren
Keywords[]=Zuordnung
Actions[]=container|import|Import into this folder…|sqliimport/edit/new/(parent)/%nodeID%|sqliimport/manage
Actions[]=class|import|Import objects of this class…|sqliimport/edit/new/(class)/%classIdentifier%|sqliimport/manage
Weight=15

# The formats of the catalogue. A format is one source class plus one block here:
#   Factory=expImportSourceEntry::of, Arguments[]=<the source class>[, Arguments[]=<a variant of it>]
# What the catalogue shows (description, group, example, preset mapping) comes from the class.

# data
[Service_sqliimport.source.csv]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportCsvSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: CSV and delimited text

[Service_sqliimport.source.tsv]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportCsvSource
Arguments[]=tsv
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: TSV (tab separated values)

[Service_sqliimport.source.spreadsheet]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportSpreadsheetSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: XLSX and ODS spreadsheets

[Service_sqliimport.source.json]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportJsonSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: JSON

[Service_sqliimport.source.ndjson]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportJsonSource
Arguments[]=ndjson
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: JSON Lines (NDJSON)

[Service_sqliimport.source.google_sheets]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportGoogleSheetsSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Google Sheets by its link

[Service_sqliimport.source.yaml]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportYamlSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: YAML data files

[Service_sqliimport.source.toml]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportYamlSource
Arguments[]=toml
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: TOML data files

[Service_sqliimport.source.xml]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportXmlSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: XML

# content
[Service_sqliimport.source.rss]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportRssSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: RSS and Atom feeds

[Service_sqliimport.source.opml]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportOpmlSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: OPML outlines and feed lists

[Service_sqliimport.source.wxr]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportWxrSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: WordPress export (WXR)

[Service_sqliimport.source.markdown]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportMarkdownSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Markdown files with front matter

[Service_sqliimport.source.podcast]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportRssSource
Arguments[]=podcast
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: podcast feeds

[Service_sqliimport.source.video_feed]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportRssSource
Arguments[]=video_feed
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: YouTube and Vimeo channel feeds

# platforms
[Service_sqliimport.source.xrowextract]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportXrowextractSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: export files of Export (xrowextract)

[Service_sqliimport.source.cie]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportCieSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: form submissions of CIE (bccie)

[Service_sqliimport.source.exponential_rss]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportRssSource
Arguments[]=exponential_rss
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: RSS export of another Exponential site

[Service_sqliimport.source.sitemap]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportSitemapSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: the pages of a site by its sitemap (crawl)

[Service_sqliimport.source.webpage]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportWebPageSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: fields picked from web pages (scraper)

[Service_sqliimport.source.media]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportMediaSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: media files with EXIF and IPTC
[Service_sqliimport.source.ical]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportIcalSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: iCalendar events and tasks (.ics)

[Service_sqliimport.source.vcard]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportVcardSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: vCard contacts (.vcf)

# commerce
[Service_sqliimport.source.google_merchant]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportGoogleMerchantSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Google Merchant product feeds

[Service_sqliimport.source.shopify]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportShopifyCsvSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: Shopify product CSV exports

[Service_sqliimport.source.woocommerce]
Class=expImportSourceEntry
Factory=expImportSourceEntry::of
Arguments[]=expImportWooCommerceCsvSource
Scope=worker
Tags[]=sqliimport.source
Description=7x SQLI Import format: WooCommerce product CSV exports

*/ ?>
