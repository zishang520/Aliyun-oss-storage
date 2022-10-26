<?php

/**
 * Created by jacob.
 * Date: 2016/5/19 0019
 * Time: 下午 17:07.
 */

namespace luoyy\AliOSS;

use Carbon\Carbon;
use DateTimeInterface;
use Generator;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use luoyy\AliOSS\Contracts\PortableVisibilityConverter;
use luoyy\AliOSS\Contracts\VisibilityConverter;
use OSS\Core\OssException;
use OSS\OssClient;
use Throwable;

class AliOssAdapter implements FilesystemAdapter
{
    /**
     * @var array<string, string>
     */
    protected const RESULT_MAP = [
        'Body' => 'raw_contents',
        'Content-Length' => 'size',
        'ContentType' => 'mimetype',
        'Size' => 'size',
        'StorageClass' => 'storage_class',
    ];

    /**
     * @var array<int, string>
     */
    protected const META_OPTIONS = [
        'CacheControl',
        'Expires',
        'ServerSideEncryption',
        'Metadata',
        'ACL',
        'ContentType',
        'ContentDisposition',
        'ContentLanguage',
        'ContentEncoding',
    ];

    /**
     * @var array<string, string>
     */
    protected const META_MAP = [
        'CacheControl' => 'Cache-Control',
        'Expires' => 'Expires',
        'ServerSideEncryption' => 'x-oss-server-side-encryption',
        'Metadata' => 'x-oss-metadata-directive',
        'ACL' => 'x-oss-object-acl',
        'ContentType' => 'Content-Type',
        'ContentDisposition' => 'Content-Disposition',
        'ContentLanguage' => 'response-content-language',
        'ContentEncoding' => 'Content-Encoding',
    ];

    /**
     * @var array<int, string>
     */
    private const EXTRA_METADATA_FIELDS = [
        'x-oss-storage-class',
        'etag',
        'x-oss-version-id',
    ];

    // Aliyun OSS Client OssClient
    protected OssClient $client;

    // bucket name
    protected string $bucket;

    protected string $hostname;

    protected bool $ssl;

    protected bool $isCname;

    protected string $epInternal;

    // 配置
    protected $options = [
        'Multipart' => 128,
    ];

    private MimeTypeDetector $mimeTypeDetector;

    private PathPrefixer $prefixer;

    private VisibilityConverter $visibility;

    private string $domain;

