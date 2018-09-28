#!/bin/bash
# This script controls the flow of the Patch Server installation

log "Starting Patch Server Installation"

apt_install() {
	if [[ $(apt-cache -n search ^${1}$ | awk '{print $1}' | grep ^${1}$) == "$1" ]] && [[ $(dpkg -s $1 2>&- | awk '/Status: / {print $NF}') != "installed" ]]; then
		apt-get -qq -y install $1 >> $logFile 2>&1
		if [[ $? -ne 0 ]]; then
			exit 1
		fi
	fi
}

yum_install() {
	if yum -q list $1 &>- && [[ $(rpm -qa $1) == "" ]] ; then
		yum install $1 -y -q >> $logFile 2>&1
		if [[ $? -ne 0 ]]; then
			exit 1
		fi
	fi
}

# Install required software
if [[ $(which apt-get 2>&-) != "" ]]; then
	log "Updating package lists..."
	apt-get -q update >> $logFile
	apt_install php5-sqlite
	apt_install php-sqlite3
	apt_install php5-curl
	apt_install php-curl
	apt_install libxml-xpath-perl
	www_user=www-data
	www_service=apache2
elif [[ $(which yum 2>&-) != "" ]]; then
	yum_install php-sqlite
	yum_install perl-XML-XPath
	www_user=apache
	www_service=httpd
fi

# Install sqlite database
if [ ! -f /var/appliance/db/patch_v1.sqlite ]; then
	mkdir -p /var/appliance/db
	cp ./resources/patch_v1.sqlite /var/appliance/db/patch_v1.sqlite >> $logFile
	chown -R $www_user:$www_user /var/appliance/db
fi

# Configure php
if [ -f "/etc/php/7.2/apache2/php.ini" ]; then
	php_ini=/etc/php/7.2/apache2/php.ini
elif [ -f "/etc/php/7.0/apache2/php.ini" ]; then
	php_ini=/etc/php/7.0/apache2/php.ini
elif [ -f "/etc/php5/apache2/php.ini" ]; then
	php_ini=/etc/php5/apache2/php.ini
elif [ -f "/etc/php.ini" ]; then
	php_ini=/etc/php.ini
else
	log "Error: Failed to locate php.ini"
	exit 1
fi
sed -i 's/^allow_url_include =.*/allow_url_include = On/' $php_ini

