FROM ubuntu:16.04

ENV IN_DOCKER=1 \
    USE_HTTP=0 \
    SERVERFQDN=localhost \
    MYSQLSERVER=db \
    WEBADMINPASS=admin \
    EMAILALERT=root@localhost

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
    cpio \
    netcat \
    swaks \
    libnet-ssleay-perl && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir /usr/local/bin/BlueSky /var/run/sshd

COPY . /usr/local/bin/BlueSky/

RUN mv /usr/local/bin/BlueSky/docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf && \
	mv /usr/local/bin/BlueSky/docker/* /usr/local/bin/ && \
	chmod +x /usr/local/bin/run /usr/local/bin/build_pkg.sh /usr/local/bin/build_admin_pkg.sh

EXPOSE 22 80 443

VOLUME ["/certs", "/home/admin/.ssh", "/home/bluesky/.ssh", "/tmp/pkg", "/etc/ssl/certs", "/etc/ssl/private"]

CMD ["/usr/local/bin/run"]
