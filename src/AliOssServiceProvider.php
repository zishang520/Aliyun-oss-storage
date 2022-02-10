<?php

namespace luoyy\AliOSS;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use OSS\OssClient;

class AliOssServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('oss', function ($app, $config) {
            $accessId = $config['access_id'];
            $accessKey = $config['access_key'];

            $cdnDomain = empty($config['cdnDomain']) ? '' : $config['cdnDomain'];
            $bucket = $config['bucket'];
            $ssl = empty($config['ssl']) ? false : $config['ssl'];
            $isCname = empty($cdnDomain) ? false : true;

            $endPoint = $config['endpoint']; // 默认作为外部节点
            $epInternal = empty($config['endpoint_internal']) ? ($isCname ? $cdnDomain : $endPoint) : $config['endpoint_internal']; // 内部节点
            $options = $config['options'] ?? [];

            $hostname = $isCname ? $cdnDomain : $endPoint;

            $client = new OssClient($accessId, $accessKey, $epInternal, $isCname ? empty($config['endpoint_internal']) : false);
            $client->setUseSSL($ssl);
            $adapter = new AliOssAdapter($client, $bucket, $hostname, $ssl, $isCname, $epInternal, $config['prefix'] ?? '', options: $options);

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
}
