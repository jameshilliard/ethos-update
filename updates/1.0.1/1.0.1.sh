#!/bin/bash
# Ethos 1.0 to 1.0.1 Updater core
BASE=`dirname "$BASH_SOURCE"`
# Do Work

    cp $BASE/etc/default/grub /etc/default/grub
    chown root.root /etc/default/grub
    chmod 644 /etc/default/grub
	rm -f /etc/conky/conky.conf
 	rm -f /etc/conky/conky_no_x11.conf

 	