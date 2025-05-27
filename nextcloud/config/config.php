<?php
$CONFIG = array (
  'htaccess.RewriteBase' => '/',
  'memcache.local' => '\\OC\\Memcache\\Redis',
  'memcache.locking' => '\\OC\\Memcache\\Redis',
  'redis' => [
      'host' => 'redis',
      'port' => 6379,
  ],
  'apps_paths' => 
  array (
    0 => 
    array (
      'path' => '/var/www/html/apps',
      'url' => '/apps',
      'writable' => false,
    ),
    1 => 
    array (
      'path' => '/var/www/html/custom_apps',
      'url' => '/custom_apps',
      'writable' => true,
    ),
  ),
  'overwritehost' => 'nextcloud.szipat.lan',
  'overwriteprotocol' => 'https',
  'upgrade.disable-web' => true,
  'instanceid' => 'ocyzgcengzt2',
  'passwordsalt' => 'vaZEr7R16Ac1wB6LLmgATgPPpkQukf',
  'secret' => '5W15mKFKqjWLh1eK2AurpV3BGeiJyoqjzoEIg2XfRHOS4zKC',
  'trusted_domains' => 
  array (
    0 => 'nextcloud.szipat.lan',
  ),
  'datadirectory' => '/var/www/html/data',
  'dbtype' => 'mysql',
  'version' => '31.0.5.1',
  'overwrite.cli.url' => 'https://nextcloud.szipat.lan',
  'dbname' => 'ncdb',
  'dbhost' => 'mariadb',
  'dbport' => '',
  'dbtableprefix' => 'oc_',
  'mysql.utf8mb4' => true,
  'dbuser' => 'nextcloud',
  'dbpassword' => 'nextcloud',
  'installed' => true,
  'app_install_overwrite' => 
  array (
  ),
);
