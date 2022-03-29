<?php

return [
    'driver' => 'oss',
    'access_id' => env('OSS_ACCESS_KEY_ID'),
    'access_key' => env('OSS_ACCESS_KEY_SECRET'),
    'bucket' => env('OSS_BUCKET'),
    'endpoint' => env('OSS_ENDPOINT'), // OSS 外网节点或自定义外部域名
    'endpoint_internal' => env('OSS_ENDPOINT_INTERNAL'), // 如果为空，则默认使用 endpoint 配置
    'cdnDomain' => env('OSS_DOMAIN'), // 如果不为空，getUrl会判断cdnDomain是否设定来决定返回的url，如果cdnDomain未设置，则使用endpoint来生成url，否则使用cdn
    'ssl' => env('OSS_SSL', false), // true to use 'https://' and false to use 'http://'. default is false,
    'prefix' => env('OSS_PREFIX'), // 路径前缀
    'options' => [],
];
