# Home server

A complete Docker Compose stack for self-hosting privacy-focused services.

This setup includes:

- **[Caddy](https://caddyserver.com/)**: Reverse proxy with automatic HTTPS or HTTP-only modes.
- **[Nextcloud AIO](https://github.com/nextcloud/all-in-one)**: Full productivity suite.
- **[Pihole](https://github.com/pi-hole/pi-hole)**: Network-wide ad blocking.
- **[Unbound](https://nlnetlabs.nl/projects/unbound/about/)**: Recursive, validating DNS resolver.
- **[Jellyfin](https://github.com/jellyfin/jellyfin)**: Media server.
- **[SearXNG](https://github.com/searxng/searxng)**: Privacy-respecting metasearch engine.

## Prerequisites

- **Docker Engine**: Must be installed natively (e.g., via `apt`, `dnf`, or the official install script).
- **Do NOT** use the Snap package version of Docker (`snap install docker`). It is known to cause permission issues with volume mounts and `bind` mounts used in this project.

## Setup

### 1. Clone this repository

```sh
git clone https://github.com/szidorpatrik/home-server.git \
cd home-server
```

### 2. Create Directory Structure

Initialize the necessary folders for persistent data to ensure permissions are handled correctly.

```bash
mkdir -p caddy/{certs,config,data} \
         jellyfin/{cache,config} \
         pihole/etc-pihole \
         searxng/searxng-data \
         unbound/{dev,var}
```

### 3. Pull the containers

```sh
docker compose pull
```

### 4. Run pihole

Without internet access, it can't download it's gravity file.

```sh
docker compose up -d pihole
```

If you can access pihole dashboard at `http:<server-ip>:8888`, with password in the `.env` file remove the container:

```sh
docker compose down pihole
```

### 5. Environment Variables

Create the `.env` file from the example.

```bash
cp .env-example .env
```

**Modify `.env`:**
Open `.env` and configure the following:

- `SEARXNG_HOSTNAME`: Your domain for search (e.g., `search.mydomain.lan`).
- `FTLCONF_webserver_api_password`: Set a strong password for the Pi-hole admin panel.
- `NEXTCLOUD_DATADIR`: Path on your host where Nextcloud files will be stored.
- `JELLYFIN_MEDIA_DIR`: Path to your media library (e.g. path/to/nextcloud/user/files/jellyfin).

### 6. Caddy Configuration (Choose One)

Choose the mode that fits your network setup.

#### Option A: HTTPS (Production/Standard)

Use this if you have SSL certificates or want Caddy to manage them.

```bash
cp caddy/Caddyfile-example caddy/Caddyfile
```

**Modify `caddy/Caddyfile`:**

- Replace `search.example.lan`, `pihole.example.lan`, etc., with your actual domains.
- Update the `(local_tls)` snippet path to point to your certificates, or remove it to use Let's Encrypt.

#### Option B: HTTP Only (Local/Testing)

Use this if you are running behind another proxy or strictly on a local LAN without SSL.

```bash
cp caddy/Caddyfile-example-http caddy/Caddyfile
```

**Modify `caddy/Caddyfile`:**

- Replace `http://search.example.lan` with your local IP or internal domains, which can be set in pihole's local dns records.

### 7. Service Configuration

#### SearXNG

Copy the settings file from the example.

```bash
cp searxng/settings.yml-example searxng/settings.yml
```

**Modify `searxng/settings.yml`:**

- Ensure the `secret_key` is unique.

#### Unbound DNS

The configuration file is located at `unbound/unbound.conf`.

**Action Required:**

- Download the root hints file (required for recursive DNS):

```bash
curl -o unbound/root.hints https://www.internic.net/domain/named.root
```

### 8. Start the Stack

```bash
docker compose up -d
```

## Post-Install

### Pi-hole

- Log into Pi-hole (`http://<server-ip>:8888/admin`) using the password set in `.env`.
- Set up your DNS records at `Settings > Local DNS Records`.
- Configure your router to have DHCP clients use Pi-hole as their DNS server.
- Pi-hole should be pre-configured to use Unbound as its upstream DNS.
  - Check DNS (Expert toggle) > Custom DNS servers, set it to `unbound#53`.
  - Set Conditional forwarding: `true,<subnet/mask>,<router ip>` (e.g. `true,192.168.1.0/24,192.168.1.1`)
- Remove/Comment out `'8888:8888'` line from pihole in [compose.yml](./compose.yml) then run:

    ```sh
    docker compose up -d
    ```

- Make a copy of your current /etc/resolv.conf:

    ```sh
    sudo cp /etc/resolv.conf /etc/resolv.conf.old
    ```

- Edit /etc/resolv.conf so that it contains the ip of your server and nothing more:

    ```sh
    nameserver 192.168.1.128 # <- This server's IP
    ```

- You should be able to access pihole at `https://pihole.example.lan/`.

### Nextcloud AIO

- Access the setup interface at `https://<your-ip>:8080`.
- Because `SKIP_DOMAIN_VALIDATION=true` is set, you can configure it using your internal domain.
- **Important:** Ensure you enter the correct domain in the AIO interface that matches your Caddyfile.

### Jellyfin

- If you can't install your self signed cert on your smart tv, uncomment (remove `#`)

    Before:

    ```yml
    #ports:
    #  - '8096:8096'
    ```

    After:

    ```yml
    ports:
      - '8096:8096'
    ```

    Run, then access it at `http://jellyfin.example.lan:8096` or `http:<server-ip>:8096`

    ```sh
    docker compose up -d
    ```
