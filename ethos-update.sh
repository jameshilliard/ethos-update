#!/bin/bash
# ethOS Update Script 
# LICENSE AGREEMENT
#
# File Version See $VERSION Variable (c) 2016 Dale Chapman, sling00@gmail.com (“Author”).
# 
# By using this file, you agree to the following:
#
# This file has been licensed to gpuShack for the exclusive use and distribution as part of ethOS. All other previous licenses 
# of this file have been revoked. This license does not expire and allows for any modification, distribution, and/or derivative work 
# by gpuShack and by the Author. This license extends to gpuShack’s owners, operators, officers, and contractors, where 
# applicable.
#
# The Author expressly forbids and revokes usage of this file, as well as any previous iterations of this file, in any 
# operating system other than ethOS. Any fork of ethOS, third party or otherwise, may not use this file without express written 
# permission from the Author.
#
# Personal Use
#
# End users may modify and use this script for personal use, but may not redistribute or include in a larger work, in whole, or
# in part, without express written permission from the Author.
#
# Version History
#
# v1.x - EthOS Release
# v.1 - Development Release
#
# Portions derived from previous work by Author
#
# Red Goat License (v) 1.0
#
# This file is released under the "Small Goat with Red Eyes" license. Breaking the above license agreement will result in a 
# small goat with red eyes visiting you while you sleep.

UPDATESERVER="curl -sL https://github.com/sling00/ethos-update/tarball/master.tar.gz | tar xz"
DEVELOPMENT=1 
LOG="/var/log/ethos-update.log"
CURLARGS="-f -s -S -k"
SCRIPT_NEW_VERSION="$(curl $CURLARGS https://raw.githubusercontent.com/sling00/ethos-update/master/version/version)"
SCRIPT_VERSION="0.10"
ETHOSVERSION=$(cat /opt/ethos/etc/version)
if [ $DEVELOPMENT = "0" ] ; then
  exec 1>>/var/log/ethos-update.log
  exec 2>>/var/log/ethos-update.log
fi
function f.updatescript() {
		echo "Checking if ethos-update is up to date........"
 	
	if [ $SCRIPT_NEW_VERSION \> $SCRIPT_VERSION ]; then
		echo "Getting latest version of ethos-update"
		download /tmp/ethos-update.tar $UPDATESERVER/ethos-update/ethos-update.tar
		tar xvf /tmp/ethos-update.tar -C /opt/ethos > /var/log/ethos-update.log
		echo "Updated to latest version, relaunching."
		rm /tmp/ethos-update.tar
		PROGNAME=$0
#		( shift; "$PROGNAME" $* ) | grep $1
		exit 0
	else 
		echo "Script up to date"
	fi
}


function f.1.0-1.1() {
 echo "version detected as $ETHOSVERSION. test"
}

f.updatescript

if [ /opt/ethos/etc/version = "1.0" ]; then
	f.1.0-1.1
fi