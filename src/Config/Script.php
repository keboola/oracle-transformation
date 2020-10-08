<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\Config;

class Script
{
    private string $sql;

    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    public function getSql(): string
    {
        return $this->sql;
    }
}
