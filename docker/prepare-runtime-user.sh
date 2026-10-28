#!/usr/bin/env bash
# Make var/ and public/ writable by PHP-FPM, and record which container uid
# should run Composer, console, and Yarn.
#
# Docker Desktop maps the host user to uid 0 inside the container, while the
# dev image runs PHP-FPM as uid 1000. Directories left by the host web server
# (www-data, uid 33) cannot be chowned from that container. This script copies
# those trees back to the current user and opens them for the FPM user.
set -euo pipefail

cd "$(dirname "$0")/.."

HOST_UID="${HOST_UID:-$(id -u)}"
HOST_GID="${HOST_GID:-$(id -g)}"
# Matches `usermod --uid 1000 www-data` in the dev image.
FPM_UID=1000
FPM_GID=1000

reclaim() {
    local path="$1"
    if [ ! -e "$path" ]; then
        return 0
    fi
    local owner
    owner="$(stat -c '%u' "$path")"
    if [ "$owner" = "$HOST_UID" ]; then
        return 0
    fi

    local backup="${path}.bak-not-owned"
    if [ -e "$backup" ]; then
        backup="${path}.bak-not-owned.$(date +%s)"
    fi
    local tmp
    tmp="$(mktemp -d "./${path}.reclaim.XXXXXX")"

    echo "Reclaiming ${path} (uid ${owner}) so PHP in Docker can write it."
    if ! cp -a --no-preserve=ownership "${path}/." "${tmp}/"; then
        rm -rf "$tmp"
        echo "Could not copy ${path}. Run: sudo chown -R $(id -un):$(id -gn) ${path}" >&2
        exit 1
    fi
    mv "$path" "$backup"
    mv "$tmp" "$path"
    echo "Previous copy saved at ${backup}"
}

reclaim public
reclaim var

# PHP-FPM (uid 1000) must be able to create cache files even when Docker
# Desktop presents these directories as owned by root.
if [ -d var ]; then
    find var -type d -exec chmod a+rwx {} + 2>/dev/null || true
fi
if [ -d public ]; then
    find public -type d -exec chmod a+rwx {} + 2>/dev/null || true
fi

RUNTIME_UID="$HOST_UID"
RUNTIME_GID="$HOST_GID"

if [ "${NO_DOCKER:-}" != "1" ] && [ "${NO_DOCKER:-}" != "true" ]; then
    probe=".docker-uid-probe"
    echo probe > "$probe"
    if ! in_uid="$(docker compose run --rm --user root --no-deps -T --entrypoint stat php -c %u "$probe" 2>/dev/null)"; then
        rm -f "$probe"
        echo "Could not detect how Docker maps user ids. Pull evopim/php-dev:8.3 and retry." >&2
        exit 1
    fi
    rm -f "$probe"
    in_uid="$(printf '%s' "$in_uid" | tr -cd '0-9')"
    if [ "$in_uid" = "0" ] && [ "$HOST_UID" != "0" ]; then
        # Host files appear as root in the container. Run CLI as the image's
        # PHP-FPM uid so cache and assets stay writable by the web process.
        RUNTIME_UID="$FPM_UID"
        RUNTIME_GID="$FPM_GID"
        echo "Docker Desktop uid remap detected. PHP commands will run as uid ${FPM_UID}."
    else
        echo "Docker keeps host uid ${HOST_UID}. PHP commands will run as that user."
    fi
fi

cat > docker/.runtime-user.mk <<EOF
PHP_RUNTIME_UID=${RUNTIME_UID}
PHP_RUNTIME_GID=${RUNTIME_GID}
EOF
