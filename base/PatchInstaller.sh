#!/bin/bash
# This script controls the flow of the Linux Patch Definition Server installation

export PATH="/bin:$PATH"

netsusdir=/var/appliance

#==== Check Requirements - Root User ======================#

if [[ "$(id -u)" != "0" ]]; then
  echo "The Patch Definition Server Installer needs to be run as root or using sudo."
  exit 1
fi

# Needed for systems with secure umask settings
OLD_UMASK=$(umask)
umask 022

clean-exit() {
  umask "$OLD_UMASK"
  exit 0
}

clean-fail() {
  umask "$OLD_UMASK"
  exit 1
}

# Create NetSUS directory (needed immediately for logging)
mkdir -p $netsusdir/logs

source utils/logger.sh

#==== Parse Arguments =====================================#

export INTERACTIVE=true

while getopts "hny" ARG
do
  case $ARG in
    h)
    echo "Usage: $0 [-y]"
    echo "-y    Activates non-interactive mode, which will silently install the NetSUS without any prompts"
    echo "-h    Shows this message"
    exit 0
    ;;
    n)
    logCritical "The -n flag is deprecated and will be removed in a future version.
                 Please use -y instead."
    export INTERACTIVE=false
    ;;
    y)
    export INTERACTIVE=false
    ;;
  esac
done

#==== Check Requirements ==================================#

log "Starting the Patch Definition Server Installation"
log "Checking installation requirements..."

failedAnyChecks=0
# Check for Valid NetSUS
bash checks/testNetSUSRequirements.sh || failedAnyChecks=1

# Abort if we failed any checks
if [[ $failedAnyChecks -ne 0 ]]; then
  log "Aborting installation due to unsatisfied requirements."
  if [[ $INTERACTIVE = true ]]; then
    # shellcheck disable=SC2154
    echo "Installation failed.  See $logFile for more details."
  fi
  clean-fail
fi

log "Passed all requirements"

#==== Prompt for Confirmation =============================#

if [[ $INTERACTIVE = true ]]; then
# Prompt user for permission to continue with the installation
  echo "
The following will be installed
* Patch Definition Server
"

  # shellcheck disable=SC2162,SC2034
  read -t 1 -n 100000 devnull # This clears any accidental input from stdin

  while [[ $REPLY != [yYnN] ]]; do
    # shellcheck disable=SC2162
    read -n1 -p "Proceed?  (y/n): "
    echo ""
  done
  if [[ $REPLY = [nN] ]]; then
    log "Aborting..."
    clean-exit
  else
    log "Installing..."
		log ""
  fi
else
  log "Installing..."
	log ""
fi

#==== Initial Cleanup tasks ===============================#

#==== Install Components ==================================#

bash install-patch_v1.sh || clean-fail

#==== Post Cleanup tasks ==================================#

log ""
log "The Patch Definition Server has been installed."
log "To complete the installation, open a web browser and navigate to https://${HOSTNAME}:443/."
log "If you are installing the Patch Definition Server for the first time, please follow the documentation for setup instructions."
log ""

clean-exit