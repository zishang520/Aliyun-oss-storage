<?php
/**
 * Created by jacob.
 * User: jacob
 * Date: 16/5/20
 * Time: 下午8:31.
 */

namespace luoyy\AliOSS\Plugins;

use League\Flysystem\Config;
use League\Flysystem\Plugin\AbstractPlugin;

class Symlink extends AbstractPlugin
{
    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'symlink';
    }

    public function handle($symlink, $path, array $options = [])
    {
        $config = new Config($options);
        if (method_exists($this->filesystem, 'getConfig')) {
            $config->setFallback($this->filesystem->getConfig());
        }

        return (bool) $this->filesystem->getAdapter()->symlink($symlink, $path, $config);
    }
}
