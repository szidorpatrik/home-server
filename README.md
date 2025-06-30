# Home server

This repository contains a home server setup with self-hosted:

- [SearXNG](https://github.com/searxng/searxng) internet metasearch engine
- [Pihole](https://github.com/pi-hole/pi-hole) dns, blocks ads and trackers across your entire network
- [Jellyfin](https://github.com/jellyfin/jellyfin) media server
- [Nextcloud server](https://github.com/nextcloud/server) file hosting service
- [MariaDB](https://github.com/MariaDB/server) as the database for Nextcloud
- [Redis](https://github.com/redis/redis) for caching (used by Nextcloud and SearXNG to improve performance)

## Clone this repository

```sh
git clone https://github.com/szidorpatrik/home-server.git && \
cd home-server
```

## Directories

The commands will create the necessary dir structure and config files.

### Symlinks

Symlinks are optional if your media and nextcloud data are stored on a mounted drive, but it's easier to navigate from the docker dir and won't need to change the [docker-compose.yml](./docker-compose.yml-example) volumes as its designed to use symlinks.

If you **want** to use symlinks, run:

```sh
mkdir -p caddy caddy/certs caddy/config caddy/data jellyfin jellyfin/cache jellyfin/config mariadb nextcloud nextcloud/apps nextcloud/config pihole pihole/etc-pihole redis && \
cp .env-example .env && \
cp searxng/settings.yml-example searxng/settings.yml && \
cp docker-compose.yml-example docker-compose.yml && \
cp nextcloud/mpm_prefork.conf-example nextcloud/mpm_prefork.conf && \
cp nextcloud/php.ini-production-example nextcloud/php.ini-production && \
cp mariadb/my.cnf-example mariadb/my.cnf
```

If you **don't want** to use symlinks, run:

```sh
mkdir -p caddy caddy/certs caddy/config caddy/data jellyfin jellyfin/cache jellyfin/config jellyfin/media mariadb nextcloud nextcloud/apps nextcloud/config nextcloud/data pihole pihole/etc-pihole redis && \
cp .env-example .env && \
cp searxng/settings.yml-example searxng/settings.yml && \
cp docker-compose.yml-example docker-compose.yml && \
cp nextcloud/mpm_prefork.conf-example nextcloud/mpm_prefork.conf && \
cp nextcloud/php.ini-production-example nextcloud/php.ini-production && \
cp mariadb/my.cnf-example mariadb/my.cnf
```

### Nextcloud data (Symlink)

```sh
ln -s /your/data/dir /your/path/to/home-server/nextcloud/data

#Example if the data dir is mounted as 'external-drive':
ln -s /mnt/external-drive/nextcloud /home/user/home-server/nextcloud/data
```

## DNS (Pihole)

Run the pihole container:

```sh
docker compose up -d pihole
```

To change the password inside the container, run:

```sh
docker exec -it pihole pihole setpassword
```

Follow the prompts to set a new password.

To access the web interface go to: `http://your.server.ip.address/admin` \
Your web interface password is located inside the **[.env](./.env-example)** in the `Pihole` section!

Change it to something secure or it's even safer to remove the password from **[.env](./.env-example)** and change it from inside the container! \
(Don't forget to [down](#down-remove-containers) and [up](#run-start-containers) the pihole service if only changed from the .env file!)

Now configure your local dns records under Settings -> Local DNS Records ([http://your.server.ip.address/admin/settings/dnsrecords](https://pihole.your-domain.lan/admin/settings/dnsrecords)) \
**Don't forget to set your router's and/or client's primary dns to your server's ip address!**

| **Domain**                 | **IP Address**         |
|:---------------------------|:-----------------------|
| search.your-domain.lan     | your.server.ip.address |
| pihole.your-domain.lan     | your.server.ip.address |
| jellyfin.your-domain.lan   | your.server.ip.address |
| nextcloud.your-domain.lan  | your.server.ip.address |

Test the dns records with:

- `nslookup search.your-domain.lan`
- `nslookup pihole.your-domain.lan`
- `nslookup jellyfin.your-domain.lan`
- `nslookup nextcloud.your-domain.lan`

If they resolve then remove the `'80:80'` port from pihole container inside the [docker-compose.yml](./docker-compose.yml-example), because `Caddy` will use it and run:

```sh
docker compose up -d pihole
```

## Search engine (SearXNG)

Update `secret_key` in [`./searxng/settings.yml`](./searxng/settings.yml-example)

For other configurations see [searxng/searxng-docker](https://github.com/searxng/searxng-docker)

## Reverse proxy (Caddy)

Update the domains in [`.env`](./.env-example) Nextcloud section

  ```conf
  NEXTCLOUD_TRUSTED_DOMAINS=nextcloud.your-domain.lan
  OVERWRITEHOST=nextcloud.your-domain.lan
  ```

#### If you want to use only HTTP, then

- Copy [`Caddyfile-example-http`](./caddy/Caddyfile-example-http) as `Caddyfile`

  ```sh
  cp ./caddy/Caddyfile-example-http ./caddy/Caddyfile
  ```

- Edit [`.env`](./.env) Nextcloud section `OVERWRITEPROTOCOL=https` to:

  ```conf
  OVERWRITEPROTOCOL=http
  ```

#### If you want to use HTTPS, then

- Copy [`Caddyfile-example`](./caddy/Caddyfile-example) as `Caddyfile`

  ```sh
  cp ./caddy/Caddyfile-example ./caddy/Caddyfile
  ```

- Create a `rootCA.crt` and `rootCA.key`, then sign a `wildcard.your-domain.lan.crt` and `wildcard.your-domain.lan.key` in the `caddy/certs/` dir.

- Update the `Nextcloud` section in **[.env](./.env-example)** and update **[Caddyfile](./caddy/Caddyfile-example)** proxies with your domain names and wildcard cert. For example:

  ```conf
  # Before
  #...
  pihole.your-domain.lan {
    tls /etc/caddy/certs/wildcard.your-domain.lan.crt /etc/caddy/certs/wildcard.your-domain.lan.key
    redir / /admin 301
    reverse_proxy /* pihole:80
  }
  #...
  ```

  ```conf
  # After
  #...
  pihole.your-updated-domain.lan {
    tls /etc/caddy/certs/wildcard.your-updated-domain.lan.crt /etc/caddy/certs/wildcard.your-updated-domain.lan.key
    redir / /admin 301
    reverse_proxy /* pihole:80
  }
  #...
  ```

Start Caddy:

```sh
docker compose up -d caddy
```

## MariaDB Configuration (Optional, configured by default)

MariaDB settings are customized in the [my.cnf](./mariadb/my.cnf-example) file (mounted as `./mariadb:/var/lib/mysql` in the container). \
This allows the settings to persist across container restarts.

Create or modify the file at `./mariadb/my.cnf` with the following settings:

```ini
[mysqld]
innodb_buffer_pool_size=256M
query_cache_size=64M
tmp_table_size=64M
max_connections=50
```

Start MariaDB:

```sh
docker compose up -d mariadb
```

## Nextcloud web configuration

Start Nextcloud and wait until it initializes:

```sh
docker compose up -d nextcloud
```

Create a user and wait for the installation process, no further actions needed.

If it want's you to setup manually, then slect MariaDB as database and fill in the form with the corresponding variables in `Nextcloud` and `MariaDB` section in the [.env](./.env-example) file.

```conf
...
#------Nextcloud------
...
MYSQL_HOST=mariadb
#------MariaDB------
MYSQL_ROOT_PASSWORD=root
MYSQL_DATABASE=ncdb
MYSQL_USER=nextcloud
MYSQL_PASSWORD=nextcloud
...
```

## Nextcloud Redis Setup (Optional, recommended)

This setup is already configured to use Redis.

Ensure the `REDIS_HOST` variable in your **[.env](./.env-example)** file matches the Redis container name (`redis`).

By default this step is optional, but if the `./nextcloud/config/config.php` misses these lines then:

Add/modify the following to your Nextcloud configuration file (**[config.php](./nextcloud/config/config.php)**) to enable Redis:

```php
'memcache.local' => '\\OC\\Memcache\\Redis',
'memcache.locking' => '\\OC\\Memcache\\Redis',
'redis' => [
    'host' => 'redis',
    'port' => 6379,
],
```

Restart nextcloud:

```sh
docker compose restart nextcloud
```

## Nextcloud PHP Configuration (Optional, configured by default)

Nextcloud’s PHP settings are customized in the **[php.ini-production](./nextcloud/php.ini-production-example)** file, which is mounted from `./nextcloud/php.ini-production`.

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

Save the file and restart the Nextcloud container if it runs already to apply the changes:

```sh
docker compose restart nextcloud
```

## Nextcloud MPM Prefork Configuration (Optional, configured by default)

Nextcloud uses Apache with the MPM Prefork module, and its settings are customized in the **[mpm_prefork.conf](./nextcloud/mpm_prefork.conf-example)** file, which is mounted from `./nextcloud/mpm_prefork.conf`.

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

## Jellyfin setup

After a successful nextcloud setup create a folder with a name like `Movies`.

If not created already, [create a symlink](#jellyfin-media-symlink) to `/path/to/nextcloud/user/files/Movies`.

```sh
ln -s /path/to/nextcloud/user/files/Movies /path/to/home-server/jellyfin/media
```

Check if the symlink is correct:

```sh
ls -al jellyfin/media
```

If permission is denied then use sudo, or refer [here](#fix-broken-symlinks).

If you see your movies then the symlink is working as intended and we should configure Jellyfin with the web interface.

Visit `http://jellyfin.your-domain.lan/` and create your admin user.

When prompted for your media directory select `/media` usually its the last in the select box.

Your movies should be visible.

## Resource Limits

The [docker-compose.yml](./docker-compose.yml) sets memory limits for some services to prevent them from consuming too many resources:

- **Redis**: Limited to 2400MB with a 2200MB reservation.
- **Jellyfin**: Limited to 2048MB with a 1024MB reservation.

Adjust these values in the [docker-compose.yml](./docker-compose.yml-example) based on your system’s available memory. After making changes, restart the services:

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

## How to manually copy files to nextcloud

Copy your files and dirs to your nextcloud user dir:

```sh
sudo cp -r /your/files /mnt/external/drive/nextcloud/user
```

Change the owner of the files:

```sh
sudo chown -R www-data:www-data /mnt/external/drive/nextcloud/user
```

Add permissions for everything for the owner and the group, read and execute for others:

> You should revert the permissions to original manually! \
> For debugging purposes this command is useful

```sh
sudo chmod -R 775 /mnt/external/drive/nextcloud/user
```

Update nextcloud's cache with scanning for files:

```sh
docker exec -u www-data nextcloud php occ files:scan -all
```

Wait for the process to finish, then reload nextclouds files page and the files should appear.

## Fix broken symlinks

> This is meant only for debugging purposes, the permissions for the `nextcloud` dir should be reset.

If the symlinks appear broken, or 'Permission denied' when trying to execute ls or any other command, add read and execute permissions for the target folder!

Check permissions at each level of the path to pinpoint the issue. \
Start from the top and work your way down (example):

```sh
ls -ld /mnt
ls -ld /mnt/external
ls -ld /mnt/external/drive/nextcloud
ls -ld /mnt/external/drive/nextcloud/user # <--- Here it throws 'Permission denied'
ls -ld /mnt/external/drive/nextcloud/user/files
```

Add read and execute permissions:

```sh
sudo chmod -R 755 /mnt/external/drive/nextcloud/user
```

Repeat this process for any other symlinks!
