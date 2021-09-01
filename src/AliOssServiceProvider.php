<?php

namespace luoyy\AliOSS;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use luoyy\AliOSS\Plugins\PutFile;
use luoyy\AliOSS\Plugins\PutRemoteFile;
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
        $this->app['filesystem']->extend('oss', function ($app, $config) {
            $accessId = $config['access_id'];
            $accessKey = $config['access_key'];

            $cdnDomain = empty($config['cdnDomain']) ? '' : $config['cdnDomain'];
            $bucket = $config['bucket'];
            $ssl = empty($config['ssl']) ? false : $config['ssl'];
            $isCname = empty($cdnDomain) ? false : true;
            $debug = empty($config['debug']) ? false : $config['debug'];

            $endPoint = $config['endpoint']; // 默认作为外部节点
            $epInternal = empty($config['endpoint_internal']) ? ($isCname ? $cdnDomain : $endPoint) : $config['endpoint_internal']; // 内部节点

            if ($debug) {
                Log::debug('OSS config:', $config);
            }

            $hostname = $isCname ? $cdnDomain : $endPoint;

            $client = new OssClient($accessId, $accessKey, $epInternal, $isCname ? empty($config['endpoint_internal']) : false);
            $adapter = new AliOssAdapter($client, $bucket, $hostname, $ssl, $isCname, $epInternal, $debug, $config['prefix'] ?? null);

            //Log::debug($client);
            $filesystem = new Filesystem($adapter);

            $filesystem->addPlugin(new PutFile());
            $filesystem->addPlugin(new PutRemoteFile());
            //$filesystem->addPlugin(new CallBack());
            return $filesystem;
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
