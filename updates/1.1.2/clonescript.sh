#!/bin/bash
SOURCE="/source/ethos-1.0.4.iso"
OUT=${ls /dev/sd* | egrep -o '(/dev/.d.)' | grep -v "/dev/sda" | grep -v "/dev/sdb" | sort | uniq | xargs}

for i in $OUT do;
