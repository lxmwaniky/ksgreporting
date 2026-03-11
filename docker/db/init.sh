#!/bin/bash
set -e

# schema.sql contains a CREATE DATABASE block and a \c metacommand that are
# handled automatically by the postgres Docker entrypoint.
# This script strips those lines and runs the rest against the pre-created DB.

sed '/^CREATE DATABASE/,/^\\c /d' /schema.sql \
    | psql -v ON_ERROR_STOP=1 \
           --username "$POSTGRES_USER" \
           --dbname "$POSTGRES_DB"
