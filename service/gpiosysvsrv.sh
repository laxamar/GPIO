#!/bin/bash

# Reference systemd shell script
# Jacques Amar
# (c) 2004-2021 Amar Micro Inc.
#
# description:	Starts, stops and saves iptables firewall
#

# Fixed Files
PHP=/usr/bin/php
CODEPATH=/usr/local/GPIOSysV/service/
CONFDIR=/etc/gpiosysv.d/
CONFFILE=${CONFDIR}/gpiosysvsrv.conf
RUNFILE=/run/gpiosysvsrv.pid


# Config File overrides defaults
if [ -e $CONFFILE ]; then
    . $CONFFILE
fi

if [ -e $RUNFILE ]; then
  GPIOSYSVPID=$(< $RUNFILE)
fi

function panic() {
  [ -n "${GPIOSYSVPID}" ] && kill -SIGKILL ${GPIOSYSVPID}
  rm -f ${RUNFILE}
}

function stop() {
  echo "Killing PID ${GPIOSYSVPID}"
  [ -n "${GPIOSYSVPID}" ] && kill -SIGTERM ${GPIOSYSVPID}
  # kill -SIGTERM ${GPIOSYSVPID}
}

function start() {
  # echo "${PHP} ${CODEPATH}gpiosysvsrv.php"
  cd ${CODEPATH}
  ${PHP} ${CODEPATH}gpiosysvsrv.php
}

function status() {
  if  [ -n "$GPIOSYSVPID" ]; then
    ps -q ${GPIOSYSVPID} -O comm=
  fi
}

case "$1" in
    start)
	stop
	start
	RETVAL=$?
	;;
    stop)
	stop
	RETVAL=$?
	;;
    restart)
	stop
	start
	RETVAL=$?
	;;
    status)
	status
	RETVAL=$?
	;;
    panic)
	panic
	RETVAL=$?
	;;
    *)
	echo $"Usage: $0 {start|stop|restart|status|panic}"
	exit 1
	;;
esac


exit $RETVAL