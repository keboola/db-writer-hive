#!/bin/bash
set -Eeuo pipefail

# Run original startup script (hive server) in background
startup.sh &
bg_pid=$!

# Wait for server ready
echo "custom-init.sh: Waiting for server ...";
until
  beeline -u "jdbc:hive2://localhost:$HIVE_DB_PORT" -n "$HIVE_DB_USER" -p "$HIVE_DB_PASSWORD" -e 'SHOW TABLES;' >/dev/null 2>&1
do sleep 1; done
echo "custom-init.sh: OK. Server ready";

# >>>>> Hive DB test data import goes here <<<<<

# Wait for server exit
wait $bg_pid
exit $?
