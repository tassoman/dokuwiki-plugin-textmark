<?php

namespace dokuwiki\plugin\textmark\classes;

/**
 * Renders the watermark by shelling out to ImageMagick's convert binary
 */
class ImageMagickWatermarker extends Watermarker
{
    private const GRAVITY_MAP = [
        'top-left' => 'NorthWest',
        'top-right' => 'NorthEast',
        'bottom-left' => 'SouthWest',
        'bottom-right' => 'SouthEast',
        'center' => 'Center',
    ];

    private string $binary;

    public function __construct(array $config, string $fontPath, string $binary)
    {
        parent::__construct($config, $fontPath);
        $this->binary = $binary;
    }

    public function getName(): string
    {
        return 'im';
    }

    public function apply(string $src, string $dest, int $imageType): bool
    {
        $geometry = @getimagesize($src);
        if ($geometry === false) {
            return false;
        }

        $gravity = self::GRAVITY_MAP[$this->position()] ?? 'SouthEast';
        $offset = $gravity === 'Center' ? '+0+0' : sprintf('+%d+%d', $this->padding(), $this->padding());

        // GD and ImageMagick rotate in opposite directions, so mirror the angle
        $angle = fmod(360 - $this->angle(), 360);
        $text = $this->escapeText($this->text());

        // The watermark is drawn on its own transparent layer and faded as a
        // whole, then composited once. Drawing straight onto the image would
        // blend the text against the already-blended border instead of against
        // the picture, which turns translucent text muddy instead of see-through.
        $layer = [
            '-size',
            escapeshellarg($geometry[0] . 'x' . $geometry[1]),
            'xc:none',
            '-font',
            escapeshellarg($this->fontPath),
            '-pointsize',
            escapeshellarg((string)$this->fontSize($geometry[0], $geometry[1])),
            '-gravity',
            escapeshellarg($gravity),
        ];

        $borderWidth = $this->borderWidth();
        if ($borderWidth > 0) {
            // Outline first: ImageMagick centres the stroke on the glyph edge,
            // so half of it would cover the fill. Stroking with twice the
            // requested width and no visible fill leaves exactly borderWidth
            // outside, then the solid glyph is drawn on top.
            $layer[] = '-fill';
            $layer[] = escapeshellarg('#' . $this->borderColor());
            $layer[] = '-stroke';
            $layer[] = escapeshellarg('#' . $this->borderColor());
            $layer[] = '-strokewidth';
            $layer[] = escapeshellarg((string)($borderWidth * 2));
            $layer[] = '-annotate';
            $layer[] = escapeshellarg($this->annotateGeometry($angle, $offset));
            $layer[] = escapeshellarg($text);
        }

        $layer[] = '-fill';
        $layer[] = escapeshellarg('#' . $this->textColor());
        $layer[] = '-stroke';
        $layer[] = escapeshellarg('none');
        $layer[] = '-annotate';
        $layer[] = escapeshellarg($this->annotateGeometry($angle, $offset));
        $layer[] = escapeshellarg($text);

        $opacity = $this->opacity();
        if ($opacity < 100) {
            $layer[] = '-channel';
            $layer[] = 'A';
            $layer[] = '-evaluate';
            $layer[] = 'multiply';
            $layer[] = escapeshellarg(sprintf('%.4f', $opacity / 100));
            $layer[] = '+channel';
        }

        $parts = array_merge(
            [escapeshellarg($this->binary), escapeshellarg($src), escapeshellarg('(')],
            $layer,
            [escapeshellarg(')'), '-compose', 'over', '-composite', '-quality', '90', escapeshellarg($dest)]
        );

        $output = [];
        $returnVar = 1;
        @exec(implode(' ', $parts) . ' 2>&1', $output, $returnVar);

        return $returnVar === 0 && file_exists($dest) && filesize($dest) > 0;
    }

    protected function measureText(float $size): array
    {
        $text = $this->escapeText($this->text());
        if ($text === '') {
            return [0.0, 0.0];
        }

        // Mirror the angle as apply() does, so the measured box matches the
        // rotation that will actually be drawn.
        $angle = fmod(360 - $this->angle(), 360);
        $label = sprintf(
            '%s -font %s -pointsize %s label:%s -format %%wx%%h info:',
            escapeshellarg($this->binary),
            escapeshellarg($this->fontPath),
            escapeshellarg((string)$size),
            escapeshellarg($text)
        );

        $output = [];
        $returnVar = 1;
        @exec($label . ' 2>/dev/null', $output, $returnVar);
        if ($returnVar !== 0 || !isset($output[0])) {
            return [0.0, 0.0];
        }

        if (!preg_match('/^(\d+)x(\d+)$/', trim((string)$output[0]), $m)) {
            return [0.0, 0.0];
        }

        // label: renders unrotated, so swap the axes for right angles
        $width = (float)$m[1];
        $height = (float)$m[2];
        $angle = fmod($angle, 180);
        if ($angle > 45 && $angle < 135) {
            return [$height, $width];
        }

        return [$width, $height];
    }

    /**
     * Neutralize the escape sequences ImageMagick interprets in -annotate text
     *
     * '%' starts a format escape, so it has to be doubled. Everything is passed
     * as a single shell-quoted argv element, so no other escaping is needed.
     */
    private function escapeText(string $text): string
    {
        return str_replace('%', '%%', $text);
    }

    /**
     * Build the -annotate geometry: "{angle}x{+-x}{+-y}"
     *
     * The 'x' separator is mandatory. Without it ImageMagick reads the angle as
     * a horizontal offset and does not rotate the text at all.
     */
    private function annotateGeometry(float $angle, string $offset): string
    {
        return sprintf('%gx%s', $angle, $offset);
    }
}
