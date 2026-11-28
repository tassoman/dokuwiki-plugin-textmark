<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\textmark\classes\FontResolver;
use dokuwiki\plugin\textmark\classes\GdWatermarker;
use dokuwiki\plugin\textmark\classes\ImageMagickWatermarker;
use dokuwiki\plugin\textmark\classes\Watermarker;

// phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps
class action_plugin_textmark extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('MEDIA_SENDFILE', 'BEFORE', $this, 'handleMediaSendfile');
    }

    public function handleMediaSendfile(Event $event, $param)
    {
        $data     = &$event->data;
        $origFile = $data['file'];
        $mediaId  = $data['media'] ?? '';
        $ext      = strtolower($data['ext'] ?? '');

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true) || !file_exists($origFile)) {
            return;
        }

        if ($this->isExcludedNamespace($mediaId)) {
            return;
        }

        $text = $this->watermarkText();

        $info = @getimagesize($origFile);
        if (!$info || $info[0] < (int)$this->getConf('min_width')) {
            return;
        }

        $fontPath = $this->fontPath();
        if ($fontPath === null) {
            return;
        }

        $watermarkers = $this->createWatermarkers($text, $fontPath);
        $cacheFile   = $this->cacheFile($origFile, $ext, $text, $fontPath, $watermarkers[0]);

        if (!file_exists($cacheFile)) {
            io_makeFileDir($cacheFile);

            $success = false;
            foreach ($watermarkers as $watermarker) {
                if ($watermarker->apply($origFile, $cacheFile, $info[2])) {
                    $success = true;
                    break;
                }
            }

            if (!$success) {
                return;
            }
        }

        $data['file'] = $cacheFile;
    }

    /**
     * Renderers in order of preference: ImageMagick when configured, then GD
     *
     * @return Watermarker[]
     */
    private function createWatermarkers(string $text, string $fontPath): array
    {
        $config = [
            'text'            => $text,
            'fontSizePercent' => $this->getConf('font_size'),
            'minFontSize'     => $this->getConf('min_font_size'),
            'textColor'       => $this->getConf('text_color'),
            'opacity'         => $this->getConf('text_opacity'),
            'position'        => $this->getConf('position'),
            'angle'           => $this->getConf('angle'),
            'border'          => $this->getConf('border'),
            'borderColor'     => $this->getConf('border_color'),
            'borderWidth'     => $this->getConf('border_width'),
            'padding'         => $this->getConf('padding'),
        ];

        $watermarkers = [];
        $binary = $this->getImageMagickBinary();
        if ($binary !== null) {
            $watermarkers[] = new ImageMagickWatermarker($config, $fontPath, $binary);
        }
        $watermarkers[] = new GdWatermarker($config, $fontPath);

        return $watermarkers;
    }

    /**
     * Watermark text to render
     *
     * A configured value always wins. When left empty, the text is generated at
     * runtime from the current year and a site name: the wiki title, or the
     * DokuWiki base URL (falling back to the bare hostname) when no title is set.
     */
    private function watermarkText(): string
    {
        $configured = trim((string)$this->getConf('watermark_text'));
        if ($configured !== '') {
            return $configured;
        }

        $name = trim((string)($GLOBALS['conf']['title'] ?? ''));
        if ($name === '') {
            $name = $this->siteUrl();
        }

        return trim("©" . date('Y') . ' ' . $name);
    }

    /**
     * Absolute base URL of this DokuWiki instance, falling back to the hostname
     */
    private function siteUrl(): string
    {
        if (defined('DOKU_URL') && DOKU_URL !== '') {
            return rtrim(DOKU_URL, '/');
        }

        global $conf;

        $base = trim((string)($conf['baseurl'] ?? ''));
        if ($base !== '') {
            return rtrim($base, '/');
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        return $host !== '' ? 'http://' . $host : '';
    }

    /**
     * Font file to render with, or null when none is usable
     */
    private function fontPath(): ?string
    {
        return (new FontResolver(__DIR__, __DIR__ . '/fonts/default.ttf'))->resolve();
    }

    /**
     * Cache name derived from the source file and every setting that affects the output
     */
    private function cacheFile(
        string $origFile,
        string $ext,
        string $text,
        string $fontPath,
        Watermarker $watermarker
    ): string {
        $configHash = md5(serialize([
            filemtime($origFile),
            $watermarker->getName(),
            $text,
            $fontPath,
            filemtime($fontPath),
            $this->getConf('font_size'),
            $this->getConf('min_font_size'),
            $this->getConf('text_color'),
            $this->getConf('text_opacity'),
            $this->getConf('position'),
            $this->getConf('angle'),
            $this->getConf('border'),
            $this->getConf('border_color'),
            $this->getConf('border_width'),
            $this->getConf('padding'),
        ]));

        return getCacheName($origFile . $configHash, '.' . $ext);
    }

    private function getImageMagickBinary(): ?string
    {
        global $conf;

        $bin = trim((string)($conf['im_convert'] ?? ''));
        if ($bin === '' || !function_exists('exec')) {
            return null;
        }

        if (@is_file($bin) && @is_executable($bin)) {
            return $bin;
        }

        return null;
    }

    private function isExcludedNamespace(string $mediaId): bool
    {
        $rawExcludes = trim((string)$this->getConf('exclude_namespaces'));
        if ($rawExcludes === '' || $mediaId === '') {
            return false;
        }

        $mediaNs  = getNS($mediaId);
        $excludes = array_filter(array_map(trim(...), explode(',', $rawExcludes)));

        foreach ($excludes as $ns) {
            $ns = cleanID($ns);
            if ($ns !== '' && ($mediaNs === $ns || str_starts_with($mediaNs . ':', $ns . ':'))) {
                return true;
            }
        }

        return false;
    }
}
