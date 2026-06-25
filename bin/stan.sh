#!/usr/bin/env bash
#
# Run PHPStan static analysis (PHP 8.5 target) inside the container.
# Any arguments are forwarded to phpstan, e.g.:
#   bin/stan.sh --generate-baseline
#
set -euo pipefail

cd "$(dirname "$0")/.."

docker compose build php
docker compose run --rm php composer install --no-interaction
docker compose run --rm php vendor/bin/phpstan analyse --memory-limit=1G "$@"
