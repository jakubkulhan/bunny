
ifndef SSL_PEER_NAME
override SSL_PEER_NAME = server.rmq
endif

clean:
	rm -f \
		ca.key ca.pem ca.srl \
		client.key client.csr client.pem \
		server.key server.csr server.pem \

all: ca.pem client.pem server.pem

ca.key:
	openssl ecparam -genkey -name secp256r1 | openssl ec -out ca.key

ca.pem: ca.key
	openssl req -new -x509 -days 3650 -key ca.key -out ca.pem -subj "/CN=ca.rmq"

client.key:
	openssl ecparam -genkey -name secp256r1 | openssl ec -out client.key

client.csr: client.key
	openssl req -new -key client.key -out client.csr -subj "/CN=client.rmq"

client.pem: ca.pem ca.key client.key client.csr
	openssl x509 -req -days 10 \
		-CA ca.pem -CAkey ca.key -CAcreateserial \
		-in client.csr  -out client.pem

server.key:
	openssl ecparam -genkey -name secp256r1 | openssl ec -out server.key

server.csr: server.key
	openssl req -new -key server.key -out server.csr -subj "/CN=$(SSL_PEER_NAME)"

server.pem: ca.pem ca.key server.key server.csr
	openssl x509 -req -days 10 \
		-CA ca.pem -CAkey ca.key -CAcreateserial \
		-in server.csr  -out server.pem
