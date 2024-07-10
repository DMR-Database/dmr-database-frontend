# dmr-database-frontend

Soon moor Readme


This is the frontend for the dmr-database in php

#
docker website

##
sudo docker run --name nginx-php-webserver-database -p 80:8080 --restart always -v /home/einstein/website-database:/var/www/html trafex/php-nginx


#
version: '3.1'

services:

  db:
    image: mariadb:10.10
    restart: always
    environment:
      MARIADB_ROOT_PASSWORD: EFeVPRJxD3WB
    ports:
      - 3306:3306

  adminer:
    image: adminer:latest
    restart: always
    ports:
      - 8085:8080
