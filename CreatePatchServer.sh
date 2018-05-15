#!/bin/bash
# This script generates a new Patch Server Installer

timeEcho() {
	echo $(date "+[%Y-%m-%d %H:%M:%S]: ") "$1"
}

alias md5='md5 -r'
alias md5sum='md5 -r'

echo ""
timeEcho "Building Patch Server Installer..."

# Clean-up old files
rm -f PatchServerInstaller.run 2>&1 > /dev/null
rm -Rf temp 2>&1 > /dev/null

#mkdir temp
mkdir -p temp/installer/checks
mkdir -p temp/installer/resources
mkdir -p temp/installer/utils
cp -R base/PatchInstaller.sh temp/installer/install.sh
cp -R base/testNetSUSRequirements.sh temp/installer/checks/testNetSUSRequirements.sh
cp -R includes/logger.sh temp/installer/utils/logger.sh
cp -R patchserver/patchInstall.sh temp/installer/install-patch_v1.sh
cp -R patchserver/var/appliance/db/* temp/installer/resources/
cp -R patchserver/var/www temp/installer/resources/html
if [ -x "/usr/bin/xattr" ]; then find temp -exec xattr -c {} \; ;fi # Remove OS X extended attributes
find temp -name .DS_Store -delete # Clean out .DS_Store files
find temp -name .svn | xargs rm -Rf # Clean out SVN garbage


# Generate final installer
timeEcho "Creating final installer..."
bash makeself/makeself.sh temp/installer/ PatchServerInstaller.run "Patch Server Installer" "bash install.sh"

timeEcho "Cleaning up..."
rm -Rf temp 2>&1 > /dev/null
timeEcho "Finished creating the Patch Server Installer.  "

exit 0