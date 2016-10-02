#!/bin/bash
# usage: editiso.sh , then if desired enter chroot and sudo ethos-update and run cleanup scripts as normal. exit when done, umount  /mnt/proc /mnt /sys /mnt/dev and /mnt.

if [ ! -z "$1" ] ; then
  if [ "$1" = "cleanup" ]; then
    umount /mnt/dev || echo "error, unable to umount /mnt/dev"
    umount /mnt/sys || echo "error, unable to umount /mnt/sys"
    umount /mnt/proc || echo "error, unable to umount /mnt/proc"
    umount /mnt || echo "error, couldnt automatically unmount image, make sure you arent in this directory somewhere" 
    e2fsck -fv /dev/loop0
  fi  
  sudo losetup -o 1048576 /dev/loop0 $1
  sudo fsck -fv /dev/loop0
  sudo mount /dev/loop0 /mnt
  mount -t proc proc /mnt/proc/
  mount -t sysfs sys /mnt/sys/
  mount -o bind /dev /mnt/dev/
  echo "Image mounted on /mnt, enter chroot?"
  read ENTERCHROOT
  if [ $ENTERCHROT = "yes|y" ];then
    chroot /mnt	
  else
    exit 0
  fi
else
  echo "This script accepts exactly 1 variable, an image name, image must be uncompressed in raw format."
  echo "usage: editiso.sh ethos-1.1.1.iso"
  echo "cleanup: editiso.sh cleanup"
  exit 0
fi

