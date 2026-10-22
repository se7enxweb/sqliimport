<?php /* #?ini charset="utf-8"?

# The services of 7x SQLI Import (Exponential's kernel service container, settings/kernelservices.ini).
# A site adds a source, a handler or a transform with a block of its own tagged sqliimport.source,
# sqliimport.handler or sqliimport.transform, and can replace these by a block of the same name
# (Enabled=false switches one off; sqliimport.ini [Source_<id>] Status=example or disabled does too).

[Service_sqliimport.registry]
Class=expImportRegistry
Scope=worker
Description=7x SQLI Import: the catalogue of sources, handlers and transforms

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
