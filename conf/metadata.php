<?php

/**
 * @author Tassoman <tassoman@gmail.com>
 * @license AGPLv3
 */

$meta['watermark_text']     = array('string');
$meta['font_file']          = array('string');
$meta['font_size']          = array('numeric', '_min' => 1, '_max' => 100);
$meta['min_font_size']      = array('numeric', '_min' => 16, '_max' => 500);
$meta['text_color']         = array('string', '_pattern' => '/^#[0-9a-fA-F]{6}$/');
$meta['text_opacity']       = array('numeric', '_min' => 0, '_max' => 100);
$meta['position']           = array('multichoice', 
  '_choices' => array(
    'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center')
);
$meta['angle']              = array('numeric', '_min' => -360, '_max' => 360);
$meta['border']             = array('onoff');
$meta['border_color']       = array('string', '_pattern' => '/^#[0-9a-fA-F]{6}$/');
$meta['border_width']       = array('numeric', '_min' => 0, '_max' => 50);
$meta['padding']            = array('numeric', '_min' => 0);
$meta['min_width']          = array('numeric', '_min' => 0);
$meta['exclude_namespaces'] = array('string');
