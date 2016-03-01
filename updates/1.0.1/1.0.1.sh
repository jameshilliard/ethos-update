#!/bin/bash
# Ethos 1.0 to 1.0.1 Updater core

#etc
	chown -R ethos.ethos /opt/ethos/etc/
#lib
	cp $BASE/opt/ethos/lib/functions.php /opt/ethos/lib/functions.php
	cp $BASE/opt/ethos/lib/putconf.php /opt/ethos/lib/putconf.php
#sbin
	mkdir -p /opt/ethos/sbin
	cp $BASE/opt/ethos/sbin/atisetup /opt/ethos/sbin/atisetup
	cp $BASE/opt/ethos/sbin/ethos-terminal /opt/ethos/sbin/ethos-terminal
	cp $BASE/opt/ethos/sbin/ethos-overheat-daemon /opt/ethos/sbin/ethos-overheat-daemon
	cp $BASE/opt/ethos/sbin/ethos-stats-daemon /opt/ethos/sbin/ethos-stats-daemon
	cp $BASE/opt/ethos/sbin/ethos-overclock /opt/ethos/sbin/ethos-overclock
	cp $BASE/opt/ethos/sbin/ethos-postlogin /opt/ethos/sbin/ethos-postlogin
	cp $BASE/opt/ethos/sbin/ethos-prelogin /opt/ethos/sbin/ethos-prelogin
	cp $BASE/opt/ethos/sbin/ethos-watchdog /opt/ethos/sbin/ethos-watchdog
	cp $BASE/opt/ethos/sbin/ethos-overclock /opt/ethos/sbin/ethos-overclock
	cp $BASE/opt/ethos/sbin/ethos-readconf /opt/ethos/sbin/ethos-readconf
	cp $BASE/opt/ethos/sbin/ethos-set-permissions /opt/ethos/sbin/ethos-set-permissions
	cp $BASE/opt/ethos/sbin/ethos-miner-daemon /opt/ethos/sbin/ethos-miner-daemo

#Run these first
	/usr/sbin/dpkg -configure -a
#Update the proxy
	rm -rf /opt/eth-proxy
	mkdir /opt/eth-proxy
	git clone https://github.com/sling00/eth-proxy /opt/eth-proxy
	chown -R ethos.ethos /opt/eth-proxy
#Cleanup
	rm -f /etc/conky/conky.conf
 	rm -f /etc/conky/conky_no_x11.conf
#Run these last
	/usr/sbin/update-grub
	/usr/sbin/update-initramfs -u

#Return the console output to the screen
	exec 1>/dev/tty
	exec 2>/dev/tty
	echo "$HOSTNAME Finished"