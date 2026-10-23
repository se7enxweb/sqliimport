# Durchgespielte Beispiele: Plattformen, Handel und Dokumente

Diese Seite zeigt einen vollständigen Import für die Quellen, die andere Systeme lesen: eine andere Exponential-Website
über REST, eine Drupal-Website über JSON:API, einen Joomla-Export, ein Exponential-Paket (`.ezpkg`); die Shop-Formate
(ein Google-Merchant-Feed, eine Shopify- oder WooCommerce-Produkt-CSV); ein IMAP-Postfach; und die Dokument-Quellen
der Erweiterung ezodf (Dokumente als eine Datei oder ZIP, von Adressen, aus einem Feed, in WebDAV oder Nextcloud,
Google Drive, OneDrive oder SharePoint und als Anhänge von E-Mails). Jedes Beispiel nennt sein Ziel, das Geheimnis,
das es braucht, die Werte für den Import-Editor, einen Auszug dessen, was die Quelle liest, die Zeilen der Zuordnung
und das Ergebnis.

Die Optionen beschreibt [sources.md](sources.md), für die Dokument-Quellen das eigene Handbuch von ezodf; jedes
Beispiel verlinkt auf seinen Abschnitt. Die anderen durchgespielten Beispiele:
[APIs und gehostete Dienste](source-examples-apis.md) und [Datenbanken](source-examples-databases.md).

