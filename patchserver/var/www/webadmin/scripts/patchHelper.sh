#!/bin/bash

# Apache Username
if [ "$(getent passwd www-data)" != '' ]; then
	www_user=www-data
elif [ "$(getent passwd apache)" != '' ]; then
	www_user=apache
fi

case $1 in

getSSLstatus)
if [ -f "/etc/ssl/certs/ssl-cert-snakeoil.pem" ]; then
	issuer=$(openssl x509 -issuer -noout -in /etc/ssl/certs/ssl-cert-snakeoil.pem | awk '{print $NF}')
	subject=$(openssl x509 -subject -noout -in /etc/ssl/certs/ssl-cert-snakeoil.pem | awk '{print $NF}')
fi
if [ -f "/etc/pki/tls/certs/localhost.crt" ]; then
	issuer=$(openssl x509 -issuer -noout -in /etc/pki/tls/certs/localhost.crt | awk '{print $NF}')
	subject=$(openssl x509 -subject -noout -in /etc/pki/tls/certs/localhost.crt | awk '{print $NF}')
fi
if [ "${issuer}" != "${subject}" ]; then
	echo "true"
fi
;;

backupDB)
if [ ! -d "/var/appliance/backup" ]; then
	mkdir -p "/var/appliance/backup"
	chown ${www_user} "/var/appliance/backup"
fi
datestamp=$(date '+%s')
cp /var/appliance/db/patch_v1.sqlite /tmp/patch_v1_${datestamp}.sqlite
gzip /tmp/patch_v1_${datestamp}.sqlite
mv /tmp/patch_v1_${datestamp}.sqlite.gz /var/appliance/backup/patch_v1-${datestamp}.sqlite.gz
chown ${www_user} /var/appliance/backup/patch_v1-${datestamp}.sqlite.gz
echo "true"
if [ "$(xpath 2>&1 | grep options)" != '' ]; then
	retention=$(xpath -e "//retention/text()" /var/appliance/conf/appliance.conf.xml 2>/dev/null)
else
	retention=$(xpath /var/appliance/conf/appliance.conf.xml "//retention/text()" 2>/dev/null)
fi
if [ "${retention}" != '' ]; then
	for i in $(ls -t1 /var/appliance/backup/*.sqlite.gz | tail -n+$((1+${retention}))); do
		rm -f "${i}"
	done
fi
;;

uploadDB)
# $2: filename
if [ ! -d "/var/appliance/backup" ]; then
	mkdir -p "/var/appliance/backup"
	chown ${www_user} "/var/appliance/backup"
fi
mv "/tmp/${2}" "/var/appliance/backup/${2}"
chown ${www_user} "/var/appliance/backup/${2}"
;;

listBackups)
ls -1 /var/appliance/backup/*.sqlite.gz 2>/dev/null
;;

restoreDB)
# $2: backup
tmpfile=/tmp/$(echo $(basename "${2}") | sed -e 's/.sqlite.gz/.sqlite/')
cp ${2} ${tmpfile}.gz
if [ ${?} -ne 0 ]; then
	rm -f ${tmpfile}*
	exit 1
fi
gunzip ${tmpfile}.gz
if [ ${?} -ne 0 ]; then
	rm -f ${tmpfile}*
	exit 1
fi
mv ${tmpfile} /var/appliance/db/patch_v1.sqlite
if [ ${?} -ne 0 ]; then
	rm -f ${tmpfile}*
	exit 1
fi
chown ${www_user}:${www_user} /var/appliance/db/patch_v1.sqlite
if [ ${?} -eq 0 ]; then
	echo "true"
fi
;;

getSchedule)
echo $(crontab -l 2>/dev/null | grep "${0}" | awk '{print $5}')
;;

setSchedule)
days="${2}"
tmpfile=$(mktemp /tmp/crontab.XXXXXX)
crontab -l 2>/dev/null | grep -v "${0}" > ${tmpfile}
echo "0 0 * * ${2} /var/www/html/webadmin/scripts/patchHelper.sh > /dev/null 2>&1" >> ${tmpfile}
crontab ${tmpfile}
rm ${tmpfile}
;;

delSchedule)
tmpfile=$(mktemp /tmp/crontab.XXXXXX)
crontab -l 2>/dev/null | grep -v "${0}" > ${tmpfile}
crontab ${tmpfile}
rm ${tmpfile}
;;

esac