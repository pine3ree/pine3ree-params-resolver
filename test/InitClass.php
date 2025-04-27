<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\test\Container;

use DateTime;

/**
 * Class InitClass
 */
class InitClass
{
    public function __construct(public DateTime $dateTime = new DateTime())
    {
    }
}