**Was auf dem Server der Dokumentation lief.** Die Datei-Beispiele liefen als Importe des versteckten Ordners "SQLI
Import docs examples", jedes mit einem Probelauf, der nichts veröffentlicht hat: Joomla (#157), das Paket (#158),
Google Merchant (#159), Shopify (#160) und WooCommerce (#161). Das Dokumenten-ZIP lief echt in seinen eigenen
versteckten Ordner (#163, nach dem Probelauf #162). Ihre Bildschirmfotos zeigen, was wirklich geschah. Die Quellen, die
eine andere Website, ein Postfach oder einen Cloud-Speicher lesen, dienen **zur Veranschaulichung: Sie liefen nicht
gegen einen echten Dienst**; ihre Auszüge sind die Schnappschüsse des Katalogs in `share/examples/`, jede Adresse und
jeder Geheimnisname ist ein Platzhalter.

## Inhalt

- [Vorab](#vorab)
- [Plattformen](#plattformen)
  - [Eine andere Exponential-Website über REST (`exponential_site`)](#eine-andere-exponential-website-über-rest-exponential_site)
  - [Drupal JSON:API (`drupal`)](#drupal-jsonapi-drupal)
  - [Joomla-Beiträge, CSV oder XML (`joomla`)](#joomla-beiträge-csv-oder-xml-joomla)
  - [Exponential-Pakete (`ezpkg`)](#exponential-pakete-ezpkg)
- [Handel](#handel)
  - [Google-Merchant-Feed (`google_merchant`)](#google-merchant-feed-google_merchant)
  - [Shopify-Produkt-CSV (`shopify`)](#shopify-produkt-csv-shopify)
  - [WooCommerce-Produkt-CSV (`woocommerce`)](#woocommerce-produkt-csv-woocommerce)
- [Ein Postfach über IMAP, mit seinen Anhängen (`imap`)](#ein-postfach-über-imap-mit-seinen-anhängen-imap)
- [Dokumente (ezodf)](#dokumente-ezodf)
  - [Dokumente, eine Datei oder ein Ordner als ZIP (`documents`)](#dokumente-eine-datei-oder-ein-ordner-als-zip-documents)
  - [Dokumente von Adressen (`document_urls`)](#dokumente-von-adressen-document_urls)
  - [Dokumente eines Feeds (`document_feed`)](#dokumente-eines-feeds-document_feed)
  - [WebDAV oder Nextcloud (`webdav`)](#webdav-oder-nextcloud-webdav)
  - [Google Drive (`google_drive`)](#google-drive-google_drive)
  - [OneDrive oder SharePoint (`onedrive`)](#onedrive-oder-sharepoint-onedrive)
  - [Dokumente als Anhänge von E-Mails (`mailbox_documents`)](#dokumente-als-anhänge-von-e-mails-mailbox_documents)

## Vorab

- **Schritte.** **Importe, Katalog** (Catalogue), die Karte des Formats, **New import**; Schritt **Quelle** (Source:
  ein Name, die Datei oder die Optionen, **Verbindung testen**, wo das Format es hat); **Zuordnung** (Mapping);
  **Vorschau** (Preview: Stichprobe und Probelauf); dann **Run** oder ein Zeitplan ([editor.md](editor.md)).
- **Geheimnisse.** Ein Passwort, Token oder Schlüssel ist nie der Wert einer Option: Die Option enthält den **Namen**
  eines Geheimnisses, dessen Wert in **New value** darunter eingetragen wird (gespeichert unter **Setup, Secrets** von
  Exponential 6.0.15, Bereich `sqliimport`, nie wieder angezeigt), oder gesetzt unter Setup, Secrets, in
  `sqliimport.ini [SecretSettings] Secrets[]` von `settings/override/` oder als `env:<VARIABLE>`
  ([sources.md, Geheimnisse](sources.md#geheimnisse)). Die Bildschirmfotos zeigen ausgefüllte, nicht gespeicherte
  Formulare.
- **Gruppen des Katalogs.** Die Website-Leser und Exporte stehen in *Platforms*, die Shop-Formate in *Commerce*, IMAP
  und die Dokument-Quellen von ezodf in *Content*, die Dokumente als Anhänge von E-Mails in *Other*.

![Die Gruppe Commerce des Katalogs: der Google-Merchant-Feed, die Shopify-Produkt-CSV und die WooCommerce-Produkt-CSV](../images/src-catalogue-commerce-group.png)

## Plattformen

### Eine andere Exponential-Website über REST (`exponential_site`)

Optionen: [sources.md, Eine andere Exponential-Website](sources.md#eine-andere-exponential-website-exponential_site).
Zur Veranschaulichung: lief nicht gegen einen echten Dienst.

**Ziel:** die Nachrichten einer anderen Exponential-Website (die Kinder ihres Knotens 120, zwei Ebenen tief) kopieren
und die Kopie bei jedem Lauf nachführen.

**Geheimnis:** `partner_site_api_key`, ein persönlicher API-Schlüssel eines Benutzers der anderen Website, der nur
diesen Teil des Baums lesen darf (`bearer`). Mit OAuth stattdessen: `oauth_refresh` mit `client_id`, `client_secret` und
`refresh_token_secret` (die Namen zweier Geheimnisse).

| Option | Wert |
|---|---|
| Address (`base_url`) | `https://partner.example.invalid` |
| Parent node | `120` |
| Depth | `2` |
| Class | `article` |
| Translation | `eng-GB` |
| Page size | `50` |
| Most nodes | `1000` |
| Authentication | `bearer`, Token-Geheimnis `partner_site_api_key` |
| Key field | `remote_id` |

Die Quelle fragt seitenweise
`https://partner.example.invalid/api/ezp/v1/content/node/120/list/offset/0/limit/50?ResponseGroups=Metadata,Fields`
an und die Kinder jedes Knotens bis zur Tiefe. Auszug aus `share/examples/exponential-site.ndjson`:

```json
{"node_id":120,"object_id":118,"remote_id":"example-article-harbour","class_identifier":"article","name":"The harbour festival","published":1790500000,"url":"https://www.example.org/News/The-harbour-festival","parent_node_id":2,"depth":1,"fields":{"title":"The harbour festival","short_title":"Harbour festival","intro":"Boats, music and food at the harbour.","body":"The festival starts on Friday evening. Entry is free.","image":"https://www.example.org/var/site/storage/images/news/the-harbour-f..."}}
```

Die Zuordnung entsteht beim Speichern des Imports aus dem ersten Datensatz:

| Quellfeld | Attribut (`article`) | Umwandlungen |
|---|---|---|
| `fields.title` | `title` | |
| `fields.short_title` | `short_title` | |
| `fields.intro` | `intro` | `richtext` |
| `fields.body` | `body` | `richtext` |
| `fields.image` | `image` | `image` |

Zuordnen über die Remote-ID `{remote_id}`, die Kopie hat also die Remote-IDs des Originals. **Erwartetes Ergebnis:**
zwei Artikel; ein auf der anderen Website geänderter Artikel wird beim nächsten Lauf aktualisiert.

### Drupal JSON:API (`drupal`)

Optionen: [sources.md, Drupal](sources.md#drupal-drupal). Zur Veranschaulichung: lief nicht gegen einen echten Dienst.

**Ziel:** die veröffentlichten Artikel einer Drupal-10-Website, die neuesten zuerst, mit Bild und Schlagwörtern.

**Geheimnis:** keines für öffentliche Inhalte; `drupal_reader` mit `basic` (Modul basic_auth) oder ein
OAuth-Zugriffstoken mit `bearer`.

| Option | Wert |
|---|---|
| Address | `https://drupal.example.invalid` |
| Resource | `node--article` |
| Include | `field_image,uid,field_tags` |
| Filter | `filter[status]=1&sort=-created` |
| Authentication | `none` |
| Key field | `id` |

Die Quelle fragt `/jsonapi/node/article?include=...&filter[status]=1&sort=-created&page[limit]=50` an und folgt
`links.next`. Die rohe Antwort hat die Form von `share/examples/drupal-jsonapi.json`:

```json
{"type": "node--article", "id": "6f1c2a3e-1111-4c1d-9e6a-0a1b2c3d4e01",
 "attributes": {"drupal_internal__nid": 11, "status": true, "title": "Planting trees in the example park",
                "created": "2026-04-01T09:30:00+00:00", "changed": "2026-04-02T11:00:00+00:00", "...": "..."}}
```

und ein Datensatz bekommt `id`, `title`, `body`, `intro`, `created`, `image_url`, `image_alt`, `tags` und den Rest
unter seinem Namen. Die Vorlage der Zuordnung: `title` nach `title`, `intro` nach `intro` und `body` nach `body`
(beide `richtext`), `created` nach `publish_date` (`date`), `image_url` nach `image` (`image`), `tags` nach `tags`;
zugeordnet über `drupal-{id}`. **Erwartetes Ergebnis:** ein Artikel je Knoten, sein Bild als Bildobjekt
heruntergeladen.

![Die Gruppe Platforms des Katalogs: Exponential-Website, Drupal, Joomla, das Paketformat und die gehosteten Dienste](../images/src-catalogue-platforms-group.png)

### Joomla-Beiträge, CSV oder XML (`joomla`)

Optionen: [sources.md, Joomla-Beiträge](sources.md#joomla-beiträge-joomla). **Lief auf dem Server der Dokumentation**
(Import "Docs: Joomla articles (CSV)", Probelauf #157).

**Ziel:** die Beiträge einer Joomla-Website nach Exponential bringen, aus dem CSV-Export der Beitragstabelle (oder
J2XML).

**Geheimnis:** keines (eine Datei).

Schritte: Format **Joomla articles (CSV or XML export)**; *An uploaded file*: `joomla-articles.csv` (das Beispiel des
Katalogs); Optionen:

| Option | Wert |
|---|---|
| Variant | `auto` (an den ersten Bytes erkannt; `xml` für J2XML wie `joomla-articles.xml`) |
| Site address | `https://joomla.example.invalid` (damit `images/...` zu Adressen werden) |
| Status | `not_trashed` (veröffentlicht, unveröffentlicht, archiviert) |
| Key field | `id` |

Die Datei (Auszug):

```
id,title,alias,introtext,fulltext,catid,category_title,state,featured,created,...
101,Welcome to the example club,welcome-to-the-example-club,"<p>The example club meets every Tuesday.</p>","<p>Bring a friend, the first visit is free.</p>",8,Club news,1,1,2026-03-02 09:00:00,...
102,A summer trip to the lake,a-summer-trip-to-the-lake,"<p>Our trip to the lake is booked.</p>",,8,Club news,1,0,2026-05-10 14:30:00,...
103,Old minutes,old-minutes,"<p>The minutes of the last year.</p>",,9,Archive,-2,0,2025-01-01 12:00:00,...
```

| Quellfeld | Attribut (`article`) | Umwandlungen |
|---|---|---|
| `title` | `title` | (Pflicht) |
| `intro` | `intro` | `richtext` |
| `body` | `body` | `richtext` (Einleitung und Volltext verbunden, wie Joomla den Beitrag zeigt) |

Zuordnen über `joomla-{id}`. **Ergebnis:** Die Vorschau liest zwei Datensätze; Beitrag 103 hat den Status -2
(Papierkorb) und wird durch *Status* weggelassen. Der Probelauf: 2 würden angelegt.

![Die Vorschau des Joomla-Imports: zwei Beiträge der Stichprobe mit Einleitung, Volltext und Text, und ein Probelauf, in dem beide angelegt würden](../images/src-joomla-preview.png)

### Exponential-Pakete (`ezpkg`)

Optionen: [sources.md, Exponential-Pakete](sources.md#exponential-pakete-ezpkg). **Lief auf dem Server der
Dokumentation** (Import "Docs: Exponential package (.ezpkg)", Probelauf #158).

**Ziel:** die Artikel eines Inhaltspakets, das auf einer anderen Exponential-Website exportiert wurde (Setup,
Packages), hierher bringen und es später erneut importieren, um sie zu aktualisieren.

**Geheimnis:** keines.

Schritte: Format **Exponential package (.ezpkg)**; `exponential-package.ezpkg` hochladen; *Class* `article`; *Key
field* `remote_id`:

![Die Optionen der Quelle Exponential-Paket: die Klasse article, keine Sprache, das Schlüsselfeld remote_id](../images/src-ezpkg-new-upload.png)

Das Paket enthält einen Ordner und drei Artikel (`ezcontentobject/object-exp-example-article-garden.xml` ...) und ein
Bild (`simplefiles/1a2b3c4d.jpg`); das Archiv wird mit PharData gelesen und nie entpackt. Ein Datensatz: `remote_id`,
`name`, `class_identifier`, `fields.title`, `fields.intro` (Rich Text als sein XML) ... Ein Bild heißt
`source:<Pfad im Paket>`, für die Umwandlung `image`.

| Quellfeld | Attribut (`article`) |
|---|---|
| `fields.title` | `title` (Pflicht) |
| `fields.intro` | `intro` |
| `fields.body` | `body` |

Zuordnen über die Remote-ID `{remote_id}` (die eigenen Remote-IDs des Pakets). **Ergebnis:** 3 würden angelegt ("A
garden in the city", "Bicycles for everyone", "The Saturday market"); wird dasselbe Paket erneut importiert, findet
der Import sie über ihre Remote-IDs und aktualisiert sie, statt sie zu verdoppeln.

![Die Vorschau des Paket-Imports: drei Artikel des Pakets und ein Probelauf, in dem jeder mit Titel, Einleitung und Text angelegt würde](../images/src-ezpkg-preview.png)

## Handel

Die drei Shop-Formate gehen von der [Produktvorlage](sources.md#die-produktvorlage) der Webshop-Klasse `product` aus:
`title` nach `name` (`strip_tags | trim`), die Produktnummer nach `product_number`, `description` nach `description`
(`richtext`), `price` nach `price` über `template(text="{price}|1|1")` und `tags` nach `tags`
(`join(separator=", ")`). Der Preistext `Betrag|MwSt.-Typ|enthält MwSt.` ist das, was der Datentyp `ezprice` liest:
`|1|1` ist MwSt.-Typ 1, der Preis enthält die MwSt.; `|1|0` ist MwSt.-Typ 1, der Preis ist ohne MwSt. Ändern Sie es
auf den MwSt.-Typ Ihres Shops. Bilder sind nicht in der Vorlage (das `image` des Webshops ist eine Objektrelation).

### Google-Merchant-Feed (`google_merchant`)

Optionen: [sources.md, Google-Merchant-Feed](sources.md#google-merchant-feed-google_merchant). **Lief auf dem Server
der Dokumentation** (Probelauf #159).

**Ziel:** die Produkte des Merchant-Center-Feeds eines Shops als Produkte der Website, jede Nacht von der Adresse des
Feeds gelesen (hier als Upload des Beispiels `products-google.xml`).

**Geheimnis:** keines (ein öffentlicher Feed; ein geschützter ist eine Adresse, die der Server lesen darf).

Optionen: nur *Key field* `id`. Der Feed (Auszug):

```xml
<item>
  <g:id>EX-1001</g:id>
  <title>Example travel mug</title>
  <description><![CDATA[<p>Keeps coffee warm for <strong>six hours</strong>.</p>]]></description>
  <g:price>24.90 EUR</g:price>
  <g:sale_price>19.90 EUR</g:sale_price>
  <g:availability>in_stock</g:availability>
  <g:product_type>Home &gt; Kitchen &gt; Mugs</g:product_type>
  ...
</item>
```

Zuordnung: die Vorlage (zugeordnet über `gmc-{id}`, Produktnummer aus `id`). **Ergebnis:** 3 würden angelegt. Der
Preis wird als Dezimalzahl gelesen (`24.90 EUR` ergibt `24.90`, `1,299.00 USD` ergibt `1299.00`), `price` wird also
`24.90|1|1`; die eigenen Labels werden die Schlagwörter (`Bestseller, Winter`).

![Die Vorschau des Google-Merchant-Imports: drei Produkte und ein Probelauf mit Name, Produktnummer, Beschreibung, Preis mit MwSt.-Typ und Schlagwörtern](../images/src-google-merchant-preview.png)

### Shopify-Produkt-CSV (`shopify`)

Optionen: [sources.md, Shopify-Produkt-CSV](sources.md#shopify-produkt-csv-shopify). **Lief auf dem Server der
Dokumentation** (Probelauf #160).

**Ziel:** die Produkte eines Shopify-Shops aus seinem Produktexport (Products, Export), mit Preisen in Euro.

**Geheimnis:** keines.

| Option | Wert |
|---|---|
| Delimiter, Encoding | `auto`, `auto` |
| Currency | `EUR` (der Export nennt sie nicht) |
| Key field | `id` (der Handle) |

Die Zeilen mit demselben Handle sind ein Produkt: `example-hoodie` hat eine Zeile je Größe und Bild.

```
Handle,Title,Body (HTML),Vendor,...,Option1 Name,Option1 Value,...,Variant SKU,...,Variant Price,Variant Compare At Price,...
example-hoodie,Example hoodie,"<p>A warm hoodie, made of <em>organic</em> cotton.</p>",Example Apparel,...,Size,Small,...,HOODIE-S,...,49.00,59.00,...
example-hoodie,,,,,,,,,Medium,,,,,HOODIE-M,...,49.00,59.00,...
```

Zuordnung: die Vorlage mit der Produktnummer aus `sku` (die der ersten Variante), zugeordnet über `shopify-{id}`.
**Ergebnis:** 2 würden angelegt, `example-hoodie` (mit seinen Varianten in `variants`) und `example-gift-card`:

![Die Seite des Shopify-Probelaufs: zwei Produkte würden angelegt, jedes mit fünf Feldern](../images/src-shopify-run.png)

### WooCommerce-Produkt-CSV (`woocommerce`)

Optionen: [sources.md, WooCommerce-Produkt-CSV](sources.md#woocommerce-produkt-csv-woocommerce). **Lief auf dem
Server der Dokumentation** (Probelauf #161).

**Ziel:** die Produkte eines WooCommerce-Shops, dessen Preise **ohne MwSt.** eingegeben sind, mit ihrer
Kurzbeschreibung.

**Geheimnis:** keines.

| Option | Wert |
|---|---|
| Variations | `nested` (die Variationen eines variablen Produkts kommen in seine `variants`) |
| Delimiter, Encoding | `auto`, `auto` |
| Currency | `EUR` |
| Key field | `id` |

Der Export (Auszug): ein einfaches Produkt, ein variables Produkt und seine Variationen:

```
ID,Type,SKU,Name,Published,...,Short description,Description,...,Tax status,Tax class,...,Regular price,Categories,Tags,...
101,simple,WC-MUG,Example mug,1,...,A sturdy mug.,"Holds 350 ml.\nDishwasher safe.",...,taxable,,...,12.50,"Kitchen > Mugs, Gifts","mug, ceramic",...
102,variable,WC-TEE,Example T-shirt,1,...,An example T-shirt.,Soft cotton.,...,taxable,reduced-rate,...
103,variation,WC-TEE-S,Example T-shirt - S,1,...,taxable,parent,...,18.00,...
```

Zuordnung: die Vorlage, mit `short_description` nach `short_description` (`richtext`) und der Preiszeile geändert auf
`template(text="{price}|1|0")`, MwSt.-Typ 1 **ohne** MwSt. Der `price` eines variablen Produkts ist der niedrigste
Preis seiner Variationen. Der Schritt Zuordnung, wie gespeichert:

![Der Schritt Zuordnung des WooCommerce-Imports: Klasse product, Remote-ID woocommerce-{id} und die Zeilen für Name, Produktnummer, Kurzbeschreibung, Beschreibung, Preis und Schlagwörter](../images/src-woocommerce-mapping.png)

**Ergebnis:** 3 würden angelegt, 0 fehlgeschlagen. Der Export von WooCommerce hat die Steuerklasse (`tax_class`, hier
`reduced-rate`), aber keinen Satz. Ein Shop mit mehreren MwSt.-Typen gibt jeder Steuerklasse ihre eigene Preiszeile
mit einer [Klasse je Datensatz](mappings.md#eine-klasse-je-datensatz): `class_from` `tax_class` und eine Variante
`reduced-rate` der Klasse `product`, deren Zeilen `template(text="{price}|2|0")` haben (MwSt.-Typ 2); die anderen
Produkte behalten die Zeilen des Rasters.

## Ein Postfach über IMAP, mit seinen Anhängen (`imap`)

Optionen: [sources.md, E-Mail-Postfach über IMAP](sources.md#e-mail-postfach-über-imap-imap). Zur Veranschaulichung:
lief nicht gegen einen echten Dienst.

**Ziel:** die Pressemitteilungen an eine Presseadresse als Artikel, mit den Namen ihrer Anhänge.

**Geheimnis:** `press_inbox`, das Passwort des Postfachs (oder ein App-Passwort).

| Option | Wert |
|---|---|
| Host, Port | `imap.example.invalid`, `993` |
| Encryption | `ssl` |
| User | `press@example.invalid` |
| Password | `press_inbox` |
| Mailbox | `INBOX/Press` |
| Search | `UNSEEN SUBJECT "Press release"` |
| Days | `14` |
| Mark as seen | aus (das Postfach wird nur lesend geöffnet) |
| Attachments | an (Namen, Typen und Größen) |
| Key field | `message_id` |

![Die Optionen der IMAP-Quelle: Host, Verschlüsselung, Benutzer, der Geheimnisname press_inbox, das Postfach, die Suche und die Tage](../images/src-imap-options.png)

Ein Datensatz (zur Veranschaulichung):

```json
{"message_id":"20261013091500.4711@example.invalid","from.name":"Example Press Office","from.address":"office@example.invalid","subject":"Press release: the autumn tasting","date":"2026-10-13T09:15:00+02:00","summary":"The autumn tasting takes place on 24 October.","body":"<p>The autumn tasting takes place on 24 October.</p>","attachments":[{"name":"programme.pdf","mime":"application/pdf","size":48213}],"attachment_count":1}
```

Die Vorlage: `subject` nach `title`, `summary` nach `intro` und `body` nach `body` (`richtext`), `date` nach
`publish_date`; zugeordnet über `mail-{message_id}`. **Erwartetes Ergebnis:** ein Artikel je passender Nachricht. Die
Anhänge werden aufgelistet, nicht gelesen; um ihren Inhalt zu importieren, nutzen Sie
[Dokumente als Anhänge von E-Mails](#dokumente-als-anhänge-von-e-mails-mailbox_documents).

## Dokumente (ezodf)

Die Erweiterung ezodf fügt dem Katalog sieben Dokumentformate hinzu (Gruppe *Content*, das Postfach-Format in
*Other*). ezodf wandelt jede Word-, OpenDocument-, PDF-, RTF-, Tabellen- oder Präsentationsdatei um; 7x SQLI Import
ordnet sie zu. Ein Dokument-Datensatz hat `title`, `summary`, `html` (der Text mit seinen Bildern), `text`,
`keywords`, `author`, `created`, `modified`, `kind` (document oder folder), `path`, `parent_path`, `folder` und
`source_url`. Die Vorlage der Formate:

| Quellfeld | Attribut (`article`) | Umwandlungen |
|---|---|---|
| `title` | `title` | |
| `summary` | `intro` | `richtext` |
| `html` | `body` | `richtext` |
| `modified` | `publish_date` (wo die Klasse es hat) | `date` |
| `keywords` | `tags` (wo die Klasse es hat) | `join(separator=", ")` |

Platzierung: `parent_from` `parent_path` mit dem Präfix des Formats (`doc-`, `webdav-`, `onedrive-` ...), so bauen die
Ordner eines ZIP oder eines Laufwerks den Baum nach.

![Die Gruppe Content des Katalogs mit den Karten der Dokumentformate von ezodf, IMAP und den Inhaltsformaten](../images/src-catalogue-content-group.png)

### Dokumente, eine Datei oder ein Ordner als ZIP (`documents`)

**Lief auf dem Server der Dokumentation:** Import "Docs: Handbook documents (ZIP)", Probelauf #162 und Lauf #163.

**Ziel:** ein Mitarbeiterhandbuch als Ordner mit Word-, OpenDocument- und RTF-Dateien, als ein ZIP hochgeladen, als
Baum von Artikeln.

**Geheimnis:** keines.

Schritte: Format **Documents (one file or a folder as ZIP)**; `docs-handbook.zip` hochladen; Optionen *Folders as
records* an, *Leave out* leer, *Key field* `id`. Das ZIP:

```
Handbook/Welcome.odt
Handbook/Policies/Travel policy.docx
Handbook/Policies/Notes.rtf
```

Die Quelle liefert fünf Datensätze, die Ordner vor ihren Dokumenten: `Handbook`, `Handbook/Policies` (kind folder) und
die drei Dokumente (kind document), jeder mit seinem `parent_path`:

![Die Vorschau des Dokumenten-Imports: fünf Datensätze mit ID, Art, Pfad und Ordner, und der Probelauf nach dem echten Lauf: alle fünf unverändert](../images/src-documents-preview.png)

Zuordnung: die Vorlage oben (`title`, `summary`, `html`), zugeordnet über `doc-{id}`, Elternobjekt aus `parent_path` mit
dem Präfix `doc-`. **Ergebnis:** Der echte Lauf #163 legte 5 Objekte an, die beiden Ordner und darunter die drei
Dokumente:

![Die Seite des Laufs des Dokumenten-Imports: fünf Datensätze angelegt, Handbook, Handbook/Policies und die drei Dokumente](../images/src-documents-run.png)

**Ein Probelauf vor dem ersten Lauf zählt die Ordner mit, die er anlegen würde.** Ein Probelauf veröffentlicht
nichts, also gibt es die Ordner, die er anlegen würde, noch nicht; er merkt sie sich, und die vier Datensätze unter
`Handbook` sagen „would be created below <Remote-ID des Ordners>, which this run would create“. Das Bild ist ein
Probelauf einer frischen Kopie des Imports mit neuen Remote-IDs (`sq688-drycheck-` statt `doc-`), Lauf #269, für diese
Seite angelegt und wieder entfernt; alle fünf Datensätze würden angelegt:

![Der Probelauf einer frischen Kopie des Dokumenten-Imports: fünf Datensätze angelegt, die vier unter Handbook würden unter dem Ordner angelegt, den dieser Lauf anlegen würde, 0 fehlgeschlagen](../images/src-documents-dry-run-parents.png)

Vor 2.0.0 ließ der Probelauf #162 diese vier Datensätze mit „the parent of the record does not exist (yet)“
fehlschlagen. Der echte Lauf platziert sie alle. Um Ordnern ihre eigene Klasse zu geben, eine Klasse je Datensatz hinzufügen:
`class_from` `kind` mit der Variante `folder` in die Klasse `folder` ([mappings.md](mappings.md#eine-klasse-je-datensatz)).

### Dokumente von Adressen (`document_urls`)

Zur Veranschaulichung: lief nicht gegen einen echten Dienst.

**Ziel:** drei öffentliche Dokumente eines Partners (eine Broschüre, eine Preisliste, ein Bericht), importiert und
wöchentlich erneuert.

| Option | Wert |
|---|---|
| Addresses | `https://partner.example.invalid/files/brochure-2026.pdf`<br>`https://partner.example.invalid/files/price-list.docx`<br>`https://partner.example.invalid/files/annual-report.odt` |
| Key field | `id` |

Jede Adresse wird einmal je Lauf heruntergeladen (nur öffentliche Adressen, mit Größen- und Zeitlimits). Zuordnung:
die Vorlage, zugeordnet über `docurl-{id}`, kein Baum. **Erwartetes Ergebnis:** drei Artikel, jeder mit `source_url`;
ein geändertes Dokument importiert der wöchentliche Zeitplan erneut.

### Dokumente eines Feeds (`document_feed`)

Zur Veranschaulichung: lief nicht gegen einen echten Dienst.

**Ziel:** die Dokumente, auf die ein Publikations-Feed verlinkt (jedes Element des Feeds verlinkt ein PDF oder DOCX).

| Option | Wert |
|---|---|
| Feed address | `https://publications.example.invalid/feed.rss` |
| Items | `20` (die neuesten je Lauf) |

Ein Datensatz ergänzt die Felder des Dokuments um Titel, Link, Datum und Zusammenfassung des Elements. Zuordnung: die
Vorlage, mit dem Datum des Elements als Veröffentlichungsdatum. **Erwartetes Ergebnis:** ein Artikel je verlinktem
Dokument der neuesten zwanzig Elemente.

### WebDAV oder Nextcloud (`webdav`)

Zur Veranschaulichung: lief nicht gegen einen echten Dienst.

**Ziel:** der Ordner Documents eines Nextcloud-Benutzers, drei Ebenen tief, als Baum.

**Geheimnis:** `cloud_documents`, ein **App-Passwort** des Nextcloud-Benutzers (Einstellungen, Sicherheit).

Die Karte des Formats mit ihrer Beispielkonfiguration:

![Die Karte des Dokumentformats WebDAV oder Nextcloud im Katalog mit ihrem Beispiel eines Nextcloud-Ordners Documents](../images/src-catalogue-documents-card.png)

| Option | Wert |
|---|---|
| Folder address | `https://cloud.example.invalid/remote.php/dav/files/editor/Documents/` |
| User | `editor` |
| Password | `cloud_documents` |
| Depth | `3` |
| Folders as records | an |
| Trust this server | aus (an nur für einen eigenen Server im Intranet) |

![Die Optionen der WebDAV-Quelle für den Ordner Documents eines Nextcloud-Benutzers mit dem Geheimnisnamen cloud_documents](../images/src-webdav-nextcloud-options.png)

Zuordnung: die Vorlage, zugeordnet über `webdav-{id}`, Elternobjekt aus `parent_path` mit dem Präfix `webdav-`.
**Erwartetes Ergebnis:** die Ordner und Dokumente des Baums; eine in Nextcloud geänderte Datei (ihr ETag ändert sich)
wird beim nächsten Lauf erneut importiert. ownCloud und andere WebDAV-Server arbeiten genauso.

### Google Drive (`google_drive`)

Zur Veranschaulichung: lief nicht gegen einen echten Dienst.

**Ziel:** ein Drive-Ordner mit Anleitungen, einschließlich Google Docs, als Artikel.

**Geheimnis:** `drive_reader_key`, der JSON-Schlüssel eines Dienstkontos; den Ordner für die Adresse dieses Kontos
freigeben (als Betrachter).

| Option | Wert |
|---|---|
| Folder ID | `1ExampleDriveFolderIdREPLACE` (der letzte Teil von `https://drive.google.com/drive/folders/<ID>`) |
| Service account key | `drive_reader_key` |
| Depth | `3` |
| Folders as records | an |
| Google Docs, Sheets and Slides | an (als DOCX, XLSX, PPTX exportiert, je höchstens 10 MB) |

![Die Optionen der Google-Drive-Quelle mit der Ordner-ID und dem Namen des Geheimnisses mit dem Dienstkonto-Schlüssel](../images/src-google-drive-options.png)

**Erwartetes Ergebnis:** ein Datensatz je Ordner und Dokument, mit Drive-ID und Link; die Vorlage platziert sie als
Baum.

### OneDrive oder SharePoint (`onedrive`)

Zur Veranschaulichung: lief nicht gegen einen echten Dienst.

**Ziel:** der Presseordner einer SharePoint-Dokumentbibliothek.

**Geheimnis:** `graph_documents`, das Client-Geheimnis einer App-Registrierung mit der Anwendungsberechtigung
`Sites.Selected` (mit Lesezugriff auf diese Website) oder `Files.Read.All`.

| Option | Wert |
|---|---|
| Tenant | `example.onmicrosoft.com` |
| Application (client) ID | `00000000-0000-0000-0000-000000000000` |
| Client secret | `graph_documents` |
| Drive | `sites/<Site-ID>/drive` (oder `users/<User Principal Name>/drive`, `drives/<Drive-ID>`) |
| Folder | `Documents/Press` |
| Depth | `3` |

![Die Optionen der Quelle OneDrive oder SharePoint mit Tenant, Client-ID, dem Geheimnisnamen graph_documents, dem Laufwerk und dem Ordner](../images/src-onedrive-options.png)

Zuordnung: die Vorlage, zugeordnet über `onedrive-{id}`, Präfix des Elternobjekts `onedrive-`. **Erwartetes
Ergebnis:** die Ordner und Dokumente von `Documents/Press` und darunter.

### Dokumente als Anhänge von E-Mails (`mailbox_documents`)

Zur Veranschaulichung: lief nicht gegen einen echten Dienst.

**Ziel:** Eine Einreichungsadresse bekommt Artikel als Word-Dateien; jeder Anhang wird ein Artikel.

**Geheimnis:** `submissions_inbox`.

Die Optionen sind die von [IMAP](#ein-postfach-über-imap-mit-seinen-anhängen-imap): Host `imap.example.invalid`,
Benutzer `submissions@example.invalid`, Passwort `submissions_inbox`, Postfach `INBOX`, Suche `UNSEEN`, *Mark as seen*
an (damit ein Anhang einmal importiert wird), Schlüsselfeld `id` (die Nachrichten-ID und der Name des Anhangs). Ein
Datensatz hat die Felder des Dokuments und ID, Betreff, Absenderadresse und Datum der Nachricht.

Zuordnung: die Vorlage ohne Baum, zugeordnet über `mail-{id}`. **Erwartetes Ergebnis:** ein Artikel je Word-,
OpenDocument-, PDF- oder RTF-Anhang der neuen Nachrichten. Die Absenderadressen sind personenbezogene Daten: nur
zuordnen, wenn die Website sie braucht.

## Siehe auch

- [Durchgespielte Beispiele: APIs und gehostete Dienste](source-examples-apis.md)
- [Durchgespielte Beispiele: Datenbanken](source-examples-databases.md)
- [Quellen](sources.md), [Der Import-Editor](editor.md), [Zuordnungen](mappings.md), [Läufe](runs.md)
