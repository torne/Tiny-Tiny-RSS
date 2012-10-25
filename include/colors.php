<?php
### RGB >> HSL
function _color_rgb2hsl($rgb) {
  $r = $rgb[0]; $g = $rgb[1]; $b = $rgb[2];
  $min = min($r, min($g, $b)); $max = max($r, max($g, $b));
  $delta = $max - $min; $l = ($min + $max) / 2; $s = 0;
  if ($l > 0 && $l < 1) {
    $s = $delta / ($l < 0.5 ? (2 * $l) : (2 - 2 * $l));
  }
  $h = 0;
  if ($delta > 0) {
    if ($max == $r && $max != $g) $h += ($g - $b) / $delta;
    if ($max == $g && $max != $b) $h += (2 + ($b - $r) / $delta);
    if ($max == $b && $max != $r) $h += (4 + ($r - $g) / $delta);
    $h /= 6;
  } return array($h, $s, $l);
}

### HSL >> RGB
function _color_hsl2rgb($hsl) {
  $h = $hsl[0]; $s = $hsl[1]; $l = $hsl[2];
  $m2 = ($l <= 0.5) ? $l * ($s + 1) : $l + $s - $l*$s;
  $m1 = $l * 2 - $m2;
  return array(_color_hue2rgb($m1, $m2, $h + 0.33333),
               _color_hue2rgb($m1, $m2, $h),
               _color_hue2rgb($m1, $m2, $h - 0.33333));
}

### Helper function for _color_hsl2rgb().
function _color_hue2rgb($m1, $m2, $h) {
  $h = ($h < 0) ? $h + 1 : (($h > 1) ? $h - 1 : $h);
  if ($h * 6 < 1) return $m1 + ($m2 - $m1) * $h * 6;
  if ($h * 2 < 1) return $m2;
  if ($h * 3 < 2) return $m1 + ($m2 - $m1) * (0.66666 - $h) * 6;
  return $m1;
}

### Convert a hex color into an RGB triplet.
function _color_unpack($hex, $normalize = false) {
  if (strlen($hex) == 4) {
    $hex = $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
  } $c = hexdec($hex);
  for ($i = 16; $i >= 0; $i -= 8) {
    $out[] = (($c >> $i) & 0xFF) / ($normalize ? 255 : 1);
  } return $out;
}

### Convert an RGB triplet to a hex color.
function _color_pack($rgb, $normalize = false) {
  foreach ($rgb as $k => $v) {
    $out |= (($v * ($normalize ? 255 : 1)) << (16 - $k * 8));
  }return '#'. str_pad(dechex($out), 6, 0, STR_PAD_LEFT);
}

function rgb2hsl($arr) {
	$r = $arr[0];
	$g = $arr[1];
	$b = $arr[2];

   $var_R = ($r / 255);
   $var_G = ($g / 255);
   $var_B = ($b / 255);

   $var_Min = min($var_R, $var_G, $var_B);
   $var_Max = max($var_R, $var_G, $var_B);
   $del_Max = $var_Max - $var_Min;

   $v = $var_Max;

   if ($del_Max == 0) {
      $h = 0;
      $s = 0;
   } else {
      $s = $del_Max / $var_Max;

      $del_R = ( ( ( $max - $var_R ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
      $del_G = ( ( ( $max - $var_G ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
      $del_B = ( ( ( $max - $var_B ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;

      if      ($var_R == $var_Max) $h = $del_B - $del_G;
      else if ($var_G == $var_Max) $h = ( 1 / 3 ) + $del_R - $del_B;
      else if ($var_B == $var_Max) $h = ( 2 / 3 ) + $del_G - $del_R;

      if ($H < 0) $h++;
      if ($H > 1) $h--;
   }

   return array($h, $s, $v);
}

function hsl2rgb($arr) {
	$h = $arr[0];
	$s = $arr[1];
	$v = $arr[2];

    if($s == 0) {
        $r = $g = $B = $v * 255;
    } else {
        $var_H = $h * 6;
        $var_i = floor( $var_H );
        $var_1 = $v * ( 1 - $s );
        $var_2 = $v * ( 1 - $s * ( $var_H - $var_i ) );
        $var_3 = $v * ( 1 - $s * (1 - ( $var_H - $var_i ) ) );

        if       ($var_i == 0) { $var_R = $v     ; $var_G = $var_3  ; $var_B = $var_1 ; }
        else if  ($var_i == 1) { $var_R = $var_2 ; $var_G = $v      ; $var_B = $var_1 ; }
        else if  ($var_i == 2) { $var_R = $var_1 ; $var_G = $v      ; $var_B = $var_3 ; }
        else if  ($var_i == 3) { $var_R = $var_1 ; $var_G = $var_2  ; $var_B = $v     ; }
        else if  ($var_i == 4) { $var_R = $var_3 ; $var_G = $var_1  ; $var_B = $v     ; }
        else                   { $var_R = $v     ; $var_G = $var_1  ; $var_B = $var_2 ; }

        $r = $var_R * 255;
        $g = $var_G * 255;
        $B = $var_B * 255;
    }
    return array($r, $g, $B);
}

?>
