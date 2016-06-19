#!/bin/bash
WORKDIR="updates/$1/"

rm -rf $WORKDIR/wip
rm -rf $WORKDIR/README.md
rm -rf $WORKDIR/dist
rm -f $WORKDIR/newupdate.sh
rm -f $WORKDIR/update.sh
rm -f $WORKDIR/cleanup.sh
rm -f $WORKDIR/clonescript.sh
rm -f $WORKDIR/opt/ethos/sbin/ethos-fan-daemon
rm -f $WORKDIR/etc/init/ethos-fan-daemon.conf
rm -f $WORKDIR/home/ethos/.config/autostart/ethos-fan-daemon.desktop

