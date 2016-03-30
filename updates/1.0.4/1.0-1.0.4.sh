# Ethos 1.0 to 1.0.4 Updater core
DEVELOPMENT=0
DATE=`date -u`
exec 1>/dev/tty
exec 2>/dev/tty
echo "$DATE Updating ethos to version 1.0.4, May take from 15-30 minutes depending on your connection speed. You can log in on another session and type tail -f /var/log/ethos-update.log to view progress"
if [ $DEVELOPMENT = "0" ] ; then
  exec 1>>/var/log/ethos-update.log
  exec 2>>/var/log/ethos-update.log
fi
unset $BASE
ALLOWED=`cat /opt/ethos/etc/allow.file`

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
	if [ -f "/usr/local/bin/helpme" ]; then
		rm -f /usr/local/bin/helpme
	fi


#Fix the ubuntu-extras repo gpg key....because upstream is mental.
 apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 437D05B5 3E5C1192
#Install new ati drivers
  /usr/bin/apt-get update
  /usr/bin/apt-get -fy install dos2unix nitrogen tmux mc nload xfce4-terminal
  /usr/bin/apt-get --only-upgrade -yo Dpkg::Options::="--force-confnew" install \
 	base-files bind9-host ca-certificates cpio cpp-4.8 dnsutils eog \
  	fglrx-amdcccle-updates fglrx-updates fglrx-updates-core g++-4.8 gcc-4.8 \
  	gcc-4.8-base gir1.2-ibus-1.0 glib-networking glib-networking-common \
  	glib-networking-services ibus ibus-gtk ibus-gtk3 ifupdown initscripts \
  	libasan0 libatomic1 libbind9-90 libc-bin libc-dev-bin libc6 libc6-dbg \
  	libc6-dev libc6-i386 libdns100 libethereum libgcc-4.8-dev libgcrypt11 \
  	libgcrypt11-dev libgnutls-dev libgnutls-openssl27 libgnutls26 libgnutlsxx27 \
  	libgomp1 libgraphite2-3 libgtk2.0-0 libgtk2.0-bin libgtk2.0-common \
  	libgudev-1.0-0 libibus-1.0-5 libisc95 libisccc90 libisccfg90 libitm1 \
  	libjasper1 liblwres90 libnettle4 libnss3 libnss3-nssdb libnuma1 \
  	libpam-systemd libpci-dev libpci3 libpixman-1-0 libquadmath0 libssh-4 \
  	libssl-dev libssl-doc libssl1.0.0 libstdc++-4.8-dev libstdc++6 \
  	libsystemd-daemon0 libsystemd-journal0 libsystemd-login0 libtsan0 libudev1 \
  	linux-libc-dev login multiarch-support ntpdate openssh-client openssh-server \
  	openssh-sftp-server openssl passwd pciutils perl perl-base perl-modules \
  	pm-utils python-ibus ssh-askpass-gnome systemd-services sysv-rc \
  	sysvinit-utils thermald udev usbutils 
