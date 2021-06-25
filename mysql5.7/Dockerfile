FROM mysql:5.7.16

COPY my.conf /etc/mysql/conf.d/my.cnf
COPY init.sql /docker-entrypoint-initdb.d/init.sql
