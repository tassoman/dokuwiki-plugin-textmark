<?php

namespace dokuwiki\plugin\textmark\classes;

/**
 * Resolves the configured font to a usable local TrueType/OpenType file
 *
 * Accepted sources, in order:
 *  - a media file, given as a DokuWiki id (e.g. "fonts:myfont.ttf"), which is
 *    the file a user uploaded through the media manager;
 *  - a path inside the plugin: bare filename ("myfont.ttf") or relative path
 *    ("fonts/myfont.ttf");
 *  - an absolute path, only when it resolves inside the DokuWiki tree or the
 *    plugin directory.
 *
 * Anything else is rejected, so a configuration value can never make the
 * renderer read an arbitrary file. No value is ever evaluated as code.
 */
class FontResolver
{
    /** File signatures of the font formats GD and ImageMagick both accept */
    private const MAGIC = [
        "\x00\x01\x00\x00", // TrueType
        "\x00\x00\x01\x00", // TrueType (alternative)
        'OTTO',             // CFF/OpenType
        'true',             // macOS TrueType
        'ttcf',             // TrueType collection
    ];

    private const EXTENSIONS = ['ttf', 'otf'];

    private string $pluginDir;
    private string $defaultFont;

    public function __construct(string $pluginDir, string $defaultFont)
    {
        $this->pluginDir = rtrim($pluginDir, '/');
        $this->defaultFont = $defaultFont;
    }

    /**
     * Absolute path of the font to render with
     *
     * Falls back to the bundled font when the configured value is empty or
     * unusable. Returns null when not even the bundled font is available.
     */
    public function resolve(): ?string
    {
        $candidate = $this->resolveConfigured($this->configuredValue());
        if ($candidate !== null) {
            return $candidate;
        }

        return $this->isUsableFont($this->defaultFont) ? $this->defaultFont : null;
    }

    private function configuredValue(): string
    {
        global $conf;

        return trim((string)($conf['plugin']['textmark']['font_file'] ?? ''));
    }

    private function resolveConfigured(string $configured): ?string
    {
        // A NUL byte would make realpath() throw, and no legitimate path has one
        if ($configured === '' || str_contains($configured, "\0")) {
            return null;
        }

        $candidates = [];

        // media manager upload, addressed by DokuWiki id. A slash-separated
        // value is accepted as well, since that is how the media manager shows
        // the path to the user.
        if (!str_starts_with($configured, '/') && $this->hasMediaExtension($configured)) {
            $mediaId = str_replace('/', ':', $configured);
            $mediaFile = mediaFN($mediaId);
            if ($mediaFile !== '') {
                $candidates[] = $mediaFile;
            }
        }

        // file bundled with the plugin
        $relative = ltrim($configured, '/');
        if ($relative !== '' && !str_contains($relative, '..')) {
            $candidates[] = $this->pluginDir . '/' . $relative;
        }

        // absolute path, but only within the allowed roots
        if (str_starts_with($configured, '/')) {
            $candidates[] = $configured;
        }

        foreach ($candidates as $candidate) {
            if ($this->isAllowedPath($candidate) && $this->isUsableFont($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function hasMediaExtension(string $value): bool
    {
        $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        return in_array($ext, self::EXTENSIONS, true);
    }

    /**
     * Confine the path to the plugin directory or the DokuWiki tree
     */
    private function isAllowedPath(string $path): bool
    {
        if (str_contains($path, "\0")) {
            return false;
        }

        $real = realpath($path);
        if ($real === false) {
            return false;
        }

        $realPlugin = realpath($this->pluginDir);
        if ($realPlugin !== false && $this->isWithin($real, $realPlugin)) {
            return true;
        }

        $realDoku = realpath(DOKU_INC);
        return $realDoku !== false && $this->isWithin($real, $realDoku);
    }

    private function isWithin(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, '/') . '/');
    }

    /**
     * Check extension, readability and the binary signature
     */
    private function isUsableFont(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONS, true)) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $head = (string)fread($handle, 4);
        fclose($handle);

        return in_array($head, self::MAGIC, true);
    }
}
