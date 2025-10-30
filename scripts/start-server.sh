#!/bin/sh

echo "Start cron"
sudo crond

#sudo crontab /etc/cron.d/doomwiki-cron

# (Re)establish appropriate permissions in our files directories. Note that this
# should only strictly be necessary after new files are synced from another
# location with different permissions schemes (such as a files sync from a zip
# file or S3), but we check these perms on each container load for convienence.
# The web server is the primary maintainer of these files and should have
# ownership. Only directores need any execute permission. Our Doomwiki 
# user should be a member of the apache group.
fileDirs=("/var/www/images")
#for fileDir in ${fileDirs[@]}; do
#  echo "Updating owner and groups for $fileDir..."
#  sudo chown -R apache:apache $fileDir
#  echo "Updating directory permissions for $fileDir..."
#  sudo find $fileDir -type d -exec chmod 775 '{}' ';'
#  echo "Updating file permissions for $fileDir..."
#  sudo find $fileDir -type f -exec chmod 664 '{}' ';'
done

echo "Start php-fpm daemon..."
sudo bash -c 'echo "clear_env = no" >> /etc/php-fpm.d/www.conf'
sudo php-fpm --nodaemonize 2>&1 &

echo "Start apache httpd (in the foreground)..."
sudo /usr/sbin/httpd -D FOREGROUND 2>&1
