<?php

/**
 * @author Tassoman <tassoman@gmail.com>
 * @license AGPLv3
 */

// Empty watermark_text means "not configured": the plugin then generates
// "(c){year} {wiki title}", or the base URL when the title is empty.
$conf['watermark_text']     = '';

// Font used to render the watermark. Leave empty to use fonts/default.ttf.
// Accepts a media manager id ("fonts:myfont.ttf"), a path relative to this
// plugin ("fonts/myfont.ttf") or an absolute path inside the DokuWiki tree.
$conf['font_file']          = '';

// Font size as a percentage of the image's shorter side (1-100)
$conf['font_size']          = 5;
// Lower bound in points, so small thumbnails stay legible
$conf['min_font_size']      = 8;

$conf['text_color']         = '#FFFFFF';
$conf['text_opacity']       = 50; // 0 (invisible) to 100 (solid)
$conf['position']           = 'center';
$conf['angle']              = 0;  // degrees, clockwise; negative input wraps to 360-value

// Border drawn around the text, replacing the old drop shadow
$conf['border']             = 1;  // 1 = enabled, 0 = disabled
$conf['border_color']       = '#000000';
$conf['border_width']       = 2;  // thickness in pixels, 1-50

$conf['padding']            = 15;
$conf['min_width']          = 150;
$conf['exclude_namespaces'] = 'wiki,playground'; // Comma-separated namespaces to skip
