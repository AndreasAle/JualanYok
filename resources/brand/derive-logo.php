<?php

/**
 * Derives every logo asset the app needs from the one artwork supplied.
 *
 * Out of a single PNG come four files: the wordmark trimmed to its ink, a
 * version whose text reads on dark surfaces, the mark alone for the collapsed
 * sidebar, and a square icon for the browser tab.
 */

$source = __DIR__.'/jualanyok-logo-master.png';
$out = 'public/images';

$im = imagecreatefrompng($source);
imagealphablending($im, false);
imagesavealpha($im, true);

$blank = function (int $width, int $height) {
    $canvas = imagecreatetruecolor($width, $height);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

    return $canvas;
};

/*
 * Written at the size it is actually displayed, doubled for retina and no
 * further. The artwork arrives 2000px wide; shipping that to every visitor to
 * draw a 32px-tall logo spends a third of a megabyte on nothing.
 */
$save = function ($image, string $path, int $maxWidth = 0) use ($blank): void {
    if ($maxWidth > 0 && imagesx($image) > $maxWidth) {
        $height = (int) round(imagesy($image) * $maxWidth / imagesx($image));
        $small = $blank($maxWidth, $height);
        imagecopyresampled($small, $image, 0, 0, 0, 0, $maxWidth, $height, imagesx($image), imagesy($image));
        $image = $small;
    }

    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagepng($image, $path, 9);
    echo str_pad(basename($path), 30), imagesx($image), 'x', imagesy($image),
        '  ', number_format(filesize($path) / 1024, 1), " KB", PHP_EOL;
};


// --- trim the transparent margin -----------------------------------------
$w = imagesx($im);
$h = imagesy($im);
$minX = $w;
$minY = $h;
$maxX = 0;
$maxY = 0;

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        if ((imagecolorat($im, $x, $y) >> 24 & 0x7F) < 119) {
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
        }
    }
}

$tw = $maxX - $minX + 1;
$th = $maxY - $minY + 1;

$wordmark = $blank($tw, $th);
imagecopy($wordmark, $im, 0, 0, $minX, $minY, $tw, $th);
$save($wordmark, "{$out}/jualanyok-logo.png", 720);

/**
 * True where a column holds any vivid pixel.
 *
 * The mark and "Yok" are gradients; "Jualan" is a near-black navy. That is the
 * only property that separates the three parts reliably, so both the crop
 * below and the recolour after it are decided by it.
 */
$vivid = [];

for ($x = 0; $x < $tw; $x++) {
    $vivid[$x] = false;

    for ($y = 0; $y < $th; $y++) {
        $rgba = imagecolorat($wordmark, $x, $y);

        if (($rgba >> 24 & 0x7F) > 100) {
            continue;
        }

        $max = max($rgba >> 16 & 0xFF, $rgba >> 8 & 0xFF, $rgba & 0xFF);
        $min = min($rgba >> 16 & 0xFF, $rgba >> 8 & 0xFF, $rgba & 0xFF);

        if ($max > 120 && ($max - $min) / max(1, $max) > 0.35) {
            $vivid[$x] = true;
            break;
        }
    }
}

// The tip of the swoosh: the last vivid column before the word begins.
$markWidth = (int) ($tw * 0.28);

for ($x = (int) ($tw * 0.5); $x >= 0; $x--) {
    if ($vivid[$x]) {
        $markWidth = $x + 1;
        break;
    }
}

echo "mark: 0..{$markWidth} dari {$tw}px\n";

// --- the text repainted for dark surfaces --------------------------------
/*
 * Only pixels to the right of the mark are touched, and only the dark ones.
 * Scoping it by position as well as by brightness is what keeps the swoosh
 * intact: its shaded edges are as dark as the letters, and a brightness test
 * alone would eat into the artwork.
 */
$light = $blank($tw, $th);
$repainted = 0;

for ($y = 0; $y < $th; $y++) {
    for ($x = 0; $x < $tw; $x++) {
        $rgba = imagecolorat($wordmark, $x, $y);
        $alpha = $rgba >> 24 & 0x7F;
        $r = $rgba >> 16 & 0xFF;
        $g = $rgba >> 8 & 0xFF;
        $b = $rgba & 0xFF;

        if ($alpha < 127 && $x > $markWidth && max($r, $g, $b) < 110) {
            [$r, $g, $b] = [255, 255, 255];
            $repainted++;
        }

        imagesetpixel($light, $x, $y, imagecolorallocatealpha($light, $r, $g, $b, $alpha));
    }
}

echo "piksel teks diputihkan: ", number_format($repainted), "\n";
$save($light, "{$out}/jualanyok-logo-light.png", 720);

// --- the mark on its own, square -----------------------------------------
$side = max($markWidth, $th);
$pad = (int) round($side * 0.07);
$box = $side + $pad * 2;

$mark = $blank($box, $box);
imagecopy(
    $mark,
    $wordmark,
    (int) (($box - $markWidth) / 2),
    (int) (($box - $th) / 2),
    0,
    0,
    $markWidth,
    $th,
);

/*
 * The swoosh and the "J" overlap, so no vertical cut separates them. The stray
 * is erased by colour instead — the mark contains nothing this dark.
 */
for ($y = 0; $y < $box; $y++) {
    for ($x = 0; $x < $box; $x++) {
        $rgba = imagecolorat($mark, $x, $y);

        if (($rgba >> 24 & 0x7F) > 120) {
            continue;
        }

        if (max($rgba >> 16 & 0xFF, $rgba >> 8 & 0xFF, $rgba & 0xFF) < 110) {
            imagesetpixel($mark, $x, $y, imagecolorallocatealpha($mark, 0, 0, 0, 127));
        }
    }
}

$icon = $blank(256, 256);
imagecopyresampled($icon, $mark, 0, 0, 0, 0, 256, 256, $box, $box);

$save($icon, "{$out}/jualanyok-mark.png");
imagepng($icon, 'public/favicon.png', 9);
echo str_pad('favicon.png', 30), imagesx($icon), 'x', imagesy($icon), '  ', number_format(filesize('public/favicon.png') / 1024, 1), " KB\n";
