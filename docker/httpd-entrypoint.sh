#!/bin/sh
# PHP-FPM in the dev image runs as uid 1000. On Docker Desktop the project
# directory is owned by root from the container's point of view, so FPM cannot
# create var/cache unless these directories are writable by every uid.
set -eu

for dir in \
    /srv/pim/var \
    /srv/pim/var/cache \
    /srv/pim/var/logs \
    /srv/pim/var/file_storage \
    /srv/pim/public \
    /srv/pim/public/bundles \
    /srv/pim/public/css \
    /srv/pim/public/js \
    /srv/pim/public/dist
do
    if [ -d "$dir" ]; then
        chmod a+rwx "$dir" 2>/dev/null || true
    fi
done

exec /usr/bin/supervisord -c /srv/pim/docker/supervisord.conf
