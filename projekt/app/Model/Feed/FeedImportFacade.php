<?php

namespace App\Model\Feed;

use App\Model\PostFacade;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * Stahuje RSS feed, parsuje ho pomocí SimpleXML a vytváří z něj příspěvky.
 *
 * Data feedu jsou cachována (výchozí 1 hodina), aby se každý klik admina
 * na "importovat" nevybíjel na síť.
 */
final class FeedImportFacade
{
    private const FEED_URL = 'https://ancientheroes.net/blog';
    private const CACHE_KEY = 'latest_item';
    private const CACHE_EXPIRE = '1 hour';

    private Cache $cache;

    public function __construct(
        private PostFacade $postFacade,
        Storage $cacheStorage,
    ) {
        // Namespace 'feed.import' izoluje klíče od ostatních cache záznamů aplikace.
        $this->cache = new Cache($cacheStorage, 'feed.import');
    }

    /**
     * Vrátí nejaktuálnější položku z feedu (z cache nebo sítě).
     * Vrátí null pokud feed není dostupný nebo neobsahuje žádné položky.
     */
    public function getLatestFeedItem(): ?array
    {
        // Cache::load uloží výsledek jen pokud callback nehodí výjimku.
        // Při síťové chybě výjimka probublá a cache zůstane prázdná.
        return $this->cache->load(self::CACHE_KEY, function (&$deps): ?array {
            $deps[Cache::Expire] = self::CACHE_EXPIRE;
            return $this->fetchLatestItem();
        });
    }

    /**
     * Importuje nejnovější položku feedu jako nový příspěvek.
     *
     * Návratové hodnoty:
     *   'created'   — příspěvek byl úspěšně vytvořen
     *   'duplicate' — příspěvek se stejným názvem již existuje
     *
     * @throws \RuntimeException pokud feed není dostupný
     */
    public function importLatestPost(int $authorUserId, string $uploadsDir): string
    {
        $item = $this->getLatestFeedItem();
        if (!$item) {
            throw new \RuntimeException('Feed není dostupný nebo neobsahuje žádné položky.');
        }

        if ($this->postFacade->findByTitle($item['title'])) {
            return 'duplicate';
        }

        // Příspěvek vytvoříme nejdřív bez obrázku — potřebujeme jeho ID pro název souboru.
        $post = $this->postFacade->createPost($item['title'], $item['content'], $authorUserId);

        $imagePath = null;
        if ($item['image_url'] !== null) {
            $imagePath = $this->downloadImage($item['image_url'], $uploadsDir, $post->id);
        }

        if ($imagePath !== null) {
            $this->postFacade->updatePost($post->id, $post->title, $post->content, $imagePath);
        }

        // Po importu vymažeme cache — příští klik admina načte nová data z feedu.
        $this->cache->remove(self::CACHE_KEY);

        return 'created';
    }

    // ─────────────────────────────────────────────
    // Privátní pomocné metody
    // ─────────────────────────────────────────────

    private function fetchLatestItem(): ?array
    {
        $raw = $this->httpGet(self::FEED_URL);

        if (!$this->looksLikeHtml($raw)) {
            // Přímá RSS/XML URL
            return $this->parseRss($raw, self::FEED_URL);
        }

        // HTML stránka — zkusíme RSS autodiscovery, pak scraping
        try {
            $feedUrl = $this->discoverFeedUrl($raw, self::FEED_URL);
            $feedRaw = $this->httpGet($feedUrl);
            return $this->parseRss($feedRaw, $feedUrl);
        } catch (\RuntimeException) {
            // RSS nenalezeno — padneme na HTML scraping
            return $this->scrapeHtmlListing($raw, self::FEED_URL);
        }
    }

    private function parseRss(string $raw, string $sourceUrl): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        libxml_clear_errors();

        if (!$xml || !isset($xml->channel->item)) {
            throw new \RuntimeException("URL {$sourceUrl} není platný RSS XML dokument.");
        }

        $latestItem = null;
        $latestTime = 0;

        foreach ($xml->channel->item as $item) {
            $time = strtotime((string) $item->pubDate);
            if ($time > $latestTime) {
                $latestTime = $time;
                $latestItem = $item;
            }
        }

        if ($latestItem === null) {
            throw new \RuntimeException('RSS feed neobsahuje žádné položky.');
        }

