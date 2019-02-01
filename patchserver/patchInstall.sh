#!/bin/bash
# This script controls the flow of the Patch Server installation

netsusdir=/var/appliance
installdir=/var/www

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
	log "Installing dependencies..."
	apt_install apache2-utils
	apt_install libapache2-mod-php5
	apt_install libapache2-mod-php
	apt_install php5-curl
	apt_install php-curl
	apt_install php5-mysqlnd
	apt_install php-mysql
	apt_install php5-sqlite
	apt_install php-sqlite3
	apt_install ufw
	www_user=www-data
	www_service=apache2
elif [[ $(which yum 2>&-) != "" ]]; then
	log "Installing dependencies..."
	yum_install mod_ssl
	yum_install php
	yum_install php-pdo
	yum_install php-sqlite
	chkconfig httpd on >> $logFile 2>&1
	www_user=apache
	www_service=httpd
fi

# Prepare the firewall in case it is enabled later
if [[ $(which ufw 2>&-) != "" ]]; then
	# HTTP
	ufw allow 80/tcp >> $logFile
	# HTTPS
	ufw allow 443/tcp >> $logFile
elif [[ $(which firewall-cmd 2>&-) != "" ]]; then
	# HTTP
	firewall-cmd --zone=public --add-port=80/tcp >> $logFile 2>&1
	firewall-cmd --zone=public --add-port=80/tcp --permanent >> $logFile 2>&1
	# HTTPS
	firewall-cmd --zone=public --add-port=443/tcp >> $logFile 2>&1
	firewall-cmd --zone=public --add-port=443/tcp --permanent >> $logFile 2>&1
else
	# HTTP
	if iptables -L | grep DROP | grep -v 'tcp dpt:https' | grep -q 'tcp dpt:http' ; then
		iptables -D INPUT -p tcp --dport 80 -j DROP
	fi
	if ! iptables -L | grep ACCEPT | grep -v 'tcp dpt:https' | grep -q 'tcp dpt:http' ; then
		iptables -I INPUT -p tcp --dport 80 -j ACCEPT
	fi
	# HTTPS
	if iptables -L | grep DROP | grep -q 'tcp dpt:https' ; then
		iptables -D INPUT -p tcp --dport 443 -j DROP
	fi
	if ! iptables -L | grep ACCEPT | grep -q 'tcp dpt:https' ; then
		iptables -I INPUT -p tcp --dport 443 -j ACCEPT
	fi
	service iptables save >> $logFile 2>&1
fi

# Begin Installation
log "Installing Patch Server..."

# Remove default it works page
rm -f /var/www/html/index.html

# Install Slim framework
mkdir -p $installdir/kinobi/bin
cp -R ./resources/Slim $installdir/kinobi/bin/Slim

# Install sqlite database
if [ ! -f $installdir/kinobi/db/patch_v1.sqlite ]; then
	mkdir -p $installdir/kinobi/db
	cp ./resources/patch_v1.sqlite $installdir/kinobi/db/patch_v1.sqlite >> $logFile
	if [ -f $netsusdir/db/patch_v1.sqlite ]; then
		mv $netsusdir/db/patch_v1.sqlite $installdir/kinobi/db/patch_v1.sqlite
	fi
	chown -R $www_user:$www_user $installdir/kinobi/db
fi

# Create paths
mkdir -p $installdir/kinobi/backup
chown -R $www_user:$www_user $installdir/kinobi/backup
mkdir -p $installdir/kinobi/conf
chown -R $www_user:$www_user $installdir/kinobi/conf

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
sed -i 's/^disable_functions =.*/disable_functions =/' $php_ini
sed -i 's/^session.gc_probability =.*/session.gc_probability = 1/' $php_ini
sed -i 's/^allow_url_include =.*/allow_url_include = On/' $php_ini

