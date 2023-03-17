# Aliyun-oss-storage for Laravel 10+
Aliyun oss filesystem storage adapter for laravel 10. You can use Aliyun OSS just like laravel Storage as usual.    
借鉴了一些优秀的代码，综合各方，同时做了更多优化，将会添加更多完善的接口和插件，打造Laravel最好的OSS Storage扩展
## Inspired By
- [thephpleague/flysystem-aws-s3-v3](https://github.com/thephpleague/flysystem-aws-s3-v3)
- [apollopy/flysystem-aliyun-oss](https://github.com/apollopy/flysystem-aliyun-oss) 

## Require
- Laravel 10+
- cURL extension

## Installation
In order to install AliOSS-storage, just add

    "luoyy/ali-oss-storage": "^4.0"

to your composer.json. Then run `composer install` or `composer update`.  
Or you can simply run below command to install:

    "composer require luoyy/ali-oss-storage:^4.0"
    
Then in your `config/app.php` add this line to providers array:
```php
luoyy\AliOSS\AliOssServiceProvider::class,
```
## Configuration
Add the following in `app/filesystems.php`:
```php
'disks'=>[
    ...
    'oss' => [
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
            'throw' => true,
    ],
    ...
]
```
Then set the default driver in app/filesystems.php:
```php
'default' => 'oss',
```
Ok, well! You are finish to configure. Just feel free to use Aliyun OSS like Storage!

## Usage
See [Larave doc for Storage](https://laravel.com/docs/5.2/filesystem#custom-filesystems)
Or you can learn here:

> First you must use Storage facade

```php
use Illuminate\Support\Facades\Storage;
```    
> Then You can use all APIs of laravel Storage

```php
Storage::disk('oss'); // if default filesystems driver is oss, you can skip this step

//fetch all files of specified bucket(see upond configuration)
Storage::files($directory);
Storage::allFiles($directory);

Storage::put('path/to/file/file.jpg', $contents); //first parameter is the target file path, second paramter is file content
Storage::putFile('path/to/file/file.jpg', 'local/path/to/local_file.jpg'); // upload file from local path

Storage::get('path/to/file/file.jpg'); // get the file object by path
Storage::exists('path/to/file/file.jpg'); // determine if a given file exists on the storage(OSS)
Storage::size('path/to/file/file.jpg'); // get the file size (Byte)
Storage::lastModified('path/to/file/file.jpg'); // get date of last modification

Storage::directories($directory); // Get all of the directories within a given directory
Storage::allDirectories($directory); // Get all (recursive) of the directories within a given directory

Storage::copy('old/file1.jpg', 'new/file1.jpg');
Storage::move('old/file1.jpg', 'new/file1.jpg');
Storage::rename('path/to/file1.jpg', 'path/to/file2.jpg');

Storage::prepend('file.log', 'Prepended Text'); // Prepend to a file.
Storage::append('file.log', 'Appended Text'); // Append to a file.

Storage::delete('file.jpg');
Storage::delete(['file1.jpg', 'file2.jpg']);

Storage::makeDirectory($directory); // Create a directory.
Storage::deleteDirectory($directory); // Recursively delete a directory.It will delete all files within a given directory, SO Use with caution please.

// upgrade logs
// new plugin for v2.0 version
Storage::putRemoteFile('target/path/to/file/jacob.jpg', 'http://example.com/jacob.jpg'); //upload remote file to storage by remote url
// new function for v2.0.1 version
Storage::url('path/to/img.jpg') // get the file url
Storage::temporaryUrl('path/to/img.jpg', 900) // Get a temporary URL for the file at the given path.
```

## Documentation
More development detail see [Aliyun OSS DOC](https://help.aliyun.com/document_detail/32099.html?spm=5176.doc31981.6.335.eqQ9dM)
## License
Source code is release under MIT license. Read LICENSE file for more information.
