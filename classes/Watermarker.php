<?php

namespace dokuwiki\plugin\textmark\classes;

/**
 * Base class for watermark renderers
 *
 * Holds the normalized plugin configuration and the helpers shared by the
 * concrete rendering backends.
 */
abstract class Watermarker
{
    protected const DEFAULTS = [
        'text' => '',
        'fontSizePercent' => 5,
        'minFontSize' => 16,
        'textColor' => '#FFFFFF',
        'opacity' => 50,
        'position' => 'bottom-right',
        'angle' => 0.0,
        'border' => false,
        'borderColor' => '#000000',
        'borderWidth' => 2,
        'padding' => 15,
    ];

    /** Hard lower bound for the computed font size, in points */
    public const ABSOLUTE_MIN_FONT_SIZE = 16;

    protected array $config;
    protected string $fontPath;

    public function __construct(array $config, string $fontPath)
    {
        $this->config = array_merge(self::DEFAULTS, $config);
        $this->fontPath = $fontPath;
    }

    /**
     * Short engine identifier, also used as part of the cache key
     */
    abstract public function getName(): string;

    /**
     * Render the watermark onto $src and write the result to $dest
     *
     * @param string $src source image path
     * @param string $dest destination image path
     * @param int $imageType one of the IMAGETYPE_* constants
     * @return bool true when $dest was written successfully
     */
    abstract public function apply(string $src, string $dest, int $imageType): bool;

    protected function text(): string
    {
        return (string)$this->config['text'];
    }

    /**
     * Font size in points, derived from the image dimensions
     *
     * The configured value is a percentage of the image's shorter side, so the
     * watermark scales with the picture regardless of orientation, and never
     * drops below the 16pt floor.
     */
    protected function fontSize(int $imgWidth, int $imgHeight): float
    {
        $percent = max(1, min(100, (int)$this->config['fontSizePercent']));
        $min = max(self::ABSOLUTE_MIN_FONT_SIZE, (int)$this->config['minFontSize']);

        $shorterSide = max(1, min($imgWidth, $imgHeight));
        $size = (int)round($shorterSide * $percent / 100);

        return $this->fitToImage((float)max($min, $size), $imgWidth, $imgHeight);
    }

    /**
     * Shrink the size until the text fits inside the image
     *
     * The percentage above only considers the image's shorter side, so long
     * text or a narrow panorama would run past the edges. The glyph box is
     * measured and scaled down to the space left between the padding, which
     * wins over the minimum size: a legible clipped word is not legible.
     */
    private function fitToImage(float $size, int $imgWidth, int $imgHeight): float
    {
        $margin = (2 * $this->padding()) + $this->borderWidth();
        $availableWidth = $imgWidth - $margin;
        $availableHeight = $imgHeight - $margin;

        if ($availableWidth < 1 || $availableHeight < 1) {
            return $size;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            [$boxWidth, $boxHeight] = $this->measureText($size);
            if ($boxWidth <= 0 || $boxHeight <= 0) {
                return $size;
            }

            $ratio = min($availableWidth / $boxWidth, $availableHeight / $boxHeight);
            if ($ratio >= 1) {
                return max(1.0, $size);
            }

            $size *= $ratio;
            if ($size < 1) {
                return 1.0;
            }
        }

        return max(1.0, $size);
    }

    /**
     * Pixel size of the rotated glyph box at the given point size
     *
     * Each renderer measures with its own backend: GD is not always available
     * (it is commonly enabled for the CLI but not for the web server), so the
     * base class cannot assume imagettfbbox() exists.
     *
     * @return float[] [width, height]; zeros when the backend cannot measure
     */
    protected function measureText(float $size): array
    {
        return [0.0, 0.0];
    }

    protected function padding(): int
    {
        return (int)$this->config['padding'];
    }

    protected function position(): string
    {
        return (string)$this->config['position'];
    }

    /**
     * Rotation angle normalized to 0-360 degrees
     *
     * Negative input is interpreted as a counter-clockwise rotation, so -33
     * becomes 327. Both GD and ImageMagick render the two identically.
     */
    protected function angle(): float
    {
        $angle = fmod((float)$this->config['angle'], 360.0);
        if ($angle < 0) {
            $angle += 360.0;
        }
        return $angle;
    }

    protected function useBorder(): bool
    {
        return (bool)$this->config['border'];
    }

    /**
     * Border thickness in pixels; zero disables the border
     */
    protected function borderWidth(): int
    {
        if (!$this->useBorder()) {
            return 0;
        }

        return max(0, min(50, (int)$this->config['borderWidth']));
    }

    protected function opacity(): int
    {
        return max(0, min(100, (int)$this->config['opacity']));
    }

    protected function textColor(): string
    {
        return self::normalizeHex((string)$this->config['textColor'], 'FFFFFF');
    }

    protected function borderColor(): string
    {
        return self::normalizeHex((string)$this->config['borderColor'], '000000');
    }

    /**
     * @return int[] [red, green, blue]
     */
    protected function rgb(string $hex): array
    {
        [$r, $g, $b] = sscanf(self::normalizeHex($hex, 'FFFFFF'), '%02x%02x%02x');
        return [(int)$r, (int)$g, (int)$b];
    }

    /**
     * Reduce a colour setting to a bare 6-digit hex triplet
     *
     * Anything that is not a plain hex colour is discarded in favour of the
     * supplied fallback, so a malformed setting can never reach a renderer.
     */
    protected static function normalizeHex(string $hex, string $fallback): string
    {
        $hex = ltrim(trim($hex), '#');
        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) === 1 ? $hex : $fallback;
    }
}
