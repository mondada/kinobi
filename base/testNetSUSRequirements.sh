#!/bin/bash

logNoNewLine "Checking for NetSUS 4.1.0 or later..."

if [ ! -e /var/www/html/webadmin/theme/bootstrap.css ]; then
	log "Error: Failed to to detect valid NetSUS installation."
	exit 1
fi

log "OK"

exit 0
