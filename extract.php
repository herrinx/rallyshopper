<?php
$tarfile = 'rallyshopper.tar.gz';
$destdir = '.';

if (!file_exists($tarfile)) {
    die("Archive not found: $tarfile");
}

$phar = new PharData($tarfile);
$phar->extractTo($destdir, null, true);

unlink($tarfile);
echo "RallyShopper extracted successfully!";
?>