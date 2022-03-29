<?php

namespace luoyy\AliOSS\Providers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use OSS\OssClient;

class AliOssServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->setupConfig();

        $this->app->make('filesystem')->extend('oss', function ($app, array $config) {
            $cdnDomain = empty($config['cdnDomain']) ? '' : $config['cdnDomain'];
            $ssl = empty($config['ssl']) ? false : (bool) $config['ssl'];
            $isCname = empty($cdnDomain) ? false : true;

            $hostname = $isCname ? $cdnDomain : $config['endpoint'];

            $epInternal = empty($config['endpoint_internal']) ? $hostname : $config['endpoint_internal']; // 内部节点

            $client = new OssClient($config['access_id'], $config['access_key'], $epInternal, $isCname ? empty($config['endpoint_internal']) : false);
            $client->setUseSSL($ssl);

            $adapter = new AliOssAdapter($client, $config['bucket'], $hostname, $ssl, $isCname, $epInternal, $config['prefix'] ?? '', options: $config['options'] ?? []);

            // symlink
            FilesystemAdapter::macro('symlink', fn (string $symlink, string $path, array $config = []) => $adapter->symlink($symlink, $path, new Config($config)));

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Setup the config.
     *
     * @return void
     */
    protected function setupConfig()
    {
        $this->mergeConfigFrom(realpath(__DIR__ . '/../config/config.php'), 'filesystems.disks.oss');
    }
}
