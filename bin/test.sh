#!/usr/bin/env bash
#
# Run the PHPUnit suite inside the PHP 8.5 container.
# Any arguments are forwarded to phpunit, e.g.:
#   bin/test.sh --filter AttachmentMapper
#
set -euo pipefail

cd "$(dirname "$0")/.."

docker compose build php
docker compose run --rm php composer install --no-interaction
docker compose run --rm php vendor/bin/phpunit "$@"
