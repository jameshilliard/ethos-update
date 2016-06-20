#!/bin/bash
NEWVERSION="$1"
if [ -z "$NEWVERSION" ]; then
	echo "Requires a version as command line argument."
	exit 1
fi
git clone -b "$NEWVERSION" https://github.com/sling00/eth-proxy ./eth-proxy
if [ $? = "0" ] && [ -d ./eth-proxy ]; then
mkdir -p proxy-updates/"$NEWVERSION"
tar cjpf proxy-updates/"$NEWVERSION"/eth-proxy.tar.bz2 ./eth-proxy
else
echo "Git failed, not tarring."
fi
if [ $? = "0" ] && [ -f "proxy-updates/$NEWVERSION/eth-proxy.tar.bz2" ]; then
git add proxy-updates/"$NEWVERSION"/eth-proxy.tar.bz2
fi
if [ $? = "0" ] && [ -d ./eth-proxy ]; then
rm -rf ./eth-proxy
fi


