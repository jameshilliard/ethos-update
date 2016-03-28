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
# Do Work
	if [ -f "/usr/local/bin/helpme" ]; then
		rm -f /usr/local/bin/helpme
	fi
        /opt/ethos/bin/disallow
        /opt/ethos/bin/minestop
        /usr/bin/dpkg --configure -a
		/usr/bin/apt-get -y install nitrogen tmux mc nload xfce4-terminal

# Copy the files

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
	cp $BASE/home/ethos/.config/nitrogen/nitrogen.conf /home/ethos/.config/nitrogen/nitrogen.conf
	cp $BASE/home/ethos/.config/nitrogen/bg-saved.conf /home/ethos/.config/nitrogen/bg-saved.conf
	mkdir -p /home/ethos/.config/lxsession/LXDE
	cp $BASE/home/ethos/.config/lxsession/LXDE/autostart /home/ethos/.config/lxsession/LXDE/autostart
	cp $BASE/home/ethos/Pictures/ethos-error.png /home/ethos/Pictures/ethos-error.png
	cp $BASE/usr/share/misc/pci.ids /usr/share/misc/pci.ids

#Cleanup
	rm -f /opt/ethos/bin/mine
	sudo update-initramfs -u
	
#Exit Clean
        if [ $ALLOWED == 0 ]; then
        echo "0" > /opt/ethos/etc/allow.file
        echo "Mining Disallowed before script start, keeping it that way."
else
        echo "1" > /opt/ethos/etc/allow.file
        echo "Mining Allowed before script start, keeping it that way."
fi
exec 1>/dev/tty
exec 2>/dev/tty
echo "ethOS Update on $HOSTNAME Finished, please reboot. see /var/log/ethos-update.log for details about what was updated."
exit 0

