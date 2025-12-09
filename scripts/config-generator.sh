#!/bin/sh

ENV=$1
REGION=$2
SECRET=$3
WIKI_CONF_OVERRIDES=$4
DOMAIN=$5

# JQ Note: Values from secretsmanager are piped through JQ twice in case the
# returned format is not properly formatted json (jq will cleanup each level).
# Values in $WIKI_CONF_OVERRIDES are expected to be properly formatted json.

cat << EOF
MYSQL_PORT=`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.mysql_port'`
MYSQL_DATABASE=`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.db_name'`
MYSQL_HOSTNAME=`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.mysql_listen_addr'`
MYSQL_USERNAME="`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.app_user'`"
MYSQL_PASSWORD="`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.app_password'`"
MYSQL_ADMINUSERNAME="`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.root_user'`"
MYSQL_ADMINPASSWORD="`aws secretsmanager get-secret-value --secret-id $SECRET | jq -r '.SecretString' | jq -r '.root_password'`"
SMTP_HOST=`aws ssm get-parameter --name WikiConfOverrides | jq -r '.Parameter.Value' | jq -r '.smtp_settings.smtp_host'`
SMTP_PORT=`aws ssm get-parameter --name WikiConfOverrides | jq -r '.Parameter.Value' | jq -r '.smtp_settings.smtp_port'`
SMTP_USERNAME="`aws ssm get-parameter --name WikiConfOverrides | jq -r '.Parameter.Value' | jq -r '.smtp_settings.smtp_username'`"
SMTP_PASSWORD="`aws ssm get-parameter --name WikiConfOverrides | jq -r '.Parameter.Value' | jq -r '.smtp_settings.smtp_password'`"
WIKI_SECRET_KEY="`aws ssm get-parameter --name WikiConfOverrides | jq -r '.Parameter.Value' | jq -r '.wiki_secret_key'`"
WIKI_UB_UPLOAD_BLACKLIST="`aws ssm get-parameter --name WikiConfOverrides | jq -r '.Parameter.Value' | jq -r '.wiki_ub_upload_blacklist'`"
WIKI_MONACO_PAYPAL_ID="`aws ssm get-parameter --name WikiConfOverrides | jq -r '.Parameter.Value' | jq -r '.wiki_monaco_paypal_id'`"
WIKI_GOOGLE_SITE_VERIFICATION="`aws ssm get-parameter --name WikiConfOverrides | jq -r '.Parameter.Value' | jq -r '.wiki_google_site_verification'`"
APP_DOMAIN="$DOMAIN"
APP_ENV="$ENV"
EOF

