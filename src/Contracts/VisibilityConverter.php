<?php

declare(strict_types=1);

namespace luoyy\AliOSS\Contracts;

interface VisibilityConverter
{
    public function visibilityToAcl(string $visibility): string;

    public function aclToVisibility(string $acl): string;

    public function defaultForDirectories(): string;
}