# Update the webadmin interface
cp -R ./resources/html/* /var/www/html/ >> $logFile
# Add endpoint to the legacy default http site
if grep -q '/srv/SUS/html' /etc/apache2/sites-enabled/000-default.conf 2>/dev/null || grep -q '/srv/SUS/html' /etc/httpd/conf/httpd.conf 2>/dev/null; then
	ln -s /var/www/html/v1.php /srv/SUS/html/v1.php 2>/dev/null
else
	rm -f /srv/SUS/html/v1.php
fi
# Insert menu link in header.php
if ! grep -q "patchTitles.php" /var/www/html/webadmin/inc/header.php; then
	if [[ -f "/var/www/html/webadmin/fonts/netsus-icons.ttf" ]]; then
		sed -i '/$pageURI == "sharing.php"/i\
				<li id="patch" class="<?php echo ($conf->getSetting("patch") == "enabled" ? ($pageURI == "patchTitles.php" ? "active" : "") : "hidden"); ?>"><a href="patchTitles.php"><span class="netsus-icon icon-patch marg-right"></span>Patch Definitions</a></li>' /var/www/html/webadmin/inc/header.php
	else
		sed -i '/$pageURI == "SUS.php"/i\
				<li id="patch" class="<?php echo ($conf->getSetting("patch") == "enabled" ? ($pageURI == "patchTitles.php" ? "active" : "") : "hidden"); ?>"><a href="patchTitles.php"><span class="glyphicon glyphicon-refresh marg-right"></span>Patch Definitions</a></li>' /var/www/html/webadmin/inc/header.php
	fi
fi
# Insert database control in settings.php
sed -i 's:<p>Patch</p>:<p>Patch Definitions</p>:' /var/www/html/webadmin/settings.php
if ! grep -q "patchSettings.php" /var/www/html/webadmin/settings.php; then
	sed -i 's:<strong>Shares</strong>:<strong>Services</strong>:g' /var/www/html/webadmin/settings.php
	if grep -q "AFP.php" /var/www/html/webadmin/settings.php; then
		sed -i '/<a href="AFP.php">/i\
				<a href="patchSettings.php">\
					<p><img src="images/settings/PatchManagement.png" alt="Patch"></p>\
					<p>Patch</p>\
				</a>\
			</div>\
			<!-- /Column -->\
			<!-- Column -->\
			<div class="col-xs-3 col-sm-2 settings-item">' /var/www/html/webadmin/settings.php
	fi
	if grep -q "sharingSettings.php" /var/www/html/webadmin/settings.php; then
		sed -i '/<a href="sharingSettings.php">/i\
					<a href="patchSettings.php">\
						<p><img src="images/settings/PatchManagement.png" alt="Patch Definitions"></p>\
						<p>Patch Definitions</p>\
					</a>\
				</div>\
				<!-- /Column -->\
				<!-- Column -->\
				<div class="col-xs-3 col-sm-2 settings-item">' /var/www/html/webadmin/settings.php
	fi
fi
# Insert patch source in dashboard.php
sed -i "s/Patch External Source/Patch Definitions/" /var/www/html/webadmin/dashboard.php
if ! grep -q "Patch Definitions" /var/www/html/webadmin/dashboard.php; then
	sed -i '1,/panel panel-default panel-main/ {/panel panel-default panel-main/i\
				<div class="panel panel-default panel-main <?php echo ($conf->getSetting("showpatch") == "false" ? "hidden" : ""); ?>">\
					<div class="panel-heading">\
						<strong>Patch Definitions</strong>\
					</div>\
					<?php\
					include "inc/dbConnect.php";\
					if (isset($pdo)) {\
						$title_count = $pdo->query("SELECT COUNT(id) FROM titles")->fetchColumn();\
					}\
\
					function patchExec($cmd) {\
						return shell_exec("sudo /bin/sh scripts/patchHelper.sh ".escapeshellcmd($cmd)." 2>&1");\
					}\
\
					if ($conf->getSetting("kinobi_url") != "" && $conf->getSetting("kinobi_token") != "") {\
						$ch = curl_init();\
						curl_setopt($ch, CURLOPT_URL, $conf->getSetting("kinobi_url"));\
						curl_setopt($ch, CURLOPT_POST, true);\
						curl_setopt($ch, CURLOPT_POSTFIELDS, "token=".$conf->getSetting("kinobi_token"));\
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\
						$result = curl_exec($ch);\
						curl_close ($ch);\
						$token = json_decode($result, true);\
					}\
					?>\
\
					<div class="panel-body">\
						<div class="row">\
							<!-- Column -->\
							<div class="col-xs-4 col-md-2 dashboard-item">\
								<a href="patchTitles.php">\
									<p><img src="images/settings/PatchManagement.png" alt="Patch Management"></p>\
								</a>\
							</div>\
							<!-- /Column -->\
\
							<!-- Column -->\
							<div class="col-xs-4 col-md-2">\
								<div class="bs-callout bs-callout-default">\
									<h5><strong>SSL Enabled</strong></h5>\
									<span class="text-muted"><?php echo (trim(patchExec("getSSLstatus")) == "true" ? "Yes" : "No") ?></span>\
								</div>\
							</div>\
							<!-- /Column -->\
\
							<!-- Column -->\
							<div class="col-xs-4 col-md-2">\
								<div class="bs-callout bs-callout-default">\
									<h5><strong>Hostname</strong></h5>\
									<span class="text-muted" style="word-break: break-all;"><?php echo $_SERVER["HTTP_HOST"]."/v1.php"; ?></span>\
								</div>\
							</div>\
							<!-- /Column -->\
\
							<div class="clearfix visible-xs-block visible-sm-block"></div>\
\
							<!-- Column -->\
							<div class="col-xs-4 col-md-2 visible-xs-block visible-sm-block"></div>\
							<!-- /Column -->\
\
							<!-- Column -->\
							<div class="col-xs-4 col-md-2">\
								<div class="bs-callout bs-callout-default">\
									<h5><strong>Number of Titles</strong></h5>\
									<span class="text-muted"><?php echo $title_count; ?></span>\
								</div>\
							</div>\
							<!-- /Column -->\
<?php if (isset($token["expires"])) { ?>\
\
							<!-- Column -->\
							<div class="col-xs-4 col-md-2">\
								<div class="bs-callout bs-callout-default">\
									<h5><strong>Subscription Expires</strong></h5>\
									<span class="text-muted"><?php echo date("Y-m-d H:i:s", $token["expires"]); ?></span>\
								</div>\
							</div>\
							<!-- /Column -->\
<?php } ?>\
						</div>\
						<!-- /Row -->\
					</div>\
				</div>\

}' /var/www/html/webadmin/dashboard.php
fi

# Prevent writes to the webadmin's database helper script
chown root:root /var/www/html/webadmin/scripts/patchHelper.sh >> $logFile
chmod a-wr /var/www/html/webadmin/scripts/patchHelper.sh >> $logFile
chmod u+rx /var/www/html/webadmin/scripts/patchHelper.sh >> $logFile

# Allow the webadmin from webadmin to invoke the database helper script
sed -i '/scripts\/patchHelper.sh/d' /etc/sudoers
sed -i 's/^\(Defaults *requiretty\)/#\1/' /etc/sudoers
if [[ $(grep "^#includedir /etc/sudoers.d" /etc/sudoers) == "" ]] ; then
	echo "#includedir /etc/sudoers.d" >> /etc/sudoers
fi
if ! grep -q 'scripts/patchHelper.sh' /etc/sudoers.d/webadmin; then
	echo "$www_user ALL=(ALL) NOPASSWD: /bin/sh scripts/patchHelper.sh *" >> /etc/sudoers.d/webadmin
	chmod 0440 /etc/sudoers.d/webadmin
fi

# Restart apache
log "Restarting apache..."
service $www_service restart >> $logFile 2>&1

log "OK"

log "Finished deploying the patch server"

exit 0
