PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE requirements (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  title_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
);
INSERT INTO requirements VALUES(1,4,'Application Bundle ID','is','com.jamfsoftware.JamfAdmin','recon',0,0);
INSERT INTO requirements VALUES(2,4,'Application Bundle ID','is','com.jamfsoftware.CasperAdmin','recon',0,1);
INSERT INTO requirements VALUES(3,5,'java-8-jdk','is not','Not Installed','extensionAttribute',1,0);
INSERT INTO requirements VALUES(4,5,'java-8-jdk','is not','','extensionAttribute',1,1);
CREATE TABLE patches (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  title_id int(11) NOT NULL DEFAULT -1,
  version varchar(255) NOT NULL,
  released bigint(32) NOT NULL DEFAULT -1,
  standalone tinyint(1) NOT NULL DEFAULT 1,
  min_os varchar(255) NOT NULL,
  reboot tinyint(1) NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT -1,
  enabled tinyint(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
);
INSERT INTO patches VALUES(1,4,'10.0.0',1509451200,1,'10.9',0,0,1);
INSERT INTO patches VALUES(2,4,'9.101.0',1505225978,1,'10.9',0,1,1);
INSERT INTO patches VALUES(3,4,'9.100.0',1500569669,1,'10.9',0,2,1);
INSERT INTO patches VALUES(4,4,'9.99.0',1495562969,1,'10.9',0,3,1);
INSERT INTO patches VALUES(5,5,'1.8.152',1508167098,1,'10.8.3',0,0,1);
INSERT INTO patches VALUES(6,5,'1.8.151',1508062698,1,'10.8.3',0,1,1);
CREATE TABLE ext_attrs (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  title_id int(11) NOT NULL DEFAULT -1,
  key_id varchar(255) NOT NULL,
  script longtext,
  name varchar(255) NOT NULL,
  FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
);
INSERT INTO ext_attrs VALUES(1,5,'java-8-jdk',replace(replace('#!/usr/bin/env bash\r\n##########################################################################################\r\n# Collects information to determine which version of the Java JDK is installed by        #\r\n# looping through all the installed JDKs for the major version selected. And then        #\r\n# comparing the build number to determine the highest value. Builds the result as        #\r\n# 1.X.Y, ignoring the build number, where X is major version and Y is the minor version. #								  #	\r\n########################################################################################## \r\nSEARCH_FOR_VERSION="8"\r\nHIGHEST_BUILD="-1"\r\nRESULT="Not Installed"\r\n\r\ninstalled_jdks=$(/bin/ls /Library/Java/JavaVirtualMachines/)\r\n\r\n\r\nfor i in ${installed_jdks}; do\r\n	version=$( /usr/bin/defaults read "/Library/Java/JavaVirtualMachines/${i}/Contents/Info.plist" CFBundleVersion )\r\n\r\n	major_version=`echo "$version" | awk -F''.'' ''{print $2}''`\r\n\r\n	if [ "$major_version" -eq "$SEARCH_FOR_VERSION" ] ; then\r\n		# Split on 1.X.0_XX to get build number\r\n		build_number=`echo "$version" | awk -F''0_'' ''{print $2}''`\r\n		if [ "$build_number" -gt "$HIGHEST_BUILD" ] ; then\r\n			HIGHEST_BUILD="$build_number"\r\n			RESULT="1.$major_version.$build_number"\r\n		fi	\r\n	fi		\r\ndone\r\n\r\necho "<result>$RESULT</result>"','\r',char(13)),'\n',char(10)),'Java 8 SE Development Kit');
CREATE TABLE kill_apps (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  bundle_id varchar(255) NOT NULL,
  app_name varchar(255) NOT NULL,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
);
INSERT INTO kill_apps VALUES(1,1,'com.jamfsoftware.CasperAdmin','Casper Admin.app');
INSERT INTO kill_apps VALUES(2,1,'com.jamfsoftware.JamfAdmin','Jamf Admin.app');
INSERT INTO kill_apps VALUES(3,2,'com.jamfsoftware.CasperAdmin','Casper Admin.app');
INSERT INTO kill_apps VALUES(4,3,'com.jamfsoftware.CasperAdmin','Casper Admin.app');
INSERT INTO kill_apps VALUES(5,4,'com.jamfsoftware.CasperAdmin','Casper Admin.app');
CREATE TABLE components (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  version varchar(255) NOT NULL,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
);
INSERT INTO components VALUES(1,1,'Jamf Admin','10.0.0');
INSERT INTO components VALUES(2,2,'Jamf Admin','9.101.0');
INSERT INTO components VALUES(3,3,'Jamf Admin','9.100.0');
INSERT INTO components VALUES(4,4,'Jamf Admin','9.99.0');
INSERT INTO components VALUES(5,5,'Java SE Development Kit 8','1.8.152');
INSERT INTO components VALUES(6,6,'Java SE Development Kit 8','1.8.151');
CREATE TABLE capabilities (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
);
INSERT INTO capabilities VALUES(1,1,'Operating System Version','greater than or equal','10.9','recon',1,0);
INSERT INTO capabilities VALUES(2,2,'Operating System Version','greater than or equal','10.9','recon',1,0);
INSERT INTO capabilities VALUES(3,3,'Operating System Version','greater than or equal','10.9','recon',1,0);
INSERT INTO capabilities VALUES(4,4,'Operating System Version','greater than or equal','10.9','recon',1,0);
INSERT INTO capabilities VALUES(5,5,'Operating System Version','greater than or equal','10.8.3','recon',1,0);
INSERT INTO capabilities VALUES(6,6,'Operating System Version','greater than or equal','10.8.3','recon',1,0);
CREATE TABLE dependencies (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  patch_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (patch_id) REFERENCES patches (id) ON DELETE CASCADE
);
CREATE TABLE criteria (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  component_id int(11) NOT NULL DEFAULT -1,
  name varchar(255) NOT NULL,
  operator varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  type varchar(255) NOT NULL,
  is_and tinyint(1) DEFAULT NULL,
  sort_order int(11) NOT NULL DEFAULT -1,
  FOREIGN KEY (component_id) REFERENCES components (id) ON DELETE CASCADE
);
INSERT INTO criteria VALUES(1,1,'Application Bundle ID','is','com.jamfsoftware.JamfAdmin','recon',1,0);
INSERT INTO criteria VALUES(2,1,'Application Version','is','10.0.0','recon',1,1);
INSERT INTO criteria VALUES(3,2,'Application Bundle ID','is','com.jamfsoftware.CasperAdmin','recon',1,0);
INSERT INTO criteria VALUES(4,2,'Application Version','is','9.101.0','recon',1,1);
INSERT INTO criteria VALUES(5,3,'Application Bundle ID','is','com.jamfsoftware.CasperAdmin','recon',1,0);
INSERT INTO criteria VALUES(6,3,'Application Version','is','9.100.0','recon',1,1);
INSERT INTO criteria VALUES(7,4,'Application Bundle ID','is','com.jamfsoftware.CasperAdmin','recon',1,0);
INSERT INTO criteria VALUES(8,4,'Application Version','is','9.99.0','recon',1,1);
INSERT INTO criteria VALUES(9,5,'java-8-jdk','is','1.8.152','extensionAttribute',1,0);
INSERT INTO criteria VALUES(10,6,'java-8-jdk','is','1.8.151','extensionAttribute',1,0);
CREATE TABLE titles (
  id integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  name varchar(255) NOT NULL,
  publisher varchar(255) NOT NULL,
  app_name varchar(255) DEFAULT NULL,
  bundle_id varchar(255) DEFAULT NULL,
  modified bigint(32) NOT NULL DEFAULT -1,
  current varchar(255) NOT NULL,
  name_id varchar(255) NOT NULL,
  enabled tinyint(1) NOT NULL DEFAULT 0,
  source_id int(11) NOT NULL DEFAULT 0
);
INSERT INTO titles VALUES(4,'Jamf Admin','Jamf','Jamf Admin.app','com.jamfsoftware.JamfAdmin',1510771309,'10.0.0','JamfAdmin',1,0);
INSERT INTO titles VALUES(5,'Java SE Development Kit 8','Oracle','','',1508416738,'1.8.152','JavaSEDevelopmentKit8',1,0);
DELETE FROM sqlite_sequence;
INSERT INTO sqlite_sequence VALUES('requirements',8);
INSERT INTO sqlite_sequence VALUES('patches',9);
INSERT INTO sqlite_sequence VALUES('kill_apps',9);
INSERT INTO sqlite_sequence VALUES('components',9);
INSERT INTO sqlite_sequence VALUES('criteria',15);
INSERT INTO sqlite_sequence VALUES('capabilities',9);
INSERT INTO sqlite_sequence VALUES('ext_attrs',2);
INSERT INTO sqlite_sequence VALUES('titles',5);
COMMIT;
