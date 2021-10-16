#!/bin/bash
# Install GPIOSysVSrv as a systemd service to fixed locations. Modify as needed
# @author Jacques Amar
# @copyright 2019-2021 Amar Micro Inc.
PACKAGE_PATH=/usr/local/gpiosysv/
LOG_PATH=/var/log/

touch ${LOG_PATH}gpiosysv.log

# install version checking (Only Redhat/CentOS and Ubuntu currently
if [ -f /usr/bin/apt ]; then
  apt install lsb-release
fi
if [ -f /bin/yum ]; then
  yum install redhat-lsb-core
fi
# Update logrotate
case "`/usr/bin/lsb_release -si`" in
  Ubuntu)
    echo 'This is Ubuntu Linux'
    chown syslog:syslog ${LOG_PATH}lax*.log
    chmod g+rw ${LOG_PATH}gpiosysv.log
    ;;
  CentOS | RedHatEnterpriseServer | RedHat )
    chmod g+rw ${LOG_PATH}gposysv.log
    ;;
       *) echo 'This is something else' ;;
esac

# make sure the service directory is here
if [ ! -d service ]; then
  echo "This install must be run from the main directory of 'vendor/laxamar/gpiosysv' inside composer"
fi
# copy service files to $PACKAGE_PATH
mkdir -p ${PACKAGE_PATH}
cp -u service/* ${PACKAGE_PATH}
cd ${PACKAGE_PATH}
# composer update should not be used here
chmod +x ${PACKAGE_PATH}gpiosysvsrv.sh
systemctl enable ${PACKAGE_PATH}gpiosysv.service
