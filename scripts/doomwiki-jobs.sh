#!/bin/bash
# will be executed by a cron-job

MEDIAWIKI=/home/doomwiki/public_html/w
RUNNING=$(ps aux | grep maintenance/runJobs.php | grep maxjobs)
if [ -z "$RUNNING" ]; then
    php $MEDIAWIKI/maintenance/runJobs.php --memory-limit=max --maxjobs=250 >/dev/null
fi
