#!/bin/bash

# Check if is script sourced (not called directly), required to set env variables
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
  echo "This script should be not called directly. Please use source ./set_env_vars.sh"
  exit 1
fi

# Check if HIVE_VERSION is defined
if [ -z "$HIVE_VERSION" ]; then
  echo "HIVE_VERSION env variable is not defined."
  echo "Please specify HIVE_VERSION env variable using export HIVE_VERSION=..."
  return 1
fi

# Convert version separated by dots to integer to compare
function version { echo "$@" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }'; }

# Default values
export HIVE_DB_HOST="hive-server"
export HIVE_DB_PORT="10000"
export HIVE_DB_DATABASE="default"
export HIVE_DB_USER="hive"
export HIVE_DB_PASSWORD="p#a!s@sw:o&r%^d"
export HIVE_SERVER_IMAGE_DIR="docker/hive-v1-v2"

export HADOOP_NAMENODE_TAG="namenode:2.0.0-hadoop2.7.4-java8"
export HADOOP_DATANODE_TAG="datanode:2.0.0-hadoop2.7.4-java8"
export HADOOP_RESOURCEMANAGER_TAG="resourcemanager:2.0.0-hadoop2.7.4-java8"
export HADOOP_NODEMANAGER_TAG="resourcemanager:2.0.0-hadoop2.7.4-java8"
export HADOOP_HISTORYSERVER_TAG="historyserver:2.0.0-hadoop2.7.4-java8"

export HADOOP_NAMENODE_WAIT_PORT=50070
export HADOOP_DATANODE_WAIT_PORT=50075
export HADOOP_RESOURCEMANAGER_WAIT_PORT=8088

# Version 2.0 ... 2.2
# Bug in Hive DB LDAP auth, required full name as username
if [[ "$(version "2.0.0")" -le "$(version $HIVE_VERSION)" ]] &&
   [[ "$(version "2.3.0")" -gt "$(version $HIVE_VERSION)" ]]; then
    export HIVE_DB_USER="uid=${HIVE_DB_USER},dc=keboola-test,dc=com"
fi

# Version 3.0+ requires Hadoop 3
if [[ "$(version "3.0.0")" -le "$(version $HIVE_VERSION)" ]]; then
  echo "Hive DB versions 3.0+ are not supported by testing environment."
  return 1
fi

# Create external network
# Issue in docker + hadoop: https://github.com/docker/compose/issues/229#issuecomment-234669078
docker network inspect dbwriterhive >/dev/null 2>&1 || docker network create  dbwriterhive >/dev/null 2>&1
