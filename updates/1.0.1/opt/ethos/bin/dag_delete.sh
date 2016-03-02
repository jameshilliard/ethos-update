#!/bin/bash
for i in `ls -t1 /home/ethos/.ethash | tail -n +3`; do rm -f /home/ethos/.ethash/$i; done

tail -n 200 /var/run/output. > /var/run/output.temp
cat /var/run/output.temp > /var/run/output.

tail -n 200 /var/run/log.file > /var/run/log.temp
cat /var/run/log.temp > /var/run/log.file

tail -n 200 /var/run/proxy.output > /var/run/proxy.temp  
cat /var/run/proxy.temp > /var/run/proxy.output

/usr/bin/sensors | grep -Poi -m1 "(?<=\+)(.*)(?=...C\s)" > /var/run/cputemp.file