        return [
            'title'     => $this->decodeText((string) $latestItem->title),
            'content'   => $this->cleanDescription((string) $latestItem->description),
            'image_url' => $this->extractImageUrl($latestItem),
            'pub_date'  => $latestTime,
        ];
    }

    /**
     * Scraping HTML stránky — použije se když RSS feed neexistuje.
     * Najde první článek na listingové stránce, pak stáhne jeho plný obsah.
     */
    private function scrapeHtmlListing(string $html, string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $origin = $parsed['scheme'] . '://' . $parsed['host'];

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // Hledáme první článek — zkoušíme běžné vzory v pořadí od nejspecifičtějšího
        $articleNode = null;
        foreach (['//article[1]', '(//div[contains(@class,"post")])[1]', '(//div[contains(@class,"card")])[1]', '(//div[contains(@class,"entry")])[1]'] as $q) {
            $nodes = $xpath->query($q);
            if ($nodes && $nodes->length > 0) {
                $articleNode = $nodes->item(0);
                break;
            }
        }

        if ($articleNode === null) {
            throw new \RuntimeException('Na stránce nebyl nalezen žádný článek (ani RSS feed). Zadejte přímou URL RSS feedu.');
        }

        // Nadpis
        $titleNode = $xpath->query('.//*[self::h1 or self::h2 or self::h3][1]', $articleNode)->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : null;
        if (!$title) {
            throw new \RuntimeException('Článek nalezen, ale nepodařilo se extrahovat nadpis.');
        }

        // Odkaz na plný článek
        $linkNode = $xpath->query('.//a[@href][1]', $articleNode)->item(0);
        $link = $linkNode ? $linkNode->getAttribute('href') : null;
        if ($link && !str_starts_with($link, 'http')) {
            $link = $origin . '/' . ltrim($link, '/');
        }

        // Obrázek z listingu
        $imgNode = $xpath->query('.//img[@src][1]', $articleNode)->item(0);
        $imageUrl = $imgNode ? $imgNode->getAttribute('src') : null;
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $imageUrl = $origin . '/' . ltrim($imageUrl, '/');
        }

        // Excerpt z listingu jako výchozí obsah
        $descNode = $xpath->query('.//p[1]', $articleNode)->item(0);
        $content = $descNode ? trim($descNode->textContent) : '';

        // Stáhneme plný článek pro lepší obsah a případně kvalitnější obrázek
        if ($link) {
            try {
                $articleHtml = $this->httpGet($link);
                $fullContent = $this->scrapeArticleContent($articleHtml);
                if ($fullContent !== '') {
                    $content = $fullContent;
                }
                // Obrázek z článku bývá kvalitnější než thumbnail z listingu
                if ($imageUrl === null) {
                    $imageUrl = $this->scrapeArticleImage($articleHtml, $origin);
                }
            } catch (\RuntimeException) {
                // Nepodařilo se stáhnout článek — použijeme excerpt
            }
        }

        return [
            'title'     => $this->decodeText($title),
            'content'   => $this->decodeText($content),
            'image_url' => $imageUrl,
            'pub_date'  => time(),
        ];
    }

    /** Vytáhne hlavní textový obsah článku. */
    private function scrapeArticleContent(string $html): string
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        foreach ([
            '//div[contains(@class,"entry-content")]',
            '//div[contains(@class,"post-content")]',
            '//div[contains(@class,"article-content")]',
            '//div[contains(@class,"content")]',
            '//article',
        ] as $q) {
            $node = $xpath->query($q)->item(0);
            if ($node) {
                return trim(strip_tags($node->textContent));
            }
        }
        return '';
    }

    /** Vytáhne první výrazný obrázek z článku (ignoruje malé ikony). */
    private function scrapeArticleImage(string $html, string $origin): ?string
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $imgs = $xpath->query('//article//img[@src] | //div[contains(@class,"content")]//img[@src]');

        foreach ($imgs as $img) {
            $src = $img->getAttribute('src');
            // Přeskočíme obrázky označené jako malé (tracking pixely, ikony)
            $w = (int) $img->getAttribute('width');
            $h = (int) $img->getAttribute('height');
            if (($w > 0 && $w < 100) || ($h > 0 && $h < 100)) {
                continue;
            }
            if (!str_starts_with($src, 'http')) {
                $src = $origin . '/' . ltrim($src, '/');
            }
            return $src;
        }
        return null;
    }

    private function decodeText(string $text): string
    {
        return html_entity_decode(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Zjistí, zda odpověď vypadá jako HTML (a ne XML/RSS).
     */
    private function looksLikeHtml(string $content): bool
    {
        $start = strtolower(substr(ltrim($content), 0, 100));
        return str_contains($start, '<html') || str_contains($start, '<!doctype');
    }

    /**
     * RSS autodiscovery — nejprve hledá <link rel="alternate" type="application/rss+xml">
     * v HTML, pak zkouší běžné feed cesty (WordPress, Ghost, Jekyll...).
     *
     * @throws \RuntimeException pokud žádná z cest nefunguje
     */
    private function discoverFeedUrl(string $html, string $baseUrl): string
    {
        $parsed = parse_url($baseUrl);
        $origin = $parsed['scheme'] . '://' . $parsed['host'];

        // 1. Hledáme <link> tag v HTML
        $pattern = '/<link[^>]+type=["\']application\/(rss|atom)\+xml["\'][^>]*>/i';
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[0] as $tag) {
                if (preg_match('/href=["\']([^"\']+)["\']/', $tag, $href)) {
                    $url = $href[1];
                    if (!str_starts_with($url, 'http')) {
                        $url = $origin . '/' . ltrim($url, '/');
                    }
                    return $url;
                }
            }
        }

        // 2. Fallback — zkoušíme feed cesty.
        // Jako první zkusíme baseUrl + feed/ (WordPress archivní feed),
        // pak běžné kořenové cesty.
        $basePath = rtrim($parsed['path'] ?? '', '/');
        $candidates = [
            $origin . $basePath . '/feed/',      // /stories/feed/ — WordPress archivní feed
            $origin . $basePath . '/feed/rss/',
            $origin . '/feed/',
            $origin . '/rss.xml',
            $origin . '/rss/',
            $origin . '/atom.xml',
            $origin . '/feed.xml',
        ];

        foreach ($candidates as $path) {
            $url = $origin . $path;
            try {
                $content = $this->httpGet($url);
                if (!$this->looksLikeHtml($content)) {
                    return $url; // dostali jsme XML — použijeme tuto cestu
                }
            } catch (\RuntimeException) {
                // Tato cesta nefunguje, zkusíme další
            }
        }

        throw new \RuntimeException(
            "Na {$origin} nebyl nalezen RSS feed. Zkuste zadat přímou URL feedu."
        );
    }

    /**
     * Stáhne URL přes cURL (preferováno) nebo file_get_contents jako fallback.
     * Hází RuntimeException s konkrétním důvodem selhání.
     */
    private function httpGet(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                // Vypnout SSL verifikaci jen v případě selhání lze zvážit,
                // ale defaultně ověřujeme — bezpečnější.
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Blog RSS importer)',
                CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/xml, text/xml'],
            ]);

            $raw    = curl_exec($ch);
            $errno  = curl_errno($ch);
            $errmsg = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno !== 0) {
                throw new \RuntimeException("cURL chyba ({$errno}): {$errmsg}");
            }
            if ($status !== 200) {
                throw new \RuntimeException("Server vrátil HTTP {$status}.");
            }
            if ($raw === false || $raw === '') {
                throw new \RuntimeException('Server vrátil prázdnou odpověď.');
            }

            return $raw;
        }

        // Fallback: file_get_contents (vyžaduje allow_url_fopen = On)
        $context = stream_context_create(['http' => [
            'timeout'    => 15,
            'user_agent' => 'Mozilla/5.0 (compatible; Blog RSS importer)',
        ]]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false || $raw === '') {
            throw new \RuntimeException('file_get_contents selhalo. Zkontrolujte allow_url_fopen a síťové připojení kontejneru.');
        }

        return $raw;
    }

    /**
     * Odstraní HTML tagy z description a dekóduje entity.
     * WordPress description bývá buď čistý text, nebo HTML snippet.
     */
    private function cleanDescription(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Zkusí získat URL obrázku z položky feedu — tři strategie v pořadí priority:
     *   1. <media:content> (Media RSS namespace, nejčastěji WordPress)
     *   2. <enclosure> (standardní RSS příloha)
     *   3. První <img> v HTML description (fallback)
     */
    private function extractImageUrl(\SimpleXMLElement $item): ?string
    {
        // Strategie 1: media:content (Yahoo Media RSS namespace)
        $media = $item->children('http://search.yahoo.com/mrss/');
        if (isset($media->content)) {
            $url = (string) ($media->content->attributes()['url'] ?? '');
            if ($url !== '') {
                return $url;
            }
        }

        // Strategie 2: <enclosure url="..." type="image/...">
        $enc = $item->enclosure;
        if ($enc) {
            $attrs = $enc->attributes();
            $type  = (string) ($attrs['type'] ?? '');
            $url   = (string) ($attrs['url'] ?? '');
            if (str_starts_with($type, 'image/') && $url !== '') {
                return $url;
            }
        }

        // Strategie 3: první <img src="..."> v description
        $desc = (string) $item->description;
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $desc, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Stáhne obrázek z URL a uloží ho do uploadsDir.
     * Timeout 10 s — nechceme, aby admin čekal na zaseknuté spojení.
     * Vrátí relativní cestu (img/posts/feed-{id}.ext) nebo null při chybě.
     */
    private function downloadImage(string $url, string $uploadsDir, int $postId): ?string
    {
        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $content = @file_get_contents($url, false, $context);
        if ($content === false || $content === '') {
            return null;
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }

        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $filename = 'feed-' . $postId . '.' . $ext;
        file_put_contents($uploadsDir . $filename, $content);
        return 'img/posts/' . $filename;
    }
}
