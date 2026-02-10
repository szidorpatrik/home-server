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
git clone https://github.com/szidorpatrik/home-server.git && cd home-server
```

### 2. Create Directory Structure

Initialize the necessary folders for persistent data to ensure permissions are handled correctly.

```bash
mkdir -p caddy/{certs,config,data} \
         pihole \
         unbound/{dev,var}\
         searxng/searxng-data \
         jellyfin/{cache,config}
```

Update unbound dir permissions (for root.key):

```bash
sudo chown -R $USER:$USER unbound/
chmod -R 775 unbound/
```

### 3. Pull the containers

```sh
docker compose pull
```

### 4. Environment Variables

Create the `.env` file from the example.

```bash
cp .env-example .env
```

**Modify `.env`:**
Open `.env` and configure the following:

| Variable | Description |
| :--- | :--- |
| **`FTLCONF_dns_hosts`** | **Crucial:** Change `192.168.1.x` to your server's actual static IP (**No spaces between `;`**). |
| **`FTLCONF_dns_revServers`** | Update `192.168.1.1` to your router's IP and check if your subnet is `/24`. |
| **`FTLCONF_webserver_api_password`** | Your admin password for the Pi-hole dashboard. |
| **`SEARXNG_HOSTNAME`** | The local domain used in your Caddyfile for the search engine. |
| **`NEXTCLOUD_DATADIR`** | The absolute path on your host where Nextcloud data will persist. |
| **`JELLYFIN_MEDIA_DIR`** | The host path where your movies/shows are stored (e.g., inside your Nextcloud user data). |

### 5. Caddy Configuration (Choose One)

Choose the mode that fits your network setup.

#### Option A: HTTPS (Production/Standard)

Use this if you have SSL certificates or want Caddy to manage them.

```bash
cp caddy/Caddyfile-example caddy/Caddyfile
```

**Modify `caddy/Caddyfile`:**

- Ensure the site block addresses (e.g., `nextcloud.example.lan`) match exactly what you defined in the FTLCONF_dns_hosts variable in your .env file.
- Create a self signed certificate or use one if you already have it. (`*.example.lan` is expected in this config).
- Update the `(local_tls)` snippet path to point to your certificates, or remove it to use Let's Encrypt.

#### Option B: HTTP Only (Local/Testing)

Use this if you are running behind another proxy or strictly on a local LAN without SSL.

```bash
cp caddy/Caddyfile-example-http caddy/Caddyfile
```

**Modify `caddy/Caddyfile`:**

- Ensure the site block addresses (e.g., `http://nextcloud.example.lan`) match exactly what you defined in the FTLCONF_dns_hosts variable in your .env file.

### 6. Service Configuration

#### SearXNG

Copy the settings file from the example.

```bash
cp searxng/settings.yml-example searxng/settings.yml
```

**Modify `searxng/settings.yml`:**

- Ensure the `secret_key` is unique.

#### Unbound

1. Update `example.lan` to your actual domain in [unbound.conf](./unbound/unbound.conf).
2. Optimize for your CPU. The `num-threads` and `*-slabs` must be a **power of 2** (e.g., 1, 2, 4, 8).

| Component | 4-8 Cores (Recommended) | 1-2 Cores |
| :--- | :--- | :--- |
| `num-threads` | 4 | 1 or 2 |
| `*-slabs` | 4 | 1 or 2 |
| `so-rcvbuf` | 4m | 1m |

```conf
# Performance Tuning
num-threads: 4
msg-cache-slabs: 4
rrset-cache-slabs: 4
infra-cache-slabs: 4
key-cache-slabs: 4

# Buffer & Cache
so-rcvbuf: 4m
so-sndbuf: 4m
rrset-cache-size: 256m
msg-cache-size: 128m

# Security
domain-insecure: "szipat.lan"
domain-insecure: "lan"
```

### 7. Update resolved.conf

Update /etc/systemd/resolved.conf, so that it contains these:

```conf
DNS=127.0.0.1 1.1.1.1   # <- Loobpack (to pihole) and some external DNS (e.g. Cloudflare)
FallbackDNS=192.168.1.1 # <- Fallback to router if nothing works
DNSStubListener=no      # <- Stop systemd-resolved from binding port 53
```

Run this to restart systemd-resolved and create a symlink.

```bash
sudo systemctl restart systemd-resolved
sudo ln -sf /run/systemd/resolve/resolv.conf /etc/resolv.conf
```

This way even when pihole or unbound is down the server itself can resolve DNS and access the internet.

### 8. Start the Stack

```bash
docker compose up -d
```

### 9. Pi-hole

- Log into Pi-hole (`http://<server-ip>:8888/admin/`) using the password set in `.env`.
- Check the settings if they have been applied correctly:
  - Settings > DNS:
    - Custom DNS server
    - Domain
    - Conditional forwarding
  - Settings > Local DNS Records
    - Local DNS records

### 10. Nextcloud AIO

- Access the setup interface at `https://<server-ip>:8080`.
- **Important:** Ensure you enter the correct domain in the AIO interface that matches in the Caddyfile.
- The initial installation can take quite a bit.
- You should see at the top the username and password for the admin, use it to log into nextcloud at `https://nextcloud.example.lan/`

### 11. Jellyfin

- If your TV does not support your self-signed certificate, bypass the proxy by exposing the port directly. \
Access it via `http://<server-ip>:8096`

- Update [compose.yml](./compose.yml):

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

- Update the containers:

    ```sh
    docker compose up -d
    ```

### 12. Post-install

- Configure your router to have DHCP clients use Pi-hole (server-ip) as their DNS server.
- Comment out `'8888:8888'` line under `pihole` in [compose.yml](./compose.yml)
- Comment out `'8080:8080'` line under `nextcloud-aio-mastercontainer` in [compose.yml](./compose.yml) after initial installation and setup (optional containers, adding users etc.)
- Run this to apply the changes:

    ```sh
    docker compose up -d
    ```

### 13. Testing

Run these commands from a different computer on the same network:

1. **Verify DNS Filtering:** `nslookup doubleclick.net <server-ip>` (Should return `0.0.0.0`)
2. **Verify Recursive Resolution:** `nslookup google.com <server-ip>` (Should return a valid IP)
3. **Verify Local Domains:** `curl -I http://nextcloud.example.lan` (Should return a 200 or 301 status from Caddy)
