#!/bin/bash

set -ex;



cd test/ssl
make ca.pem server.pem
sudo cp ca.pem server.pem server.key /etc/rabbitmq/
if [ -z "$SSL_CLIENT_CERT" ]; then
	sudo cp rabbitmq.ssl.verify_none.conf /etc/rabbitmq/rabbitmq.conf
	sudo cp rabbitmq.ssl.verify_none.config /etc/rabbitmq/rabbitmq.config
else
	make client.pem
	sudo cp rabbitmq.ssl.verify_peer.conf /etc/rabbitmq/rabbitmq.conf
	sudo cp rabbitmq.ssl.verify_peer.config /etc/rabbitmq/rabbitmq.config
fi
sudo chown rabbitmq: /etc/rabbitmq/{ca.pem,server.pem,server.key,rabbitmq.conf,rabbitmq.config}
sudo service rabbitmq-server restart
