# dmr-database-frontend

Soon more Readme


This is the frontend for the dmr-database in php

#
docker website

##
sudo docker run --name nginx-php-webserver-database -p 80:8080 --restart always -v /home/einstein/website-database:/var/www/html trafex/php-nginx


#
docker compose for mariadb with adminer

##
version: '3.1'

services:

  db:
    image: mariadb:10.10
    restart: always
    environment:
      MARIADB_ROOT_PASSWORD: passw0rd
    ports:
      - 3306:3306

  adminer:
    image: adminer:latest
    restart: always
    ports:
      - 8085:8080
