<?php
/**
 * 画像最適化（見た目を保ったままファイルサイズ削減）
 * JPEG: 品質82で再エンコード / PNG: 最大圧縮で再保存
 * 元より小さくなった場合のみ採用。src の階層構造を dst に複製。
 * usage: php optimize.php <srcDir> <dstDir>
 */
$src = rtrim($argv[1], '\\/');
$dst = rtrim($argv[2], '\\/');

$totIn = 0; $totOut = 0; $n = 0; $saved = 0;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
$rows = array();
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    $rel  = ltrim(str_replace($src, '', $path), '\\/');
    // /sp/ と wp-content は対象外（移行対象外）
    if (preg_match('#(^|[\\\\/])sp[\\\\/]#i', $rel)) continue;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, array('jpg','jpeg','png'), true)) continue;

    $inSize = filesize($path);
    $target = $dst . DIRECTORY_SEPARATOR . $rel;
    @mkdir(dirname($target), 0777, true);

    $im = null;
    if ($ext === 'png') {
        $im = @imagecreatefrompng($path);
        if (!$im) { copy($path, $target); continue; }
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $tmp = $target . '.tmp';
        imagepng($im, $tmp, 9);
    } else {
        $im = @imagecreatefromjpeg($path);
        if (!$im) { copy($path, $target); continue; }
        $tmp = $target . '.tmp';
        imagejpeg($im, $tmp, 82);
    }
    imagedestroy($im);

    $outSize = file_exists($tmp) ? filesize($tmp) : PHP_INT_MAX;
    if ($outSize < $inSize) {
        rename($tmp, $target);
        $saved += ($inSize - $outSize);
    } else {
        @unlink($tmp);
        copy($path, $target); // 元の方が小さければそのまま
        $outSize = $inSize;
    }
    $totIn += $inSize; $totOut += $outSize; $n++;
    if ($inSize - $outSize > 30000) {
        $rows[] = sprintf('  %-40s %6.0f KB -> %6.0f KB  (-%d%%)', substr($rel,0,40), $inSize/1024, $outSize/1024, round(100*($inSize-$outSize)/$inSize));
    }
}
// 非画像（gif/css/js等）もコピーして完全な配布セットにする
$rii2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
foreach ($rii2 as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    $rel  = ltrim(str_replace($src, '', $path), '\\/');
    if (preg_match('#(^|[\\\\/])sp[\\\\/]#i', $rel)) continue;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, array('jpg','jpeg','png','html'), true)) continue;
    $target = $dst . DIRECTORY_SEPARATOR . $rel;
    @mkdir(dirname($target), 0777, true);
    if (!file_exists($target)) copy($path, $target);
}

usort($rows, function($a,$b){ return strcmp($a,$b); });
echo "Top reductions (>30KB saved):\n" . implode("\n", array_slice($rows, 0, 25)) . "\n\n";
printf("images optimized: %d\n", $n);
printf("total: %.1f MB -> %.1f MB  (saved %.1f MB, -%d%%)\n",
    $totIn/1048576, $totOut/1048576, $saved/1048576, $totIn>0?round(100*$saved/$totIn):0);
