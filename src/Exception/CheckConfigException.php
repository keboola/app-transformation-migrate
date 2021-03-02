<?php

declare(strict_types=1);

namespace Keboola\TransformationMigrate\Exception;

use Keboola\CommonExceptions\UserExceptionInterface;
use Keboola\Component\UserException;

class CheckConfigException extends UserException implements UserExceptionInterface
{

}
