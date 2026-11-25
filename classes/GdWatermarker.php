<?php

namespace dokuwiki\plugin\textmark\classes;

/**
 * Renders the watermark using PHP's GD extension
 */
class GdWatermarker extends Watermarker
{
    public function getName(): string
    {
        return 'gd';
    }

    public function apply(string $src, string $dest, int $imageType): bool
    {
        if (!function_exists('imagettftext')) {
            return false;
        }

        $img = $this->load($src, $imageType);
        if (!$img) {
            return false;
        }

        // fontSize() returns points; GD measures in pixels at 96 DPI, where one
        // point is 0.75px. Converting keeps both engines at the same visual size.
        $fontSize = $this->fontSize(imagesx($img), imagesy($img)) * 0.75;
        $angle = $this->angle();
        $text = $this->text();

        $bbox = imagettfbbox($fontSize, $angle, $this->fontPath, $text);
        if ($bbox === false) {
            imagedestroy($img);
            return false;
        }

        [$x, $y] = $this->baseline($bbox, imagesx($img), imagesy($img));
        [$originX, $originY, $width, $height] = $this->layerBounds($bbox, $x, $y);

        // The watermark is painted translucent on its own canvas and composited
        // once. Painting straight onto the image would blend the text against
        // the already-blended border instead of against the picture, which turns
        // translucent text muddy instead of see-through.
        $layer = $this->createLayer($width, $height);
        if ($layer === false) {
            imagedestroy($img);
            return false;
        }

        $localX = $x - $originX;
        $localY = $y - $originY;
        $alpha = (int)round(127 - ($this->opacity() / 100 * 127));
        $textColor = $this->allocateColor($layer, $this->textColor(), $alpha);
        $borderColor = $this->allocateColor($layer, $this->borderColor(), $alpha);

        foreach ($this->outlineOffsets($this->borderWidth()) as [$dx, $dy]) {
            imagettftext(
                $layer,
                $fontSize,
                $angle,
                $localX + $dx,
                $localY + $dy,
                $borderColor,
                $this->fontPath,
                $text
            );
        }

        imagettftext($layer, $fontSize, $angle, $localX, $localY, $textColor, $this->fontPath, $text);

        imagealphablending($img, true);
        imagesavealpha($img, true);
        imagecopy($img, $layer, $originX, $originY, 0, 0, $width, $height);
        imagedestroy($layer);

        $saved = $this->save($img, $dest, $imageType);
        imagedestroy($img);

        return $saved;
    }

    protected function measureText(float $size): array
    {
        if (!function_exists('imagettfbbox')) {
            return [0.0, 0.0];
        }

        $box = @imagettfbbox($size * 0.75, $this->angle(), $this->fontPath, $this->text());
        if ($box === false) {
            return [0.0, 0.0];
        }

        $xs = [$box[0], $box[2], $box[4], $box[6]];
        $ys = [$box[1], $box[3], $box[5], $box[7]];

        return [(float)(max($xs) - min($xs)), (float)(max($ys) - min($ys))];
    }

    /**
     * Canvas rectangle the watermark is painted on
     *
     * Kept just large enough for the glyph outline, so the compositing copy
     * costs time proportional to the watermark rather than to the whole image.
     *
     * @param int[] $bbox bounding box as returned by imagettfbbox()
     * @return int[] [x, y, width, height]
     */
    private function layerBounds(array $bbox, int $x, int $y): array
    {
        $xs = [$bbox[0], $bbox[2], $bbox[4], $bbox[6]];
        $ys = [$bbox[1], $bbox[3], $bbox[5], $bbox[7]];

        $margin = $this->borderWidth() + 2;
        $left = (int)floor(min($xs)) - $margin;
        $top = (int)floor(min($ys)) - $margin;
        $right = (int)ceil(max($xs)) + $margin;
        $bottom = (int)ceil(max($ys)) + $margin;

        return [$x + $left, $y + $top, max(1, $right - $left), max(1, $bottom - $top)];
    }

    /**
     * Fully transparent canvas for the watermark
     *
     * Alpha blending stays disabled: every pass paints the same translucency,
     * so the glyph has to replace the outline rather than blend with it.
     *
     * @return \GdImage|false
     */
    private function createLayer(int $width, int $height)
    {
        $layer = imagecreatetruecolor($width, $height);
        if ($layer === false) {
            return false;
        }

        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagefilledrectangle($layer, 0, 0, $width, $height, imagecolorallocatealpha($layer, 0, 0, 0, 127));

        return $layer;
    }

    /**
     * @return \GdImage|false
     */
    private function load(string $src, int $imageType)
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG => @imagecreatefrompng($src),
            IMAGETYPE_GIF => @imagecreatefromgif($src),
            default => false,
        };
    }

    private function save($img, string $dest, int $imageType): bool
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($img, $dest, 90),
            IMAGETYPE_PNG => imagepng($img, $dest, 6),
            IMAGETYPE_GIF => imagegif($img, $dest),
            default => false,
        };
    }

    /**
     * Offsets covering a filled disc of the given radius
     *
     * Drawn underneath the text, these paint a uniform border of the requested
     * thickness. The centre offset is skipped: the text itself goes there.
     *
     * @return array<int, int[]> list of [dx, dy]
     */
    private function outlineOffsets(int $thickness): array
    {
        if ($thickness < 1) {
            return [];
        }

        $offsets = [];
        for ($dx = -$thickness; $dx <= $thickness; $dx++) {
            for ($dy = -$thickness; $dy <= $thickness; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }
                if (($dx * $dx) + ($dy * $dy) > $thickness * $thickness) {
                    continue;
                }
                $offsets[] = [$dx, $dy];
            }
        }

        return $offsets;
    }

    /**
     * @param int[] $bbox bounding box as returned by imagettfbbox()
     * @return int[] [x, y] baseline coordinates for imagettftext()
     */
    private function baseline(array $bbox, int $imgWidth, int $imgHeight): array
    {
        $xs = [$bbox[0], $bbox[2], $bbox[4], $bbox[6]];
        $ys = [$bbox[1], $bbox[3], $bbox[5], $bbox[7]];

        $minX = min($xs);
        $minY = min($ys);
        $boxWidth = max($xs) - $minX;
        $boxHeight = max($ys) - $minY;
        $padding = $this->padding();

        switch ($this->position()) {
            case 'top-left':
                $left = $padding;
                $top = $padding;
                break;
            case 'top-right':
                $left = $imgWidth - $boxWidth - $padding;
                $top = $padding;
                break;
            case 'bottom-left':
                $left = $padding;
                $top = $imgHeight - $boxHeight - $padding;
                break;
            case 'center':
                $left = ($imgWidth - $boxWidth) / 2;
                $top = ($imgHeight - $boxHeight) / 2;
                break;
            case 'bottom-right':
            default:
                $left = $imgWidth - $boxWidth - $padding;
                $top = $imgHeight - $boxHeight - $padding;
                break;
        }

        return [(int)round($left - $minX), (int)round($top - $minY)];
    }

    /**
     * @param \GdImage $img
     */
    private function allocateColor($img, string $hex, int $alpha): int
    {
        [$r, $g, $b] = $this->rgb($hex);
        return imagecolorallocatealpha($img, $r, $g, $b, $alpha);
    }
}
