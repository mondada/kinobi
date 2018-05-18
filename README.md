### About

Kinobi is an External Patch Definition server for Jamf Pro.
It provides a simple interface for creating and editing Patch Definitions, as well as the appropriate endpoints for Jamf Pro to connect to.


#### Requirements

NetSUS 4.1.0 or later


Latest OVA

https://www.dropbox.com/s/selvovkm0mwrqli/NetSUSLP_4.2.3.ova


Latest Installer

https://www.dropbox.com/s/e7drtcjc3b47525/NetSUSLPInstaller_4.2.3.run


### Downloading

Ubuntu

<code>wget https://www.dropbox.com/s/oqe13l73fqkme9g/PatchServerInstaller_1.0.run</code>


CentOS / RHEL

<code>curl -L -O https://www.dropbox.com/s/oqe13l73fqkme9g/PatchServerInstaller_1.0.run</code>


### Installation

<code>chmod +x PatchServerInstaller_1.0.run</code>

<code>sudo ./PatchServerInstaller_1.0.run</code>

<code>rm -f PatchServerInstaller_1.0.run</code>


### Connecting Jamf Pro to Kinobi Server

To connect Jamf Pro to an external patch source, navigate to Settings > Computer Management > Patch Management

Add `<HOSTNAME or IP ADDRESS>/v1.php` as the hostname in Jamf Pro

If enabling SSL ensure the Jamf Pro Server will trust the NetSUS SSL certificate.
