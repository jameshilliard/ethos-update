
<?php

$seplen = 2;

@ob_end_clean();

require_once('/opt/ethos/lib/functions.php');

$stats = get_stats();
$maxlen = 0;

foreach ($stats as $k => $v) {
    $len = strlen($k);
    if ($len > $maxlen)
        $maxlen = $len;
}

$headerbuffer = '';
$spacebuffer = '';
for ($i=0; $i<$maxlen+$seplen; $i++) {
    if ($i<$maxlen)
        $headerbuffer .= '-';
    $spacebuffer .= ' ';
}


function writestat($k, $v) {
    global $maxlen, $seplen, $spacebuffer;

    $len = strlen($k);
    $spacesize = $maxlen + $seplen - $len;

    // Handle multi-line values
    if (strpos($v, "\n") !== false) {
        $v = str_replace("\n", "\n".$spacebuffer, $v);
    }

    echo $k . substr($spacebuffer, -1 * $spacesize) . $v . "\n";
}

echo "\n";
echo "Stats dump:\n";
echo "\n";

writestat("Data", "Value");
writestat($headerbuffer, $headerbuffer);

ksort($stats);
foreach ($stats as $k => $v) {
    writestat($k, $v);
}

echo "\n";
