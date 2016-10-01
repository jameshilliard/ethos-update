DRIVER=`/opt/ethos/sbin/ethos-readconf driver`
if [ "$DRIVER" = "amdgpu" ]; then
	export LD_LIBRARY_PATH="/usr/lib/x86_64-linux-gnu/amdgpu-pro/"
else
	export AMDAPPSDKROOT="/opt/AMDAPPSDK-3.0"
	export LD_LIBRARY_PATH="/opt/AMDAPPSDK-3.0/lib/x86_64/"
fi
