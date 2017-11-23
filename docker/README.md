## Docker setup

This is an attempt at getting BlueSky to run within docker.  This should allow it to be easily setup on a wide variety of systems that support docker containers.

### Environment variables

These variables can be overriden when you run the bluesky docker container.

Variable | Default Value | Note
--- | --- | ---
USE_HTTP | 0 | Set to 1 to use HTTP instead of HTTPS
SERVERFQDN | localhost
WEBADMINPASS | admin
MYSQLROOTPASS | admin
EMAILALERT | root@localhost

### Docker volumes

The following locations are mappable locations within the container.  These will be used for data that needs to persist between runs.

Path | Note
--- | ---
/certs | bluesky ssh keys
/home/admin/.ssh | ?
/home/bluesky/.ssh | ?
/home/admin/newkeys | ?
/home/bluesky/newkeys | ?
/tmp/pkg | Client install pkg

### Example Setup: Persistant storage

#### MySQL

Because we are also using MySQL within docker in this example setup, we need to setup the local storage first.  In the example below we are mapping the `/private/var/docker/bluesky/db` directory on the host to have the persistent mysql data.

First create and set permissions on the local folder:
_you may want to modify permissions depending on how you are running docker_
```
sudo mkdir -p /private/var/docker/bluesky/db
sudo chmod -R 777 /private/var/docker/bluesky
```

#### BlueSky

Because docker images do not keep their data between runs, we need to map locations (volumes) for persistent storage.  In the example below we are mapping various directories within `/private/var/docker/bluesky/` on the host to have the persistent key data.

First create and set permissions on the local folder:
_you may want to modify permissions depending on how you are running docker_
```
sudo mkdir -p /private/var/docker/bluesky/certs
sudo mkdir -p /private/var/docker/bluesky/admin.ssh
sudo mkdir -p /private/var/docker/bluesky/admin.newkeys
sudo mkdir -p /private/var/docker/bluesky/bluesky.ssh
sudo mkdir -p /private/var/docker/bluesky/bluesky.newkeys
sudo mkdir -p /private/var/docker/bluesky/pkg
sudo chmod -R 777 /private/var/docker/bluesky
```

### Run BlueSky

Setup MySQL:
_note that currently the root password is embedded in this command_
```
docker run -d --name bluesky_db \
	-v /private/var/docker/bluesky/db:/var/lib/mysql \
	-e MYSQL_ROOT_HOST=% \
	-e MYSQL_ROOT_PASSWORD=admin \
	mysql/mysql-server:5.7
```

Wait for the MySQL docker container to initialize...

Setup BlueSky:
```
docker run -d --name bluesky \
	--link bluesky_db:db \
	-e SERVERFQDN=bluesky.example.com \
	-v /private/var/docker/bluesky/certs:/certs \
	-v /private/var/docker/bluesky/admin.ssh:/home/admin/.ssh \
	-v /private/var/docker/bluesky/bluesky.ssh:/home/bluesky/.ssh \
	-v /private/var/docker/bluesky/admin.newkeys:/home/admin/newkeys \
	-v /private/var/docker/bluesky/bluesky.newkeys:/home/bluesky/newkeys \
	-v /private/var/docker/bluesky/pkg:/tmp/pkg \
	-p 80:80 \
	-p 443:443 \
	-p 3122:22 \
	sphen/bluesky
```

### Troubleshooting

You can get the logs from a container to see if there are any issues:
```
docker logs bluesky
```

You can also shell into the bluesky container if needed:
```
docker exec -it bluesky bash
```

### TODO

~~- Set up persistant storage for the bluesky container.~~
  - ~~This will require alot of changes to get those files within different directories.~~
- Fix SSL if being used in container.
  - ~~right now complaining about "/etc/ssl/certs/ssl-cert-snakeoil.pem"~~
  - outline instructions for mapping your own cert.
- Add example of Caddy docker container in front of bluesky for auto-generated SSL certificates :)
- Auto-generate client installer

### Links

- Auto-build on Docker Hub: https://hub.docker.com/r/sphen/bluesky/
- Forked BlueSky on GitHub: https://github.com/logicnow/BlueSky
