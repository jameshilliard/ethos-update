# Ethos 1.0.3 to 1.0.4 Updater core
DEVELOPMENT=0
exec 1>/dev/tty
exec 2>/dev/tty
echo "Updating ethos to version 1.0.4"
if [ $DEVELOPMENT = "0" ] ; then
  exec 1>>/var/log/ethos-update.log
  exec 2>>/var/log/ethos-update.log
fi
unset $BASE
ALLOWED=`cat /opt/ethos/etc/allow.file`
BASE=`dirname "$BASH_SOURCE"`
UPDATEGRUB="0"
#Check the IOMMU fix is in place, if not....Do it.
	if [ ! -f /opt/ethos/etc/.iommufixed ]; then
		cp /opt/ethos-update/updates/1.0.3/etc/default/grub /etc/default/grub
		chown root.root /etc/default/grub
		chmod 644 /etc/default/grub
		/usr/sbin/update-grub
		touch /opt/ethos/etc/.iommufixed
		UPDATEGRUB="1"
	fi
# Do Work
	if [ -f "/usr/local/bin/helpme" ]; then
		rm -f /usr/local/bin/helpme
	fi
        /opt/ethos/bin/disallow
        /opt/ethos/bin/minestop
        /usr/bin/dpkg --configure -a
		/usr/bin/apt-get -y install nitrogen tmux mc nload xfce4-terminal

# Copy the files
	if [ ! -e "/usr/local/bin/apt-get-ubuntu" ]; then
		ln -s /usr/bin/apt-get /usr/local/bin/apt-get-ubuntu
	fi
	ln -s /opt/ethos/bin/show /opt/ethos/bin/log
    cp $BASE/opt/ethos/bin/gethelp /opt/ethos/bin/gethelp
	chown -R ethos.ethos /opt/eth-proxy
	cp $BASE/home/ethos/.conkyrc /home/ethos/.conkyrc
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
	cp $BASE/opt/ethos/lib/functions.php /opt/ethos/lib/functions.php
	cp $BASE/opt/ethos/bin/gethelp /opt/ethos/bin/gethelp

	cp $BASE/opt/ethos/bin/info.php /opt/ethos/bin/info.php
	cp $BASE/opt/ethos/sbin/ethos-stats-daemon /opt/ethos/sbin/ethos-stats-daemon
	cp $BASE/opt/ethos/sbin/ethos-motd-generator /opt/ethos/sbin/ethos-motd-generator
	cp $BASE/home/ethos/.bashrc /home/ethos/.bashrc
	cp $BASE/opt/ethos/etc/version /opt/ethos/etc/version
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

#Cleanup
	if [ "$UPDATEGRUB" -eq "1" ]; then
		update-grub
	fi
	update-initramfs -u
	rm -rf /var/cache/apt-xapian-index/*
	rm -rf /var/backups/*
	rm -rf /var/cache/apt/*
	rm -f /opt/ethos/bin/mine

#Exit Clean
    if [ $ALLOWED -eq "0" ]; then
        echo "0" > /opt/ethos/etc/allow.file
        echo "Mining Disallowed before script start, keeping it that way."
	else
        echo "1" > /opt/ethos/etc/allow.file
        echo "Mining Allowed before script start, keeping it that way."
	fi
#Give the user some notice
exec 1>/dev/tty
exec 2>/dev/tty
echo "ethOS Update on $HOSTNAME Finished, please reboot. see /var/log/ethos-update.log for details about what was updated."
exit 0

