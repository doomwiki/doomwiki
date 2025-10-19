#!/bin/bash
#
# Initiate a Veracode SAST Scan

VERACODE_SECRET_ID=$1
VERACODE_SECRET_KEY=$2
VERSION_STRING=$3
PROJECT_NAME=$4

CODE_PACKAGE="./$PROJECT_NAME.zip"

set -o nounset

function fail {
  echo $1 >&2
  exit 1
}

function retry {
  local n=1
  local max=3
  local delay=600
  while true; do
    echo "Attempt $n/$max"
    "$@" && break || {
      if [[ $n -lt $max ]]; then
        ((n++))
        echo "Scan already in progress. Waiting $delay seconds before trying again."
        sleep $delay;
      else
        fail "The scan attempt has failed after $n attempts."
      fi
    }
  done
}

echo "Sending $CODE_PACKAGE for scanning..."

retry java -jar /opt/veracode/api-wrapper.jar -action uploadandscan \
    -vid "$VERACODE_SECRET_ID" -vkey "$VERACODE_SECRET_KEY" \
    -appname "$PROJECT_NAME" \
    -createprofile true \
    -version "$VERSION_STRING" \
    -filepath "$CODE_PACKAGE"
