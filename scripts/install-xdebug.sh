#!/bin/sh

# Newer version of Amazon Linux do not include access to package repositories
# that allow us to install xdebug via yum, so unfortunatly we need to install
# from sources.
echo "Installing Xdebug..."
wget https://xdebug.org/files/xdebug-3.2.2.tgz \
  && sudo pecl install ./xdebug-3.2.2.tgz \
  && rm ./xdebug-3.2.2.tgz

# Configure XDebug via directive additions to php.ini.
# When possible we try to detect the IDE address via the request (using
# xdebug.discover_client_host=true). This should work if IDE is connected
# directly to the running container. We fallback on a connection to the IDE
# running remotely on the host (using xdebug.client_host=host.docker.internal).
# If debugging remotely pathmappings need to be setup in IDE to allow
# breakpoints to work. See https://github.com/xdebug/vscode-php-debug#remote-host-debugging
# An example launch.json snippet with pathmappings may look like this:
#    {
#      "name": "Listen for Xdebug",
#      "type": "php",
#      "request": "launch",
#      "port": 9003,
#      "pathMappings": {
#        "/home/doomwiki/app/web": "${workspaceRoot}/app/web",
#      }
#    },
echo "Configuring xdebug..."
if grep -Fxq "zend_extension=xdebug" /etc/php.d/40-doomwiki-custom.ini

then
echo "xdebug is already configured"

else
sudo bash -c 'cat >> /etc/php.d/40-doomwiki-custom.ini << EOF


# Activate xdebug
zend_extension=xdebug
xdebug.mode=debug
xdebug.start_with_request=trigger
xdebug.discover_client_host=true
xdebug.client_host=host.docker.internal
EOF'

# Xdebug will be active on CLI immediatly, but we need to restart PHP for it
# to work on CGI. We don't use init system for php-fpm so we have to do a
# brute-force stop and start.
echo "Restarting php..."
sudo kill php-fpm && sudo php-fpm

fi
