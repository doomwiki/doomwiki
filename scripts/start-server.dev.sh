#!/bin/sh

echo "Start cron"
sudo crond

# Adjust permissions under home directory. Files and directories copied via
# Dockerfile should already have correct permissions, and our bind-mount
# perms will be managed automatically by Docker. However, there are some parent
# directories of the bind mounts that were created during the mounting that need
# to also be adjusted to allow composer operations (being run as the Drupal
# user) to complete. For example, when Docker bind-mounts
# "app/web/modules/custom", perms on "custom" will be dynamically managed by
# Docker, but "app/web/modules" and "app/web" will be set with static root
# ownership. We need to change this to allow composer to create directories like
# "app/web/libraries". This needs to happen after bind-mounts are added. We only
# need to go a couple levels deep here, as changing ALL directories recursivly
# can take too long.
echo "Adjust home permissions..."
sudo find . -maxdepth 2 -type d -exec chown doomwiki:doomwiki {} +

echo "Start php-fpm daemon..."
# Uncomment the lines below to allow php-fpm to inherit .env directly.
# This is only needed if Drupal is not sourcing the environment itself. Note
# that this is needed even if loading the environment externally with Docker.
# sudo bash -c 'cat /home/doomwiki/.env >> /etc/environment'
# sudo bash -c 'echo "clear_env = no" >> /etc/php-fpm.d/www.conf'
sudo php-fpm

echo "Start apache httpd (in the foreground)..."
sudo /usr/sbin/httpd -D FOREGROUND
