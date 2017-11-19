FROM ubuntu:16.04

ENV IN_DOCKER 1
ENV USE_HTTP 0
ENV SERVERFQDN localhost
ENV WEBADMINPASS admin
ENV MYSQLROOTPASS admin
ENV EMAILALERT root@localhost

RUN echo "mysql-server mysql-server/root_password password $MYSQLROOTPASS" | debconf-set-selections && \
	echo "mysql-server mysql-server/root_password_again password $MYSQLROOTPASS" | debconf-set-selections

RUN apt-get update && \
    apt-get install --no-install-recommends -y apache2 \
    openssh-server \
    openssl \
    curl \
    cron \
    mysql-client \
    php-mysql \
    php \
    libapache2-mod-php \
    php-mcrypt \
    php-mysql \
    inoticoming \
    supervisor \
    swaks && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir /usr/local/bin/BlueSky /var/run/sshd

COPY . /usr/local/bin/BlueSky/
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/run /usr/local/bin/run
RUN chmod +x /usr/local/bin/run

EXPOSE 80 443 3122

# Define mountable directories.
# VOLUME ["/etc/mysql", "/var/lib/mysql", "/home/ubuntu/workspace"]

CMD ["/usr/local/bin/run"]