<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Tests\Constraints;

use JsonSchema\Validator;

abstract class BaseTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getInvalidTests
     */
    public function testInvalidCases($input, $schema, $checkMode = Validator::CHECK_MODE_NORMAL, $errors = array())
    {
        $validator = new Validator($checkMode);

        $validator->check(json_decode($input), json_decode($schema));

        if (array() !== $errors) {
            $this->assertEquals($errors, $validator->getErrors(), print_r($validator->getErrors(),true));
        }
        $this->assertFalse($validator->isValid(), print_r($validator->getErrors(), true));
    }

    /**
     * @dataProvider getInvalidTests
     */
    public function testInvalidCasesCompiled($input, $schema, $checkMode = Validator::CHECK_MODE_NORMAL, $errors = array())
    {
        $schema = json_decode($schema);
        if (null === $schema) {
            $this->setExpectedException('InvalidArgumentException');
        }

        $compiled = Validator::compile(null, $schema, $checkMode);
        file_put_contents('tmp.php', $compiled['code']);
        include('tmp.php');
        $validator = new $compiled['validator']();

        $validator->check(json_decode($input));

        if (array() !== $errors) {
            $this->assertEquals($errors, $validator->getErrors(), print_r($validator->getErrors(),true));
        }
        $this->assertFalse($validator->isValid(), print_r($validator->getErrors(), true));
    }

    /**
     * @dataProvider getValidTests
     */
    public function testValidCases($input, $schema, $checkMode = Validator::CHECK_MODE_NORMAL)
    {
        $validator = new Validator($checkMode);

        $validator->check(json_decode($input), json_decode($schema));
        $this->assertTrue($validator->isValid(), print_r($validator->getErrors(), true));
    }
    /**
     * @dataProvider getValidTests
     */
    public function testValidCasesCompiled($input, $schema, $checkMode = Validator::CHECK_MODE_NORMAL)
    {
        $schema = json_decode($schema);
        if (null === $schema) {
            $this->setExpectedException('InvalidArgumentException');
        }

        $compiled = Validator::compile(null, $schema, $checkMode);
        file_put_contents('tmp.php', $compiled['code']);
        include('tmp.php');
        $validator = new $compiled['validator']();

        $validator->check(json_decode($input));
        $this->assertTrue($validator->isValid(), print_r($validator->getErrors(), true));
    }

    abstract public function getValidTests();

    abstract public function getInvalidTests();
}
