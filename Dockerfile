FROM ubuntu:16.04

ENV IN_DOCKER=1 \
    USE_HTTP=0 \
    FAIL2BAN=1 \
    SERVERFQDN=localhost \
    MYSQLSERVER=db \
    WEBADMINPASS=admin \
    EMAILALERT=root@localhost \
    LANG=C.UTF-8 \
    LC_ALL=C.UTF-8 \
    BLUESKY_VERSION=2.3.1

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
    php-mysql \
    inoticoming \
    supervisor \
    cpio \
    netcat \
    swaks \
    rsyslog \
    fail2ban \
    iptables \
    uuid-runtime \
    libnet-ssleay-perl && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir /usr/local/bin/BlueSky /var/run/sshd  /var/run/fail2ban

COPY . /usr/local/bin/BlueSky/

RUN mv /usr/local/bin/BlueSky/docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf && \
	mv /usr/local/bin/BlueSky/docker/* /usr/local/bin/ && \
	touch /var/log/auth.log /etc/default/locale && \
	chown syslog:adm /var/log/auth.log && \
	chmod 640 /var/log/auth.log && \
	chmod +x /usr/local/bin/run /usr/local/bin/fail2ban-supervisor.sh /usr/local/bin/build_pkg.sh /usr/local/bin/build_admin_pkg.sh && \
	echo "ServerName CHANGETHIS" >> /etc/apache2/apache2.conf

EXPOSE 22 80 443

VOLUME ["/certs", "/home/admin/.ssh", "/home/bluesky/.ssh", "/tmp/pkg"]

CMD ["/usr/local/bin/run"]
