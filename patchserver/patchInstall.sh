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
	apt_install php-sqlite
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

# Update the webadmin interface
cp -R ./resources/html/* /var/www/html/ >> $logFile
# Add endpoint to default http site
ln -s /var/www/html/v1.php /srv/SUS/html/v1.php 2>/dev/null
# Insert menu link in header.php
if ! grep -q "patchTitles.php" /var/www/html/webadmin/inc/header.php; then
	sed -i '/^                        <li class="<?php if ($pageURI == "SUS.php")/i\
                        <li class="<?php if ($pageURI == "patchTitles.php") { echo "active"; } ?>"><a href="patchTitles.php"><span class="glyphicon glyphicon-refresh marg-right"></span>Patch Definitions</a></li>' /var/www/html/webadmin/inc/header.php
	sed -i '/^                <li class="<?php if ($pageURI == "SUS.php")/i\
                <li class="<?php if ($pageURI == "patchTitles.php") { echo "active"; } ?>"><a href="patchTitles.php"><span class="glyphicon glyphicon-refresh marg-right"></span>Patch Definitions</a></li>' /var/www/html/webadmin/inc/header.php
fi
# Insert database control in settings.php
if ! grep -q "patchSettings.php" /var/www/html/webadmin/settings.php; then
	sed -i 's:<strong>Shares</strong>:<strong>Services</strong>:g' /var/www/html/webadmin/settings.php
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
# Insert patch source in dashboard.php
if ! grep -q "Patch External Source" /var/www/html/webadmin/dashboard.php; then
	sed -i '/Software Update Server/i\
		<strong>Patch External Source</strong>\
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
	?>\
\
	<div class="panel-body">\
		<div class="row">\
			<!-- Column -->\
			<div class="col-xs-6 col-md-2">\
				<div class="bs-callout bs-callout-default">\
					<h4>Number of Titles</h4>\
					<span><?php echo $title_count; ?></span>\
				</div>\
			</div>\
			<!-- /Column -->\
\
			<div class="clearfix visible-xs-block visible-sm-block"></div>\
\
			<!-- Column -->\
			<div class="col-xs-6 col-md-2">\
				<div class="bs-callout bs-callout-default">\
					<h4>SSL Enabled</h4>\
					<span><?php echo (trim(patchExec("getSSLstatus")) == "true" ? "Yes" : "No") ?></span>\
				</div>\
			</div>\
			<!-- /Column -->\
\
			<!-- Column -->\
			<div class="col-xs-6 col-md-2">\
				<div class="bs-callout bs-callout-default">\
					<h4>Hostname</h4>\
					<span><?php echo $_SERVER["HTTP_HOST"]."/v1.php"; ?></span>\
				</div>\
			</div>\
			<!-- /Column -->\
		</div>\
		<!-- /Row -->\
	</div>\
</div>\
\
<div class="panel panel-default panel-main">\
	<div class="panel-heading">' /var/www/html/webadmin/dashboard.php
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
