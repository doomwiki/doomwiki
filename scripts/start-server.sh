#!/bin/sh

MW_INSTALL_PATH="/home/doomwiki/public_html/w"
export MW_INSTALL_PATH
CACHE_DIR="/tmp/cache"
SETTINGS_FILES="LocalSettings.php LocalSettingsRJ.php"

echo "Relocate LocalSettings files to ${CACHE_DIR} and symlink main file back"
sudo mkdir -p "${CACHE_DIR}"
for f in ${SETTINGS_FILES}; do
  src="${MW_INSTALL_PATH}/${f}"
  dest="${CACHE_DIR}/${f}"
  if [ -f "${src}" ] && [ ! -f "${dest}" ]; then
    sudo mv "${src}" "${dest}"
  fi
  if [ -f "${dest}" ]; then
    sudo chown apache:apache "${dest}"
  fi
done
for f in ${SETTINGS_FILES}; do
  if [ -f "${CACHE_DIR}/${f}" ]; then
    sudo ln -sfn "${CACHE_DIR}/${f}" "${MW_INSTALL_PATH}/${f}"
  fi
done

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
#done

echo "Start php-fpm daemon..."
sudo bash -c 'echo "clear_env = no" >> /etc/php-fpm.d/www.conf'
sudo php-fpm --nodaemonize 2>&1 &

echo "Start apache httpd (in the foreground)..."
sudo /usr/sbin/httpd -D FOREGROUND 2>&1
