#!/bin/bash

if [ -e /var/www/html/webadmin/dashboard.php ]; then
	logNoNewLine "Checking for NetSUS 4.1.0 or later..."

	if ! grep -q "version-number-text" /var/www/html/webadmin/inc/header.php; then
		log "Error: Failed to to detect valid NetSUS installation."
		exit 1
	fi

	log "OK"
fi

exit 0
