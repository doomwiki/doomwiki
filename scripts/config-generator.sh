#!/bin/sh

ENV=$1
REGION=$2
SECRET=$3
DRUPAL_HASH_SALT=$4
DRUPAL_CONF_OVERRIDES=$5
DOMAIN=$6

# JQ Note: Values from secretsmanager are piped through JQ twice in case the
# returned format is not properly formatted json (jq will cleanup each level).
# Values in $DRUPAL_CONF_OVERRIDES are expected to be properly formatted json.

cat << EOF
MYSQL_DATABASE=`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.dbname'`
MYSQL_HOSTNAME=`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.host'`
MYSQL_PASSWORD="`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.password'`"
MYSQL_PORT=`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.port'`
MYSQL_USERNAME="`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.username'`"
HASH_SALT="$DRUPAL_HASH_SALT"
SMTP_HOST=`echo $DRUPAL_CONF_OVERRIDES | jq -r '.smtp_settings.smtp_host'`
SMTP_PORT=`echo $DRUPAL_CONF_OVERRIDES | jq -r '.smtp_settings.smtp_port'`
SMTP_USERNAME="`echo $DRUPAL_CONF_OVERRIDES | jq -r '.smtp_settings.smtp_username'`"
SMTP_PASSWORD="`echo $DRUPAL_CONF_OVERRIDES | jq -r '.smtp_settings.smtp_password'`"
TFA_KEY="`echo $DRUPAL_CONF_OVERRIDES | jq -r '.key_key_tfa.key_provider_settings.key_value'`"
APP_DOMAIN='$DOMAIN'
APP_ENV='$ENV'
EOF
