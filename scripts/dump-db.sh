#!/bin/bash

current_date=$(date +%Y%m%d)
MEDIAWIKI=/home/doomwiki/public_html/w
cd /home/doomwiki
backup_dir='./dbdump'
backup_dumpfile="${backup_dir}/mwdump${current_date}.xml.gz"

# Prune old backups
find $backup_dir -type f -name '*.gz' -mtime +6 -exec rm {} \;

# Lock MediaWiki
sed -i '/wgReadOnly = /s~^//~~' $MEDIAWIKI/LocalSettings.php $MEDIAWIKI/LocalSettingsRJ.php

# Create MediaWiki XML export
php $MEDIAWIKI/maintenance/dumpBackup.php --full 2>/tmp/dumpBackup.error | gzip -9 > $backup_dumpfile

# Unlock MediaWiki
sed -i '/wgReadOnly = /s~^~//~' $MEDIAWIKI/LocalSettings.php $MEDIAWIKI/LocalSettingsRJ.php
