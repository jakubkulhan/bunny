#!/bin/bash

set -ex;

cd test/tls
# create CA and server certificate
make ca.pem server.pem

# install CA and server certificate
sudo cp ca.pem server.pem server.key /etc/rabbitmq/

if [ -z "$TLS_CLIENT_CERT" ]; then
	# install config
	sudo cp rabbitmq.tls.verify_none.conf /etc/rabbitmq/rabbitmq.conf
	sudo cp rabbitmq.tls.verify_none.config /etc/rabbitmq/rabbitmq.config
else
	# create client certificate
	make client.pem
	# install config
	sudo cp rabbitmq.tls.verify_peer.conf /etc/rabbitmq/rabbitmq.conf
	sudo cp rabbitmq.tls.verify_peer.config /etc/rabbitmq/rabbitmq.config
fi

# restart RabbitMQ with new config
sudo service rabbitmq-server restart