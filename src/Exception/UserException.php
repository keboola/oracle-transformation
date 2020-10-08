<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation\Exception;

use \Exception;
use Keboola\CommonExceptions\UserExceptionInterface;

class UserException extends \Keboola\Component\UserException implements UserExceptionInterface
{

}
