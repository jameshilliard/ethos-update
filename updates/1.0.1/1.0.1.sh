#!/bin/bash
# Ethos 1.0 to 1.0.1 Updater core
unset $BASE
BASE=`dirname "$BASH_SOURCE"`
# Do Work
	/opt/ethos/bin/disallow
	/opt/ethos/bin/minestop
	/usr/bin/dpkg --configure -a
	if [ -f "/home/ethos/remote" ]; then
		mv /home/ethos/remote /home/ethos/remote.conf
	fi
	mv /home/ethos/local /home/ethos/local.conf
    cp $BASE/etc/default/grub /etc/default/grub
    chown root.root /etc/default/grub
    chmod 644 /etc/default/grub
#Copy new files
	#Copy new profiles & link apt-get to apt-get-ubuntu because of apt-get potential for breakage
	ln -s /usr/bin/apt-get /usr/local/bin/apt-get-ubuntu
	cp $BASE/root/.bashrc /root/.bashrc
	cp $BASE/root/.profile /root/.profile
	cp $BASE/home/ethos/.bashrc /home/ethos/.bashrc
	cp $BASE/etc/profile /etc/profile
	chmod 0644 /home/ethos/.bashrc
	chown root.root /root/.bashrc
	chmod 0644 /root/.bashrc
	chown root.root /root/.profile
	#etc/init
	cp $BASE/etc/init/ethos-atisetup.conf /etc/init/ethos-atisetup.conf
	cp $BASE/etc/init/ethos-miner-daemon.conf /etc/init/ethos-miner-daemon.conf
	cp $BASE/etc/init/ethos-stats-daemon.conf /etc/init/ethos-stats-daemon.conf
	cp $BASE/etc/init/ethos-overheat-daemon.conf /etc/init/ethos-overheat-daemon.conf
	cp $BASE/etc/init/ethos-set-permissions.conf /etc/init/ethos-set-permissions.conf
	cp $BASE/etc/init/ethos-watchdog.conf /etc/init/ethos-watchdog.conf
	chown -R root.root /etc/init/ethos-*
	#/opt/ethos/bin
	cp $BASE/opt/ethos/bin/allow /opt/ethos/bin/allow
	cp $BASE/opt/ethos/bin/disallow /opt/ethos/bin/disallow
	cp $BASE/opt/ethos/bin/helpme /opt/ethos/bin/helpme
	cp $BASE/opt/ethos/bin/log /opt/ethos/bin/log
	cp $BASE/opt/ethos/bin/minestop /opt/ethos/bin/minestop
	rm -f /opt/ethos/bin/minestart /opt/ethos/bin/minertimer
	ln -s /opt/ethos/bin/allow /opt/ethos/bin/minestart
	cp $BASE/opt/ethos/bin/putconf /opt/ethos/bin/putconf
	cp $BASE/opt/ethos/bin/r /opt/ethos/bin/r
	cp $BASE/opt/ethos/bin/restart-proxy /opt/ethos/bin/restart-proxy
	cp $BASE/opt/ethos/bin/info.php /opt/ethos/bin/info.php
	cp $BASE/opt/ethos/bin/update /opt/ethos/bin/update
	cp $BASE/opt/ethos/bin/show /opt/ethos/bin/show
	cp $BASE/opt/ethos/bin/dag_delete.sh /opt/ethos/bin/dag_delete.sh
	#/opt/ethos/lib
	mkdir -p /opt/ethos/lib
	cp $BASE/opt/ethos/lib/functions.php /opt/ethos/lib/functions.php
	cp $BASE/opt/ethos/lib/putconf.php /opt/ethos/lib/putconf.php
	#/opt/ethos/sbin
	mkdir -p /opt/ethos/sbin
	cp $BASE/opt/ethos/sbin/atisetup /opt/ethos/sbin/atisetup
	cp $BASE/opt/ethos/sbin/start-conky /opt/ethos/sbin/start-conky
	cp $BASE/opt/ethos/sbin/ethos-motd-generator /opt/ethos/sbin/ethos-motd-generator
	cp $BASE/opt/ethos/sbin/ethos-getmessage /opt/ethos/sbin/ethos-getmessage
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
 	cp $BASE/opt/ethos/sbin/ethos-miner-daemon /opt/ethos/sbin/ethos-miner-daemon
#Update the GPULIST
 	cp $BASE/usr/share/initramfs-tools/scripts/init-top/gpulist /usr/share/initramfs-tools/scripts/init-top/gpulist
	chown root.root /usr/share/initramfs-tools/scripts/init-top/gpulist
	chmod 755 /usr/share/initramfs-tools/scripts/init-top/gpulist
#Check if the reboot.file exists and if not create it.
	if [ ! -f /opt/ethos/etc/reboot.file ]; then
		cp $BASE/opt/ethos/etc/reboot.file /opt/ethos/etc/reboot.file
		chmod 664 /opt/ethos/etc/reboot.file
	fi
#cleanup
	rm -f /opt/ethos/lib/send.php
    rm -f /opt/ethos/bin/update.php
    rm -f /opt/ethos/etc/motdpart
	rm -f /etc/conky/conky.conf
 	rm -f /etc/conky/conky_no_x11.conf
	rm -f /opt/ethos/etc/motdpart
 	rm -f /home/ethos/.config/autostart/ethos-custom.desktop
#Reinstall The Proxy
	rm -rf /opt/eth-proxy
	mkdir /opt/eth-proxy
	git clone https://github.com/sling00/eth-proxy /opt/eth-proxy
	chown -R ethos.ethos /opt/eth-proxy
#exec these at the end
 	/usr/sbin/update-initramfs -u
 	/usr/sbin/update-grub
#Return the console output...
exec 1>/dev/tty
exec 2>/dev/tty
echo "$HOSTNAME Finished"
exit 0

 	