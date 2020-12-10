#!/bin/bash

set -eu

is_rabbitmq_gte_3_7_0() {
	[[ "3.7.0" = $(echo -e "3.7.0\n$RABBITMQ_VERSION" | sort -V | head -n1) ]]
}

TEST_DATA_ROOT=/opt/bunny/test/ssl
CONFIG_NAME=${CONFIG_NAME:-}

cp ${TEST_DATA_ROOT}/{ca.pem,server.pem,server.key} /etc/rabbitmq/
chown rabbitmq /etc/rabbitmq/{ca.pem,server.pem,server.key}
chmod 0400 /etc/rabbitmq/{ca.pem,server.pem,server.key}

if [[ -n "$CONFIG_NAME" ]]; then
	if is_rabbitmq_gte_3_7_0; then
		cp ${TEST_DATA_ROOT}/${CONFIG_NAME}.conf /etc/rabbitmq/rabbitmq.conf
		chown rabbitmq /etc/rabbitmq/rabbitmq.conf
	else
		cp ${TEST_DATA_ROOT}/${CONFIG_NAME}.config /etc/rabbitmq/rabbitmq.config
		chown rabbitmq /etc/rabbitmq/rabbitmq.config
	fi
fi

exec "$@"