    /**
     * AliOssAdapter constructor.
     */
    public function __construct(
        OssClient $client,
        string $bucket,
        string $hostname,
        bool $ssl,
        bool $isCname,
        string $epInternal,
        string $prefix = '',
        ?VisibilityConverter $visibility = null,
        ?MimeTypeDetector $mimeTypeDetector = null,
        array $options = []
    ) {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->hostname = $hostname;
        $this->ssl = $ssl;
        $this->isCname = $isCname;
        $this->epInternal = $epInternal;
        $this->prefixer = new PathPrefixer($prefix);
        $this->visibility = $visibility ?: new PortableVisibilityConverter();
        $this->mimeTypeDetector = $mimeTypeDetector ?: new FinfoMimeTypeDetector();
        $this->options = array_merge($this->options, $options);
        $this->domain = $this->isCname ? $this->hostname : $this->bucket . '.' . $this->hostname;
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $this->prefixer->prefixPath($path), $this->options);
        } catch (Throwable $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $options = [
                OssClient::OSS_DELIMITER => '/',
                OssClient::OSS_PREFIX => $this->prefixer->prefixDirectoryPath($path),
                OssClient::OSS_MAX_KEYS => 1,
                OssClient::OSS_MARKER => '',
            ];

            $listObjectInfo = $this->client->listObjects($this->bucket, $options + $this->options);

            return !empty($listObjectInfo->getObjectList());
        } catch (Throwable $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->upload($path, $contents, $config);
    }

    public function read(string $path): string
    {
        try {
            return $this->client->getObject($this->bucket, $this->prefixer->prefixPath($path), $this->options);
        } catch (OssException $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getErrorMessage(), $exception);
        } catch (Throwable $e) {
            UnableToReadFile::fromLocation($path, '', $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $result = $this->client->getObject($this->bucket, $this->prefixer->prefixPath($path), $this->options);
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $result);
            rewind($stream);
            unset($result);

            return $stream;
        } catch (OssException $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getErrorMessage(), $exception);
        } catch (Throwable $e) {
            UnableToReadFile::fromLocation($path, '', $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->client->deleteObject($this->bucket, $this->prefixer->prefixPath($path), $this->options);
        } catch (OssException $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getErrorMessage(), $exception);
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, '', $exception);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $dirname = ltrim(rtrim($this->prefixer->prefixPath($path), '/') . '/', '/');

        $objects = $this->retrievePaginatedListing([
            OssClient::OSS_MAX_KEYS => 1000,
            OSSClient::OSS_DELIMITER => '/',
            OssClient::OSS_MARKER => '',
            OssClient::OSS_PREFIX => $dirname,
        ], true);

        $dels = [];
        foreach ($objects as $object) {
            array_push($dels, $object['key'] ?? $object['prefix']);
        }
        array_push($dels, $dirname);

        try {
            $this->client->deleteObjects($this->bucket, $dels);
        } catch (OssException $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getErrorMessage(), $exception);
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, '', $exception);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->client->createObjectDir($this->bucket, $this->prefixer->prefixPath($path), $this->options + $this->getOptionsFromConfig($config));
    }

    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $this->client->putObjectAcl($this->bucket, $this->prefixer->prefixPath($path), $this->visibility->visibilityToAcl($visibility), $this->options);
        } catch (OssException $exception) {
            throw UnableToSetVisibility::atLocation($path, $exception->getErrorMessage(), $exception);
        } catch (Throwable $exception) {
            throw UnableToSetVisibility::atLocation($path, '', $exception);
        }
    }

    public function visibility(string $path): FileAttributes
    {
        try {
            $acl = $this->client->getObjectAcl($this->bucket, $this->prefixer->prefixPath($path), $this->options);
        } catch (OssException $exception) {
            throw UnableToRetrieveMetadata::visibility($path, $exception->getErrorMessage(), $exception);
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::visibility($path, '', $exception);
        }

        $visibility = $this->visibility->aclToVisibility($acl);

        return new FileAttributes($path, null, $visibility);
    }

    public function mimeType(string $path): FileAttributes
    {
        $attributes = $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_MIME_TYPE);

        if ($attributes->mimeType() === null) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        return $attributes;
    }

    public function lastModified(string $path): FileAttributes
    {
        $attributes = $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_LAST_MODIFIED);

        if ($attributes->lastModified() === null) {
            throw UnableToRetrieveMetadata::lastModified($path);
        }

        return $attributes;
    }

    public function fileSize(string $path): FileAttributes
    {
        $attributes = $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_FILE_SIZE);

        if ($attributes->fileSize() === null) {
            throw UnableToRetrieveMetadata::fileSize($path);
        }

        return $attributes;
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = trim($this->prefixer->prefixPath($path), '/');
        $prefix = empty($prefix) ? '' : $prefix . '/';
        $options = [
            OssClient::OSS_MAX_KEYS => 1000,
            OssClient::OSS_MARKER => '',
            OssClient::OSS_PREFIX => $prefix,
        ];

        if ($deep === false) {
            $options[OssClient::OSS_DELIMITER] = '/';
        }
        $listing = $this->retrievePaginatedListing($options);

        foreach ($listing as $item) {
            yield $this->mapOssObjectMetadata((array) $item);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            /** @var string $visibility */
            $visibility = $this->visibility($source)->visibility();
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }

        $options = $this->getOptions([static::META_MAP['ACL'] => $visibility] + $this->options, $config);

        try {
            $this->client->copyObject(
                $this->bucket,
                $this->prefixer->prefixPath($source),
                $this->bucket,
                $this->prefixer->prefixPath($destination),
                $options
            );
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function symlink(string $symlink, string $path, Config $config): void
    {
        try {
            $this->client->putSymlink($this->bucket, $this->prefixer->prefixPath($symlink), $this->prefixer->prefixPath($path), $this->getOptions($this->options, $config));
        } catch (OssException $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getErrorMessage(), $exception);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, 'Unknown', $exception);
        }
    }

    public function appendFile(string $path, string $file, int $position = 0, Config $config): void
    {
        try {
            $this->client->appendFile($this->bucket, $this->prefixer->prefixPath($path), $file, $position, $this->getOptions($this->options, $config));
        } catch (OssException $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getErrorMessage(), $exception);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, 'Unknown', $exception);
        }
    }

    public function appendObject(string $path, string $content, int $position = 0, Config $config): void
    {
        try {
            $this->client->appendObject($this->bucket, $this->prefixer->prefixPath($path), $content, $position, $this->getOptions($this->options, $config));
        } catch (OssException $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getErrorMessage(), $exception);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, 'Unknown', $exception);
        }
    }

    public function getUrl(string $path): string
    {
        return ($this->ssl ? 'https://' : 'http://') . $this->domain . '/' . ltrim($path, '/');
    }

    /**
     * 获取临时地址.
     * @copyright (c) zishang520 All Rights Reserved
     */
    public function getTemporaryUrl(string $path, DateTimeInterface $expiration, array $options = []): string
    {
        $url = $this->client->signUrl($this->bucket, $this->prefixer->prefixPath($path), Carbon::now()->diffInSeconds(Carbon::parse($expiration)), $options[OssClient::OSS_METHOD] ?? OssClient::OSS_HTTP_GET, $options + $this->options);
        if ($this->epInternal == $this->hostname) {
            return $url;
        }
        return preg_replace(sprintf('/%s/', preg_quote($this->bucket . '.' . $this->epInternal)), $this->domain, $url, 1);
    }

    /**
     * Get options for a OSS call. done.
     *
     * @return array OSS options
     */
    protected function getOptions(array $options = [], Config $config = null): array
    {
        $options = array_merge($this->options, $options);

        if ($config) {
            $options = array_merge($options, $this->getOptionsFromConfig($config));
        }

        return [OssClient::OSS_HEADERS => $options];
    }

    /**
     * Retrieve options from a Config instance. done.
     */
    protected function getOptionsFromConfig(Config $config): array
    {
        $options = [];

        foreach (static::META_OPTIONS as $option) {
            $value = $config->get($option, '__NOT_SET__');

            if ($value !== '__NOT_SET__') {
                $options[static::META_MAP[$option]] = $value;
            }
        }

        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            // For local reference
            // $options['visibility'] = $visibility;
            // For external reference
            $options[static::META_MAP['ACL']] = $this->visibility->visibilityToAcl($visibility);
        }

        if ($mimetype = $config->get('mimetype')) {
            // For local reference
            // $options['mimetype'] = $mimetype;
            // For external reference
            $options[static::META_MAP['ContentType']] = $mimetype;
        }

        return $options;
    }

    private function retrievePaginatedListing(array $options, bool $recursive = false): Generator
    {
        while (true) {
            $listObjectInfo = $this->client->listObjects($this->bucket, $options + $this->options);
            $options[OssClient::OSS_MARKER] = $listObjectInfo->getNextMarker();

            foreach ($listObjectInfo->getPrefixList() as $prefix) {
                yield [
                    'prefix' => $prefix->getPrefix(),
                ];
                if ($recursive) {
                    yield from $this->retrievePaginatedListing([OssClient::OSS_MARKER => '', OssClient::OSS_PREFIX => $prefix->getPrefix()] + $options + $this->options, $recursive);
                }
            }
            foreach ($listObjectInfo->getObjectList() as $object) {
                yield [
                    'key' => $object->getKey(),
                    'last-modified' => $object->getLastModified(),
                    'etag' => $object->getETag(),
                    'type' => $object->getType(),
                    'content-length' => $object->getSize(),
                    'x-oss-storage-class' => $object->getStorageClass(),
                ];
            }

            // 没有更多结果了
            if ($listObjectInfo->getIsTruncated() === 'false') {
                break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    private function fetchFileMetadata(string $path, string $type): FileAttributes
    {
        try {
            $objectMeta = $this->client->getObjectMeta($this->bucket, $this->prefixer->prefixPath($path));
        } catch (OssException $exception) {
            throw UnableToRetrieveMetadata::create($path, $type, $exception->getErrorMessage(), $exception);
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::create($path, $type, '', $exception);
        }
        $attributes = $this->mapOssObjectMetadata($objectMeta, $path);

        if (!$attributes instanceof FileAttributes) {
            throw UnableToRetrieveMetadata::create($path, $type, '');
        }

        return $attributes;
    }

    private function mapOssObjectMetadata(array $metadata, string $path = null): StorageAttributes
    {
        if ($path === null) {
            $path = $this->prefixer->stripPrefix($metadata['key'] ?? $metadata['prefix']);
        }

        if (substr($path, -1) === '/') {
            return new DirectoryAttributes(rtrim($path, '/'));
        }

        $mimetype = $metadata['content-type'] ?? null;
        $fileSize = $metadata['content-length'] ?? $metadata['info']['download_content_length'] ?? null;
        $fileSize = $fileSize === null ? null : (int) $fileSize;
        $dateTime = $metadata['last-modified'] ?? null;
        $lastModified = !is_null($dateTime) ? Carbon::parse($dateTime)->getTimeStamp() : null;

        return new FileAttributes(
            $path,
            $fileSize,
            null,
            $lastModified,
            $mimetype,
            $this->extractExtraMetadata($metadata)
        );
    }

    private function extractExtraMetadata(array $metadata): array
    {
        $extracted = [];

        foreach (static::EXTRA_METADATA_FIELDS as $field) {
            if (isset($metadata[$field]) && $metadata[$field] !== '') {
                $extracted[$field] = $metadata[$field];
            }
        }

        return $extracted;
    }

    /**
     * @param string|resource $body
     */
    private function upload(string $path, $body, Config $config): void
    {
        $key = $this->prefixer->prefixPath($path);
        $options = $this->getOptions($this->options, $config);

        $shouldDetermineMimetype = $body !== '' && !array_key_exists(OssClient::OSS_CONTENT_TYPE, $options);

        if ($shouldDetermineMimetype && $mimeType = $this->mimeTypeDetector->detectMimeType($key, $body)) {
            $options[OssClient::OSS_CONTENT_TYPE] = $mimeType;
        }

        try {
            if (is_resource($body)) {
                $this->client->uploadStream($this->bucket, $key, $body, $options);
            } else {
                $this->client->putObject($this->bucket, $key, $body, $options);
            }
        } catch (OssException $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getErrorMessage(), $exception);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, 'Unknown', $exception);
        }
    }
}
