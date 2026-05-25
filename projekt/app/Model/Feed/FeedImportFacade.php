<?php

namespace App\Model\Feed;

use App\Model\PostFacade;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

// Stahuje RSS feed, parsuje ho pomoci SimpleXML a vytvari z nej prispevky.
// Data feedu jsou cachovana (vychozi 1 hodina), aby se kazdy klik admina
// na "importovat" nevybijel na sit.
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
        // namespace 'feed.import' izoluje klice od ostatnich cache zaznamu aplikace
        $this->cache = new Cache($cacheStorage, 'feed.import');
    }

    // Vrati nejaktuálnejsi polozku z feedu (z cache nebo site).
    // Vrati null pokud feed neni dostupny nebo neobsahuje zadne polozky.
    public function getLatestFeedItem(): ?array
    {
        // Cache::load ulozi vysledek jen pokud callback nehodí vyjimku.
        // Pri sitove chybe vyjimka probubla a cache zustane prazdna.
        return $this->cache->load(self::CACHE_KEY, function (&$deps): ?array {
            $deps[Cache::Expire] = self::CACHE_EXPIRE;
            return $this->fetchLatestItem();
        });
    }

    // Importuje nejnovejsi polozku feedu jako novy prispevek.
    // Navratove hodnoty:
    //   'created'   - prispevek byl uspesne vytvoren
    //   'duplicate' - prispevek se stejnym nazvem uz existuje
    // Hazi RuntimeException pokud feed neni dostupny.
    public function importLatestPost(int $authorUserId, string $uploadsDir): string
    {
        $item = $this->getLatestFeedItem();
        if (!$item) {
            throw new \RuntimeException('Feed není dostupný nebo neobsahuje žádné položky.');
        }

        if ($this->postFacade->findByTitle($item['title'])) {
            return 'duplicate';
        }

        // prispevek vytvorime nejdriv bez obrazku - potrebujeme jeho ID pro nazev souboru
        $post = $this->postFacade->createPost($item['title'], $item['content'], $authorUserId);

        $imagePath = null;
        if ($item['image_url'] !== null) {
            $imagePath = $this->downloadImage($item['image_url'], $uploadsDir, $post->id);
        }

        if ($imagePath !== null) {
            $this->postFacade->updatePost($post->id, $post->title, $post->content, $imagePath);
        }

        // po importu vymaze cache - pristi klik admina nacte nova data z feedu
        $this->cache->remove(self::CACHE_KEY);

        return 'created';
    }

    private function fetchLatestItem(): ?array
    {
        $raw = $this->httpGet(self::FEED_URL);

        if (!$this->looksLikeHtml($raw)) {
            // prima RSS/XML URL
            return $this->parseRss($raw, self::FEED_URL);
        }

        // HTML stranka - zkusime RSS autodiscovery, pak scraping
        try {
            $feedUrl = $this->discoverFeedUrl($raw, self::FEED_URL);
            $feedRaw = $this->httpGet($feedUrl);
            return $this->parseRss($feedRaw, $feedUrl);
        } catch (\RuntimeException) {
            // RSS nenalezeno - padneme na HTML scraping
            return $this->scrapeHtmlListing($raw, self::FEED_URL);
        }
    }

    private function parseRss(string $raw, string $sourceUrl): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        libxml_clear_errors();

        if (!$xml || !isset($xml->channel->item)) {
            throw new \RuntimeException("URL {$sourceUrl} neni platny RSS XML dokument.");
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
            throw new \RuntimeException('RSS feed neobsahuje zadne polozky.');
        }

        return [
            'title'     => $this->decodeText((string) $latestItem->title),
            'content'   => $this->cleanDescription((string) $latestItem->description),
            'image_url' => $this->extractImageUrl($latestItem),
            'pub_date'  => $latestTime,
        ];
    }

    // Scraping HTML stranky - pouzije se kdyz RSS feed neexistuje.
    // Najde prvni clanek na listingove strance, pak stahne jeho plny obsah.
    private function scrapeHtmlListing(string $html, string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $origin = $parsed['scheme'] . '://' . $parsed['host'];

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // hledame prvni clanek - zkousime bezne vzory v poradi od nejspecificejsiho
        $articleNode = null;
        foreach (['//article[1]', '(//div[contains(@class,"post")])[1]', '(//div[contains(@class,"card")])[1]', '(//div[contains(@class,"entry")])[1]'] as $q) {
            $nodes = $xpath->query($q);
            if ($nodes && $nodes->length > 0) {
                $articleNode = $nodes->item(0);
                break;
            }
        }

        if ($articleNode === null) {
            throw new \RuntimeException('Na strance nebyl nalezen zadny clanek (ani RSS feed). Zadejte primou URL RSS feedu.');
        }

        // nadpis
        $titleNode = $xpath->query('.//*[self::h1 or self::h2 or self::h3][1]', $articleNode)->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : null;
        if (!$title) {
            throw new \RuntimeException('Clanek nalezen, ale nepodarilo se extrahovat nadpis.');
        }

        // odkaz na plny clanek
        $linkNode = $xpath->query('.//a[@href][1]', $articleNode)->item(0);
        $link = $linkNode ? $linkNode->getAttribute('href') : null;
        if ($link && !str_starts_with($link, 'http')) {
            $link = $origin . '/' . ltrim($link, '/');
        }

        // obrazek z listingu
        $imgNode = $xpath->query('.//img[@src][1]', $articleNode)->item(0);
        $imageUrl = $imgNode ? $imgNode->getAttribute('src') : null;
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $imageUrl = $origin . '/' . ltrim($imageUrl, '/');
        }

        // excerpt z listingu jako vychozi obsah
        $descNode = $xpath->query('.//p[1]', $articleNode)->item(0);
        $content = $descNode ? trim($descNode->textContent) : '';

        // stáhneme plny clanek pro lepsi obsah a pripadne kvalitnejsi obrazek
        if ($link) {
            try {
                $articleHtml = $this->httpGet($link);
                $fullContent = $this->scrapeArticleContent($articleHtml);
                if ($fullContent !== '') {
                    $content = $fullContent;
                }
                // obrazek z clanku byva kvalitnejsi nez thumbnail z listingu
                if ($imageUrl === null) {
                    $imageUrl = $this->scrapeArticleImage($articleHtml, $origin);
                }
            } catch (\RuntimeException) {
                // nepodarilo se stahnout clanek - pouzijeme excerpt
            }
        }

        return [
            'title'     => $this->decodeText($title),
            'content'   => $this->decodeText($content),
            'image_url' => $imageUrl,
            'pub_date'  => time(),
        ];
    }

    // vytahne hlavni textovy obsah clanku
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

    // vytahne prvni vyznamny obrazek z clanku (ignoruje male ikony)
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
            // preskocime obrazky oznacene jako male (tracking pixely, ikony)
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

    // zjisti, zda odpoved vypada jako HTML (a ne XML/RSS)
    private function looksLikeHtml(string $content): bool
    {
        $start = strtolower(substr(ltrim($content), 0, 100));
        return str_contains($start, '<html') || str_contains($start, '<!doctype');
    }

    // RSS autodiscovery - nejprve hleda <link rel="alternate" type="application/rss+xml">
    // v HTML, pak zkusi bezne feed cesty (WordPress, Ghost, Jekyll...).
    // Hazi RuntimeException pokud zadna z cest nefunguje.
    private function discoverFeedUrl(string $html, string $baseUrl): string
    {
        $parsed = parse_url($baseUrl);
        $origin = $parsed['scheme'] . '://' . $parsed['host'];

        // hledame <link> tag v HTML
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

        // fallback - zkousime feed cesty.
        // jako prvni zkusime baseUrl + feed/ (WordPress archivni feed),
        // pak bezne kořenové cesty.
        $basePath = rtrim($parsed['path'] ?? '', '/');
        $candidates = [
            $origin . $basePath . '/feed/',
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
                    return $url; // dostali jsme XML - pouzijeme tuto cestu
                }
            } catch (\RuntimeException) {
                // tato cesta nefunguje, zkusime dalsi
            }
        }

        throw new \RuntimeException(
            "Na {$origin} nebyl nalezen RSS feed. Zkuste zadat primou URL feedu."
        );
    }

    // stahne URL pres cURL (preferovano) nebo file_get_contents jako fallback.
    // hazi RuntimeException s konkretnim duvodem selhani.
    private function httpGet(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                // defaultne overujeme SSL - bezpecnejsi
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
                throw new \RuntimeException("Server vratil HTTP {$status}.");
            }
            if ($raw === false || $raw === '') {
                throw new \RuntimeException('Server vratil prazdnou odpoved.');
            }

            return $raw;
        }

        // fallback: file_get_contents (vyzaduje allow_url_fopen = On)
        $context = stream_context_create(['http' => [
            'timeout'    => 15,
            'user_agent' => 'Mozilla/5.0 (compatible; Blog RSS importer)',
        ]]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false || $raw === '') {
            throw new \RuntimeException('file_get_contents selhalo. Zkontrolujte allow_url_fopen a sitove pripojeni kontejneru.');
        }

        return $raw;
    }

    // odstrani HTML tagy z description a dekoduje entity.
    // WordPress description byva bud cisty text, nebo HTML snippet.
    private function cleanDescription(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // zkusi ziskat URL obrazku z polozky feedu - tri strategie v poradi priority:
    //   1. <media:content> (Media RSS namespace, nejcasteji WordPress)
    //   2. <enclosure> (standardni RSS priloha)
    //   3. prvni <img> v HTML description (fallback)
    private function extractImageUrl(\SimpleXMLElement $item): ?string
    {
        // strategie 1: media:content (Yahoo Media RSS namespace)
        $media = $item->children('http://search.yahoo.com/mrss/');
        if (isset($media->content)) {
            $url = (string) ($media->content->attributes()['url'] ?? '');
            if ($url !== '') {
                return $url;
            }
        }

        // strategie 2: <enclosure url="..." type="image/...">
        $enc = $item->enclosure;
        if ($enc) {
            $attrs = $enc->attributes();
            $type  = (string) ($attrs['type'] ?? '');
            $url   = (string) ($attrs['url'] ?? '');
            if (str_starts_with($type, 'image/') && $url !== '') {
                return $url;
            }
        }

        // strategie 3: prvni <img src="..."> v description
        $desc = (string) $item->description;
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $desc, $m)) {
            return $m[1];
        }

        return null;
    }

    // stahne obrazek z URL a ulozi ho do uploadsDir.
    // timeout 10 s - nechceme, aby admin cekal na zasekle spojeni.
    // vrati relativni cestu (img/posts/feed-{id}.ext) nebo null pri chybe.
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
