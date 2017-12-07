## Docker Full example setup

This page shows an example setup consisting of several docker containers working together for easy deployment.

The main documentation on how to use the bluesky container is in our main [README](../README.md)

### Assumptions for this example

We will be using these docker containers:
- [mysql/mysql-server:5.7](https://hub.docker.com/r/mysql/mysql-server/)
- [sphen/bluesky](https://hub.docker.com/r/sphen/bluesky/)
- [abiosoft/caddy](https://hub.docker.com/r/abiosoft/caddy/)

These are the values being used below - and will need modification from you:

| Setting | Value |
| --- | --- |
| MySQL root password | admin |
| Server FQDN | bluesky.example.com |
| Web admin pass | admin |
| Email alert address | email@example.com |
| SMTP Server | smtp.office365.com:587 |
| SMTP user | email@example.com |
| SMTP pass | yourpassword |

These are the local directories for persistent data:
```
/var/docker/bluesky/db
/var/docker/bluesky/certs
/var/docker/bluesky/admin.ssh
/var/docker/bluesky/bluesky.ssh
/var/docker/caddy
```

### Steps

#### Create the local storage directories

> _Note you may need to set permissions_
```
mkdir -p /var/docker/bluesky/db \
	/var/docker/bluesky/certs \
	/var/docker/bluesky/admin.ssh \
	/var/docker/bluesky/bluesky.ssh \
	/var/docker/caddy
```

#### Create MySQL container

> _Note that the MySQL root password is in this command_
```
docker run -d --name bluesky_db \
	-v /var/docker/bluesky/db:/var/lib/mysql \
	-e MYSQL_ROOT_HOST=% \
	-e MYSQL_ROOT_PASSWORD=admin \
	--restart always \
	mysql/mysql-server:5.7
```

**Wait a minute or two for the MySQL container to initialize.** You can get the status of the container by running `docker ps -a`.  Wait until you see it with a status of **(healthy)**

#### Create BlueSky container

> _Note all the variables that need to change_
```
docker run -d --name bluesky \
	--link bluesky_db:db \
	-e USE_HTTP=1 \
	-e SERVERFQDN=bluesky.example.com \
	-e WEBADMINPASS=admin \
	-e MYSQLROOTPASS=admin \
	-e EMAILALERT=email@example.com \
	-e SMTP_SERVER=smtp.office365.com:587 \
	-e SMTP_AUTH=email@example.com \
	-e SMTP_PASS=yourpassword \
	-v /var/docker/bluesky/certs:/certs \
	-v /var/docker/bluesky/admin.ssh:/home/admin/.ssh \
	-v /var/docker/bluesky/bluesky.ssh:/home/bluesky/.ssh \
	-p 3122:22 \
	--restart always \
	sphen/bluesky
```

#### Create Caddyfile

> _Note the first line containing the FQDN and the line starting with `tls` needs to be changed_
```
cat <<EOF > /var/docker/caddy/Caddyfile
bluesky.example.com {
	proxy / bluesky:80 {
		transparent
	}
	tls email@example.com
}
EOF
```

#### Create Caddy container

```
docker run -d --name caddy \
    -p 80:80 \
    -p 443:443 \
    --link bluesky:bluesky \
    -v /var/docker/caddy/Caddyfile:/etc/Caddyfile \
    -v /var/docker/caddy:/root/.caddy \
    --restart always \
    abiosoft/caddy
```

### TODO

- Set up bluesky to wait for mysql
- docker compose example?
