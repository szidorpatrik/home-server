# Home server

This repository contains a home server setup with self-hosted:

- [Pihole](https://github.com/pi-hole/pi-hole) dns and network wide ad-blocking
- [Jellyfin](https://github.com/jellyfin/jellyfin) for Netflix/Plex like functionality
- [Nextcloud server](https://github.com/nextcloud/server) for Google Drive like functionality, you can install different apps as well (Calendar, Chat, etc.)
- [Redis](https://github.com/redis/redis) for caching (used by Nextcloud to improve performance)
- [MariaDB](https://github.com/MariaDB/server) as the database for Nextcloud

All commands assume that you are inside your docker dir!

This command will create the missing directories and copy .env-example to .env.

## Directories

#### Symbolic links

Symbolic links are optional if your media and nextcloud data are stored on a mounted drive, but it's easier to navigate from the docker dir and won't need to change the [docker-compose.yml](./docker-compose.yml) volumes as its designed to use symbolic links.

If you want to use symbolic links, run:

```sh
mkdir -p caddy caddy/certs caddy/config caddy/data jellyfin jellyfin/cache jellyfin/config mariadb nextcloud nextcloud/apps nextcloud/config pihole pihole/etc-pihole redis && cp .env-example .env
```

If you don't want to use symbolic links, run:

```sh
mkdir -p caddy caddy/certs caddy/config caddy/data jellyfin jellyfin/cache jellyfin/config jellyfin/media mariadb nextcloud nextcloud/apps nextcloud/config nextcloud/data pihole pihole/etc-pihole redis && cp .env-example .env
```

### Jellyfin media (Symbolic link)

```sh
ln -s /your/media/dir /your/docker/dir/jellyfin/media
```

### Nextcloud data (Symbolic link)

```sh
ln -s /your/data/dir /your/docker/dir/nextcloud/data
```

## DNS (Pihole)

The web interface won't show up because only the dns ports are forwarded!
In order to access the web allow the `'80:80'` port:

```yml
ports:
  - "80:80" # Remove after local dns records and Caddy are configured
  - "53:53/tcp"
  - "53:53/udp"
```

Run the pihole container:

```sh
docker compose up -d pihole
```

To access the web interface go to: `http://your.server.ip.address/admin` \
Your web interface password is located inside the **[.env](./.env)** in the `Pihole` section!

Change it to something secure or it's even safer to remove the password from **[.env](./.env)** and change it from inside the container! \
(Don't forget to [down](#down-remove-containers) and [up](#run-start-containers) the pihole service if only changed from the .env file!)

To change the password inside the container, run:

```sh
docker exec -it pihole pihole setpassword
```

Follow the prompts to set a new password.

Now configure your local dns records under Settings -> Local DNS Records ([http://your.server.ip.address/admin/settings/dnsrecords](https://pihole.your-domain.lan/admin/settings/dnsrecords)) \
**Don't forget to set your router's and/or client's primary dns to your server's ip address!**

Test the dns records with `nslookup`.

If they resolve then remove the `"80:80"` port from pihole container, because `Caddy` will use it and run:

```sh
docker compose up -d pihole
```

## Reverse proxy (Caddy)

If you want to use only HTTP, then:

- Edit your **[Caddyfile](./caddy/Caddyfile)**

- Remove or comment (#) all lines starting with `tls /etc/caddy/certs/`

- Add `http://` to all sites (without `http`, it will automatically redirect to HTTPS)\
  Example:

  ```conf
  http://pihole.your-domain.lan {
    redir / /admin 301
    reverse_proxy /* pihole:80
  }
  
  http://nextcloud.your-domain.lan {
    reverse_proxy /* nextcloud:80
  }
  
  http://jellyfin.your-domain.lan {
    reverse_proxy /* jellyfin:8096
  }
  ```

If you want to use HTTPS, then:

- Create a `rootCA.crt` and `rootCA.key`, then sign a `wildcard.your-domain.lan.crt` and `wildcard.your-domain.lan.key` in the `caddy/certs/` dir.

- Update the `Nextcloud` section in **[.env](./.env)** and update **[Caddyfile](./caddy/Caddyfile)** proxies with your domain names and wildcard cert. For example:

  ```conf
  pihole.your-domain.lan {
    tls /etc/caddy/certs/wildcard.your-domain.lan.crt /etc/caddy/certs/wildcard.your-domain.lan.key
    redir / /admin 301
    reverse_proxy /* pihole:80
  }
  
  nextcloud.your-domain.lan {
    tls /etc/caddy/certs/wildcard.your-domain.lan.crt /etc/caddy/certs/wildcard.your-domain.lan.key
    reverse_proxy /* nextcloud:80
  }
  
  jellyfin.your-domain.lan {
    tls /etc/caddy/certs/wildcard.your-domain.lan.crt /etc/caddy/certs/wildcard.your-domain.lan.key
    reverse_proxy /* jellyfin:8096
  }
  ```

## Nextcloud Redis Setup

Nextcloud uses Redis for caching to improve performance. Ensure the `REDIS_HOST` variable in your **[.env](./.env)** file matches the Redis container name (`redis`).

Then, add the following to your Nextcloud configuration file (**[config.php](./nextcloud/config/config.php)**) to enable Redis:

```php
'memcache.local' => '\\OC\\Memcache\\Redis',
'memcache.locking' => '\\OC\\Memcache\\Redis',
'redis' => [
    'host' => 'redis',
    'port' => 6379,
],
```

Save the file and restart the Nextcloud container:

```sh
docker compose restart nextcloud
```

## Nextcloud PHP Configuration

Nextcloud’s PHP settings are customized in the **[php.ini-production](./nextcloud/php.ini-production)** file, which is mounted from `./nextcloud/php.ini-production`.

If this file doesn’t exist, create it and add or modify the following settings to optimize performance:

```ini
memory_limit=512M
max_execution_time = 3600
post_max_size = 0
upload_max_filesize = 0
max_file_uploads = 200
opcache.memory_consumption=128M
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.max_wasted_percentage=10
```

Save the file and restart the Nextcloud container:

```sh
docker compose restart nextcloud
```

## Nextcloud MPM Prefork Configuration

Nextcloud uses Apache with the MPM Prefork module, and its settings are customized in the **[mpm_prefork.conf](./nextcloud/mpm_prefork.conf)** file, which is mounted from `./nextcloud/mpm_prefork.conf`.

If this file doesn’t exist, create it with the following settings to optimize Apache for low-memory systems while handling moderate traffic:

```conf
# prefork MPM
# StartServers: number of server processes to start
# MinSpareServers: minimum number of server processes which are kept spare
# MaxSpareServers: maximum number of server processes which are kept spare
# MaxRequestWorkers: maximum number of server processes allowed to start
# MaxConnectionsPerChild: maximum number of requests a server process serves

StartServers            5
MinSpareServers         3
MaxSpareServers         8
MaxRequestWorkers       30
MaxConnectionsPerChild  1000
```

Adjust based on your system’s memory and traffic.

## MariaDB Configuration

MariaDB settings are customized in the [my.cnf](./mariadb/my.cnf) file (mounted as `./mariadb:/var/lib/mysql` in the container). \
This allows the settings to persist across container restarts.

Create or modify the file at `./mariadb/my.cnf` with the following settings:

```ini
[mysqld]
innodb_buffer_pool_size=256M
query_cache_size=64M
tmp_table_size=64M
max_connections=50
```

After creating or modifying the file, restart the MariaDB container to apply the changes:

```sh
docker compose restart mariadb
```

## Resource Limits

The [docker-compose.yml](./docker-compose.yml) sets memory limits for some services to prevent them from consuming too many resources:

- **Redis**: Limited to 2400MB with a 2200MB reservation.
- **Jellyfin**: Limited to 2048MB with a 1024MB reservation.

Adjust these values in the [docker-compose.yml](./docker-compose.yml) based on your system’s available memory. After making changes, restart the services:

```sh
docker compose up -d
```

---

## Docker commands

### Run (Start containers)

```sh
docker compose up -d
```

### Stop (Stop containers)

```sh
docker compose stop
```

### Restart (Restart containers)

```sh
docker compose restart
```

### Down (Remove containers)

```sh
docker compose down
```

### Down (Remove containers and volumes)

```sh
docker compose down -v
```
