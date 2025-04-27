<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\test\Container;

use DateTime;
use const PHP_VERSION_ID;

if (PHP_VERSION_ID < 80100) {
    return;
}

/**
 * Class InitClass
 */
class InitClass
{
    public function __construct(public DateTime $dateTime = new DateTime())
    {

    }
}
