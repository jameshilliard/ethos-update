#!/usr/bin/php
<?php

$time = time();

$hashrate = array();

$total_hash = 0.0 + trim(`tail -100 /var/run/output. | grep PoWhash | cut -d" " -f10 | tail -30 | awk '{ sum += $1/1000/1000; n++ } END { if (n > 0) print sum / n; }' | awk '{printf "%.2f", $1}'`);

$log_hash = "";

$log_temp = trim(`export DISPLAY=:0 && /usr/local/bin/atitweak -s | grep -Poi "(?<=temperature.)(.*)(?= C)" |  awk '{printf "%.0f ", $1}' | xargs`);
$log_hash = $total_hash;

file_put_contents("/var/run/hash.file",$total_hash);
file_put_contents("/var/run/temp.file",$log_temp);

echo "temps: $log_temp\n";

?>
