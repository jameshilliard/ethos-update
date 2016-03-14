# Ethos 1.0.1 to 1.0.2 Updater core
unset $BASE
ALLOWED=`cat /opt/ethos/etc/allow.file`

BASE=`dirname "$BASH_SOURCE"`
# Do Work
	/opt/ethos/bin/disallow
	/opt/ethos/bin/minestop
	/usr/bin/dpkg --configure -a
#Fix the ubuntu-extras repo gpg key....because upstream is mental.
 apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 437D05B5 3E5C1192
#Install new ati drivers
 /usr/bin/apt-get update
 /usr/bin/apt-get -yo Dpkg::Options::="--force-confnew" upgrade
#Install new files
    cp $BASE/opt/ethos/bin/gethelp /opt/ethos/bin/gethelp
#Reinstall The Proxy
	rm -rf /opt/eth-proxy
	mkdir /opt/eth-proxy
	git clone -b 1.0.2 https://github.com/sling00/eth-proxy /opt/eth-proxy
	chown -R ethos.ethos /opt/eth-proxy
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
#/opt/ethos/etc
	cp $BASE/opt/ethos/etc/version /opt/ethos/etc/version
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
#Update ATITweak
	sudo rm -f /usr/local/bin/atitweak
	sudo rm -f /opt/ethos/bin/atitweak
	sudo cp $BASE/usr/local/bin/atitweak /usr/local/bin/atitweak
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
	rm -f /var/log/ethosupdate.log
	rm -f /opt/ethos/lib/send.php
    rm -f /opt/ethos/bin/update.php
    rm -f /opt/ethos/etc/motdpart
	rm -f /etc/conky/conky.conf
 	rm -f /etc/conky/conky_no_x11.conf
	rm -f /opt/ethos/etc/motdpart
 	rm -f /home/ethos/.config/autostart/ethos-custom.desktop
#Exit Clean
 	if [ $ALLOWED == 0 ]; then
	echo "0" > /opt/ethos/etc/allow.file
	echo "Mining Disallowed before script start, keeping it that way."
else
	echo "1" > /opt/ethos/etc/allow.file
	echo "Mining Allowed before script start, keeping it that way."
fi
exit 0