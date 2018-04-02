## Docker Full example setup

This page shows an example setup consisting of several docker containers working together for easy deployment.

The main documentation on how to use the bluesky container is in our main [README](https://github.com/logicnow/BlueSky/blob/master/docker/README.md)

### Assumptions for this example

We will be using these docker containers:
- [mysql:5.7](https://hub.docker.com/_/mysql/)
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

> _Note you may want to set more secure permissions_

```
mkdir -p /var/docker/bluesky/db \
  /var/docker/bluesky/certs \
  /var/docker/bluesky/admin.ssh \
  /var/docker/bluesky/bluesky.ssh \
  /var/docker/caddy
```

#### Create MySQL container

> _Note that the MySQL root password is in this command. If you use a complex password with offending characters you should enclose the password in ''. Passwords do not work with a \ in them_

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

> **Note:** _All the variables that need to change.  If you use complex passwords with offending characters you should enclose the password in single quotes.  Passwords do not work with a backslash in them_

> **Note:** _We do not care about SSL certs here as the Caddy container will take care of that_

```
docker run -d --name bluesky \
  --link bluesky_db:db \
  -e SERVERFQDN=bluesky.example.com \
  -e WEBADMINPASS=admin \
  -e EMAILALERT=email@example.com \
  -e SMTP_SERVER=smtp.office365.com:587 \
  -e SMTP_AUTH=email@example.com \
  -e SMTP_PASS=yourpassword \
  -v /var/docker/bluesky/certs:/certs \
  -v /var/docker/bluesky/admin.ssh:/home/admin/.ssh \
  -v /var/docker/bluesky/bluesky.ssh:/home/bluesky/.ssh \
  --cap-add=NET_ADMIN \
  -p 3122:22 \
  --restart always \
  sphen/bluesky
```

#### Create Caddyfile

> **Note:** _The first line containing the FQDN and the line starting with `tls` needs to be changed._

> _We also are specifically passing through via https in order to keep sanity in the appgini framework.  Without proxying to https the will be some pages that redirect back to http.  Because of this we also use the `insecure_skip_verify` option as the bluesky cert will be self signed for internal traffic.  If anyone else has any bright ideas on how to avoid this let me know_

```
cat <<EOF > /var/docker/caddy/Caddyfile
bluesky.example.com {
  proxy / https://bluesky {
    transparent
    insecure_skip_verify
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
  -e ACME_AGREE=true \
  --link bluesky:bluesky \
  -v /var/docker/caddy/Caddyfile:/etc/Caddyfile \
  -v /var/docker/caddy:/root/.caddy \
  --restart always \
  abiosoft/caddy
```
