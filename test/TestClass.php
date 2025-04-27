<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\test\Container;

/**
 * Test class for unit testing
 */
class TestClass
{
    public function __construct(string $mandatory)
    {
    }
    public function doSomething(int $number = 42)
    {
    }
    public static function doSomethingStatic(?int $number = null)
    {
    }
}
