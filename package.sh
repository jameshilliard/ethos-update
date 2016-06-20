#!/bin/bash
NEWVERSION="$1"
if [ -z "$NEWVERSION" ]; then
	echo "Requires a version as command line argument."
	exit 1
fi
git clone -b "$NEWVERSION" https://github.com/sling00/eth-proxy ./eth-proxy
if [ $? = "0" ] && [ -d ./eth-proxy ]; then
tar cjpf updates/"$NEWVERSION"/eth-proxy.tar.bz2 ./eth-proxy
else
echo "Git failed, not tarring."
fi
if [ $? = "0" ] && [ -d ./eth-proxy ]; then
rm -rf ./eth-proxy
fi