# Install Common Files
mkdir -p $installdir/html/webadmin/fonts
mkdir -p $installdir/html/webadmin/images
mkdir -p $installdir/html/webadmin/inc/patch
mkdir -p $installdir/html/webadmin/scripts
mkdir -p $installdir/html/webadmin/theme
cp -R ./resources/html/index.php $installdir/html/ >> $logFile
cp -R ./resources/html/v1.php $installdir/html/ >> $logFile
cp -R ./resources/html/webadmin/fonts/* $installdir/html/webadmin/fonts/ >> $logFile
cp -R ./resources/html/webadmin/images/* $installdir/html/webadmin/images/ >> $logFile
cp -R ./resources/html/webadmin/inc/auth.php $installdir/html/webadmin/inc/ >> $logFile
cp -R ./resources/html/webadmin/inc/patch/* $installdir/html/webadmin/inc/patch/ >> $logFile
cp -R ./resources/html/webadmin/logout.php $installdir/html/webadmin/ >> $logFile
cp -R ./resources/html/webadmin/managePatch.php $installdir/html/webadmin/ >> $logFile
cp -R ./resources/html/webadmin/manageTitle.php $installdir/html/webadmin/ >> $logFile
cp -R ./resources/html/webadmin/patchCtl.php $installdir/html/webadmin/ >> $logFile
cp -R ./resources/html/webadmin/patchSettings.php $installdir/html/webadmin/ >> $logFile
cp -R ./resources/html/webadmin/patchTitles.php $installdir/html/webadmin/ >> $logFile
cp -R ./resources/html/webadmin/scripts/* $installdir/html/webadmin/scripts/ >> $logFile
cp -R ./resources/html/webadmin/theme/* $installdir/html/webadmin/theme/ >> $logFile

# Remove Obsolete Files
rm -f $installdir/html/webadmin/inc/dbConnect.php >> $logFile
rm -f $installdir/html/webadmin/scripts/patchHelper.sh >> $logFile

# Install or update Includes
if [[ -e $installdir/html/webadmin/inc/config.php ]]; then
	sed -i '/patchTitles.php/d' $installdir/html/webadmin/inc/header.php
	if [[ -f "$installdir/html/webadmin/fonts/netsus-icons.ttf" ]]; then
		sed -i '/$pageURI == "sharing.php"/i\
				<li id="patch" class="<?php echo ($conf->getSetting("patch") == "enabled" ? ($pageURI == "patchTitles.php" ? "active" : "") : "hidden"); ?>"><a href="patchTitles.php"><span class="netsus-icon icon-patch marg-right"></span>Patch Definitions</a></li>' $installdir/html/webadmin/inc/header.php
	else
		sed -i '/$pageURI == "SUS.php"/i\
			<li class="<?php if ($pageURI == "patchTitles.php") { echo "active"; } ?>"><a href="patchTitles.php"><span class="glyphicon glyphicon-refresh marg-right"></span>Patch Definitions</a></li>' $installdir/html/webadmin/inc/header.php
	fi
else
	cp -R ./resources/html/webadmin/inc/footer.php $installdir/html/webadmin/inc/ >> $logFile
	cp -R ./resources/html/webadmin/inc/header.php $installdir/html/webadmin/inc/ >> $logFile
fi

# Login Page
if ! grep -q "NetSUS" $installdir/html/webadmin/index.php 2>/dev/null; then
	cp -R ./resources/html/webadmin/index.php $installdir/html/webadmin/ >> $logFile
fi

# Insert patch source in dashboard.php
if [[ -e $installdir/html/webadmin/dashboard.php ]]; then
	sed -i '/showpatch/,/panel panel-default panel-main/ {/showpatch/n;/panel panel-default panel-main/!d}' $installdir/html/webadmin/dashboard.php
	sed -i '/showpatch/d' $installdir/html/webadmin/dashboard.php
	sed -i 's/Patch External Source/Patch Definitions/g' $installdir/html/webadmin/dashboard.php
	sed -i '/Patch Definitions/,/Software Update Server/ {/Patch Definitions/n;/Software Update Server/!d}' $installdir/html/webadmin/dashboard.php
	sed -i '/Patch Definitions/d' $installdir/html/webadmin/dashboard.php
	if grep -q "sharing.php" $installdir/html/webadmin/dashboard.php; then
		sed -i '1,/panel panel-default panel-main/ {/panel panel-default panel-main/i\
				<div class="panel panel-default panel-main <?php echo ($conf->getSetting("showpatch") == "false" ? "hidden" : ""); ?>">\
					<div class="panel-heading">\
						<strong>Patch Definitions</strong>\
					</div>\
<?php\
// SSL Enabled\
$ch = curl_init();\
curl_setopt($ch, CURLOPT_URL, "https://".$_SERVER["HTTP_HOST"]);\
curl_setopt($ch, CURLOPT_CERTINFO, true);\
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);\
curl_exec($ch);\
$certinfo = curl_getinfo($ch);\
curl_close ($ch);\
\
// Patch Titles\
include "inc/patch/functions.php";\
include "inc/patch/database.php";\
if (isset($pdo)) {\
	$title_count = $pdo->query("SELECT COUNT(id) FROM titles WHERE enabled = 1")->fetchColumn();\
}\
\
// Suscription\
$subs = $kinobi->getSetting("subscription");\
if (!empty($subs["url"]) && !empty($subs["token"])) {\
	$subs_resp = fetchJsonArray($subs["url"], $subs["token"]);\
}\
?>\
\
					<div class="panel-body">\
						<div class="row">\
<?php if ($conf->getSetting("patch") == "enabled") { ?>\
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
									<span class="text-muted"><?php echo (empty($certinfo["certinfo"]) ? "No" : "Yes"); ?></span>\
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
<?php if (isset($subs_resp["expires"])) { ?>\
\
							<!-- Column -->\
							<div class="col-xs-4 col-md-2">\
								<div class="bs-callout bs-callout-default">\
									<h5><strong>Subscription Expires</strong></h5>\
									<span class="text-muted"><?php echo date("Y-m-d H:i:s", $subs_resp["expires"]); ?></span>\
								</div>\
							</div>\
							<!-- /Column -->\
<?php }\
} else { ?>\
							<!-- Column -->\
							<div class="col-xs-4 col-md-2 dashboard-item">\
								<a href="patchSettings.php">\
									<p><img src="images/settings/PatchManagement.png" alt="Patch Management"></p>\
								</a>\
							</div>\
							<!-- /Column -->\
\
							<!-- Column -->\
							<div class="col-xs-8 col-md-10">\
								<div class="bs-callout bs-callout-default">\
									<h5><strong>Configure Patch Definitions</strong> <small>to provide an external patch source for Jamf Pro.</small></h5>\
									<button type="button" class="btn btn-default btn-sm" onClick="document.location.href=patchSettings.php">Patch Definitions Settings</button>\
								</div>\
							</div>\
							<!-- /Column -->\
<?php } ?>\
						</div>\
						<!-- /Row -->\
					</div>\
				</div>\

}' $installdir/html/webadmin/dashboard.php
		sed -i "s/document.location.href=patchSettings.php/document.location.href='patchSettings.php'/"  $installdir/html/webadmin/dashboard.php
	else
		sed -i '/Software Update Server/i\
		<strong>Patch Definitions</strong>\
	</div>\
<?php\
// SSL Enabled\
$ch = curl_init();\
curl_setopt($ch, CURLOPT_URL, "https://".$_SERVER["HTTP_HOST"]);\
curl_setopt($ch, CURLOPT_CERTINFO, true);\
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);\
curl_exec($ch);\
$certinfo = curl_getinfo($ch);\
curl_close ($ch);\
\
// Patch Titles\
include "inc/patch/functions.php";\
include "inc/patch/database.php";\
if (isset($pdo)) {\
	$title_count = $pdo->query("SELECT COUNT(id) FROM titles WHERE enabled = 1")->fetchColumn();\
}\
\
// Suscription\
$subs = $kinobi->getSetting("subscription");\
if (!empty($subs["url"]) && !empty($subs["token"])) {\
	$subs_resp = fetchJsonArray($subs["url"], $subs["token"]);\
}\
?>\
\
	<div class="panel-body">\
		<div class="row">\
			<!-- Column -->\
			<div class="col-xs-4 col-md-2">\
				<div class="bs-callout bs-callout-default">\
					<h5><strong>SSL Enabled</strong></h5>\
					<span class="text-muted"><?php echo (empty($certinfo["certinfo"]) ? "No" : "Yes"); ?></span>\
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
			<div class="col-xs-4 col-md-2">\
				<div class="bs-callout bs-callout-default">\
					<h5><strong>Number of Titles</strong></h5>\
					<span class="text-muted"><?php echo $title_count; ?></span>\
				</div>\
			</div>\
			<!-- /Column -->\
<?php if (isset($subs_resp["expires"])) { ?>\
\
			<!-- Column -->\
			<div class="col-xs-4 col-md-2">\
				<div class="bs-callout bs-callout-default">\
					<h5><strong>Subscription Expires</strong></h5>\
					<span class="text-muted"><?php echo date("Y-m-d H:i:s", $subs_resp["expires"]); ?></span>\
				</div>\
			</div>\
			<!-- /Column -->\
<?php } ?>\
		</div>\
		<!-- /Row -->\
	</div>\
</div>\
\
<div class="panel panel-default panel-main">\
	<div class="panel-heading">' $installdir/html/webadmin/dashboard.php
	fi
fi

# Insert database control in settings.php
if [[ -e $installdir/html/webadmin/settings.php ]]; then
	sed -i 's:<p>Patch</p>:<p>Patch Definitions</p>:' $installdir/html/webadmin/settings.php
	if ! grep -q "patchSettings.php" $installdir/html/webadmin/settings.php; then
		sed -i 's:<strong>Shares</strong>:<strong>Services</strong>:g' $installdir/html/webadmin/settings.php
		if grep -q "AFP.php" $installdir/html/webadmin/settings.php; then
			sed -i '/<a href="AFP.php">/i\
				<a href="patchSettings.php">\
					<p><img src="images/settings/PatchManagement.png" alt="Patch Definitions"></p>\
					<p>Patch Definitions</p>\
				</a>\
			</div>\
			<!-- /Column -->\
			<!-- Column -->\
			<div class="col-xs-3 col-sm-2 settings-item">' $installdir/html/webadmin/settings.php
		fi
		if grep -q "sharingSettings.php" $installdir/html/webadmin/settings.php; then
			sed -i '/<a href="sharingSettings.php">/i\
					<a href="patchSettings.php">\
						<p><img src="images/settings/PatchManagement.png" alt="Patch Definitions"></p>\
						<p>Patch Definitions</p>\
					</a>\
				</div>\
				<!-- /Column -->\
				<!-- Column -->\
				<div class="col-xs-3 col-sm-2 settings-item">' $installdir/html/webadmin/settings.php
		fi
	fi
fi

# Add endpoint to NetSUS' legacy default http site
if grep -q '/srv/SUS/html' /etc/apache2/sites-enabled/000-default.conf 2>/dev/null || grep -q '/srv/SUS/html' /etc/httpd/conf/httpd.conf 2>/dev/null; then
	ln -s /var/www/html/v1.php /srv/SUS/html/v1.php 2>/dev/null
else
	rm -f /srv/SUS/html/v1.php
fi

# Remove the webadmin's helper script from sudoers
sed -i '/scripts\/patchHelper.sh/d' /etc/sudoers.d/webadmin 2>/dev/null

# Disable directory listing for webadmin
if [ -f "/etc/apache2/apache2.conf" ]; then
	sed -i 's/Options Indexes FollowSymLinks/Options FollowSymLinks/' /etc/apache2/apache2.conf
fi
if [ -f "/etc/httpd/conf/httpd.conf" ]; then
	sed -i 's/Options Indexes FollowSymLinks/Options FollowSymLinks/' /etc/httpd/conf/httpd.conf
fi

# Enable apache on SSL, only needed on Ubuntu
if [[ $(which a2enmod 2>&-) != "" ]]; then
	if [ ! -L /etc/apache2/mods-enabled/ssl.conf ]; then
		rm -f /etc/apache2/mods-enabled/ssl.conf
	fi
	sed -i 's/SSLProtocol all/SSLProtocol all -SSLv3/' /etc/apache2/mods-available/ssl.conf
	a2enmod ssl >> $logFile
	a2ensite default-ssl >> $logFile
fi

# Enable apache on SSL, only needed on RHEL / CentOS
if [ -f "/etc/httpd/conf.d/ssl.conf" ]; then
	sed -i 's/#\?DocumentRoot.*/DocumentRoot "\/var\/www\/html"/' /etc/httpd/conf.d/ssl.conf
	sed -i 's/SSLProtocol all -SSLv2/SSLProtocol all -SSLv2 -SSLv3/' /etc/httpd/conf.d/ssl.conf
	sed -i 's/\(^.*ssl_access_log.*$\)/#\1/' /etc/httpd/conf.d/ssl.conf
	sed -i 's/\(^.*ssl_request_log.*$\)/#\1/' /etc/httpd/conf.d/ssl.conf
	sed -i 's/\(^.*SSL_PROTOCOL.*$\)/#\1/' /etc/httpd/conf.d/ssl.conf
	sed -i '/\(^.*SSL_PROTOCOL.*$\)/ a\CustomLog logs/ssl_access_log \\\
          "%h %l %u %t \\\"%r\\\" %>s %b \\\"%{Referer}i\\\" \\\"%{User-Agent}i\\\""' /etc/httpd/conf.d/ssl.conf
fi

# Restart apache
log "Restarting apache..."
service $www_service restart >> $logFile 2>&1

log "OK"

log "Finished deploying the patch server"

exit 0