#Install new files
    rm -f /opt/ethos/bin/log /opt/ethos/bin/log
    ln -s /opt/ethos/bin/show /opt/ethos/bin/log
	ln -s /usr/bin/apt-get /usr/local/bin/apt-get-ubuntu
    cp $BASE/opt/ethos/bin/gethelp /opt/ethos/bin/gethelp
    cp $BASE/home/ethos/.conkyrc /home/ethos/.conkyrc
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
 	cp $BASE/opt/ethos/sbin/ethos-readconf /opt/ethos/sbin/ethos-readconf
 	cp $BASE/opt/ethos/sbin/ethos-set-permissions /opt/ethos/sbin/ethos-set-permissions
 	cp $BASE/opt/ethos/sbin/ethos-miner-daemon /opt/ethos/sbin/ethos-miner-daemon
 	mkdir -p /home/ethos/.config/xfce4/terminal
	cp $BASE/home/ethos/.config/xfce4/terminal/terminalrc /home/ethos/.config/xfce4/terminal/terminalrc
	cp $BASE/home/ethos/.config/autostart/ethos-fullscreen-terminal.desktop /home/ethos/.config/autostart/ethos-fullscreen-terminal.desktop
	mkdir -p /home/ethos/.config/openbox
	cp $BASE/home/ethos/.config/openbox/lxde-rc.xml /home/ethos/.config/openbox/lxde-rc.xml
	mkdir -p /home/ethos/.config/nitrogen
	cp $BASE/home/ethos/.config/nitrogen/nitrogen.cfg /home/ethos/.config/nitrogen/nitrogen.cfg
	cp $BASE/home/ethos/.config/nitrogen/bg-saved.cfg /home/ethos/.config/nitrogen/bg-saved.cfg
	mkdir -p /home/ethos/.config/lxsession/LXDE
	cp $BASE/home/ethos/.config/lxsession/LXDE/autostart /home/ethos/.config/lxsession/LXDE/autostart
	cp $BASE/home/ethos/Pictures/ethos-error.png /home/ethos/Pictures/ethos-error.png
	cp $BASE/usr/share/misc/pci.ids /usr/share/misc/pci.ids
	cp $BASE/opt/ethos/sbin/ethos-fan-daemon /opt/ethos/sbin/ethos-fan-daemon
	cp $BASE/home/ethos/.config/autostart/ethos-fan-daemon.desktop /home/ethos/.config/autostart/ethos-fan-daemon.desktop
	cp $BASE/opt/ethos/bin/amdmeminfo /opt/ethos/bin/amdmeminfo
	cp $BASE/etc/network/if-up.d/writehost /etc/network/if-up.d/writehost
	cp /usr/share/zoneinfo/UTC /etc/localtime
#Update ATITweak
	sudo rm -f /usr/local/bin/atitweak
	sudo rm -f /opt/ethos/bin/atitweak
	sudo cp $BASE/usr/local/bin/atitweak /usr/local/bin/atitweak
#Update the GPULIST
 	cp $BASE/usr/share/initramfs-tools/scripts/init-top/gpulist /usr/share/initramfs-tools/scripts/init-top/gpulist
	chown root.root /usr/share/initramfs-tools/scripts/init-top/gpulist
	chmod 755 /usr/share/initramfs-tools/scripts/init-top/gpulist
#Reinstall The Proxy
        rm -rf /opt/eth-proxy
        mkdir /opt/eth-proxy
        git clone -b 1.0.2 https://github.com/sling00/eth-proxy /opt/eth-proxy
#Check if the reboot.file exists and if not create it.
	if [ ! -f /opt/ethos/etc/reboot.file ]; then
		cp $BASE/opt/ethos/etc/reboot.file /opt/ethos/etc/reboot.file
		chmod 664 /opt/ethos/etc/reboot.file
	fi
#cleanup
	update-initramfs -u
	touch /opt/ethos/etc/.iommufixed
	rm -rf /var/cache/apt-xapian-index/*
	rm -rf /var/backups/*
	rm -rf /var/cache/apt/*
	rm -f /opt/ethos/bin/mine
	if [ -f "/usr/local/bin/helpme" ]; then
		rm -f /usr/local/bin/helpme
	fi
	rm -f /var/log/ethosupdate.log
	rm -f /opt/ethos/lib/send.php
    	rm -f /opt/ethos/bin/update.php
    	rm -f /opt/ethos/etc/motdpart
	rm -f /etc/conky/conky.conf
 	rm -f /etc/conky/conky_no_x11.conf
	rm -f /opt/ethos/etc/motdpart
 	rm -f /home/ethos/.config/autostart/ethos-custom.desktop
#Exit Clean

if [ "$ALLOWED" -eq "0" ]; then
	echo "0" > /opt/ethos/etc/allow.file
	echo "$DATE - Mining Disallowed before script start, keeping it that way."
else
	echo "1" > /opt/ethos/etc/allow.file
	echo "$DATE - Mining Allowed before script start, keeping it that way."
fi
exec 1>/dev/tty
exec 2>/dev/tty
echo "$DATE - ethOS Update on $HOSTNAME Finished, please reboot. see /var/log/ethos-update.log for details about what was updated."
exit 0
