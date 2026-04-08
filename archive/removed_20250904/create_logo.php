<?php
// Script untuk membuat logo placeholder sederhana
$width = 512;
$height = 512;

// Buat image canvas
$image = imagecreatetruecolor($width, $height);

// Warna TNI
$red = imagecolorallocate($image, 220, 20, 60);      // #DC143C
$gold = imagecolorallocate($image, 255, 215, 0);     // #FFD700
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);

// Background merah
imagefill($image, 0, 0, $red);

// Circle border emas
$center_x = $width / 2;
$center_y = $height / 2;
$radius = 200;

// Draw gold circle
imagefilledellipse($image, $center_x, $center_y, $radius * 2, $radius * 2, $gold);
imagefilledellipse($image, $center_x, $center_y, ($radius - 20) * 2, ($radius - 20) * 2, $red);

// Star symbol (simplified)
$star_points = array(
    $center_x, $center_y - 60,      // top
    $center_x + 20, $center_y - 20, // top right
    $center_x + 60, $center_y - 20, // right
    $center_x + 30, $center_y + 10, // bottom right
    $center_x + 40, $center_y + 50, // bottom
    $center_x, $center_y + 20,      // bottom center
    $center_x - 40, $center_y + 50, // bottom left
    $center_x - 30, $center_y + 10, // left bottom
    $center_x - 60, $center_y - 20, // left
    $center_x - 20, $center_y - 20  // top left
);

imagefilledpolygon($image, $star_points, 5, $gold);

// Add text "TNI"
$font_size = 36;
$font_file = null; // Use default font

// Calculate text position
$text = "TNI";
$text_box = imagettfbbox($font_size, 0, $font_file, $text);
$text_width = $text_box[4] - $text_box[0];
$text_height = $text_box[1] - $text_box[7];
$text_x = ($width - $text_width) / 2;
$text_y = $center_y + 100;

// Add text if font is available, otherwise use imagestring
if ($font_file && file_exists($font_file)) {
    imagettftext($image, $font_size, 0, $text_x, $text_y, $white, $font_file, $text);
} else {
    // Use built-in font
    $font_built_in = 5; // Large built-in font
    $text_width_builtin = imagefontwidth($font_built_in) * strlen($text);
    $text_height_builtin = imagefontheight($font_built_in);
    $text_x_builtin = ($width - $text_width_builtin) / 2;
    $text_y_builtin = $center_y + 80;
    
    imagestring($image, $font_built_in, $text_x_builtin, $text_y_builtin, $text, $white);
}

// Save as PNG
$filename = __DIR__ . '/assets/images/logo.png';
imagepng($image, $filename);
imagedestroy($image);

echo "Logo placeholder created: $filename\n";
echo "Size: {$width}x{$height}px\n";
echo "Colors: TNI Red (#DC143C) and Gold (#FFD700)\n";
?>
