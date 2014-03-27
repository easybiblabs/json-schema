<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Constraints;

use JsonSchema\Uri\UriRetriever;

/**
 * The Base Constraints, all Validators should extend this class
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Bruno Prieto Reis <bruno.p.reis@gmail.com>
 */
abstract class Constraint implements ConstraintInterface
{
    protected $checkMode = self::CHECK_MODE_NORMAL;
    protected $uriRetriever;
    protected $errors = array();
    protected $inlineSchemaProperty = '$schema';

    const CHECK_MODE_NORMAL = 1;
    const CHECK_MODE_TYPE_CAST = 2;

    /**
     * @param int          $checkMode
     * @param UriRetriever $uriRetriever
     */
    public function __construct($checkMode = self::CHECK_MODE_NORMAL, UriRetriever $uriRetriever = null)
    {
        $this->checkMode    = $checkMode;
        $this->uriRetriever = $uriRetriever;
    }

    /**
     * @return UriRetriever $uriRetriever
     */
    public function getUriRetriever()
    {
        if (is_null($this->uriRetriever))
        {
            $this->setUriRetriever(new UriRetriever);
        }

        return $this->uriRetriever;
    }

    /**
     * @param UriRetriever $uriRetriever
     */
    public function setUriRetriever(UriRetriever $uriRetriever)
    {
        $this->uriRetriever = $uriRetriever;
    }

    /**
     * {@inheritDoc}
     */
    public function addError($path, $message)
    {
        $this->errors[] = array(
            'property' => $path,
            'message' => $message
        );
    }

    /**
     * {@inheritDoc}
     */
    public function addErrors(array $errors)
    {
        $this->errors = array_merge($this->errors, $errors);
    }

    /**
     * {@inheritDoc}
     */
    public function getErrors()
    {
        return array_unique($this->errors, SORT_REGULAR);
    }

    /**
     * {@inheritDoc}
     */
    public function isValid()
    {
        return !$this->getErrors();
    }

    /**
     * Clears any reported errors.  Should be used between
     * multiple validation checks.
     */
    public function reset()
    {
        $this->errors = array();
    }

    /**
     * Bubble down the path
     *
     * @param string $path Current path
     * @param mixed  $i    What to append to the path
     *
     * @return string
     */
    protected function incrementPath($path, $i)
    {
        if ($path !== '') {
            if (is_int($i)) {
                $path .= '[' . $i . ']';
            } elseif ($i == '') {
                $path .= '';
            } else {
                $path .= '.' . $i;
            }
        } else {
            $path = $i;
        }

        return $path;
    }

    /**
     * Validates an array
     *
     * @param mixed $value
     * @param mixed $schema
     * @param mixed $path
     * @param mixed $i
     */
    protected function checkArray($value, $schema = null, $path = null, $i = null)
    {
        $this->checkValidator(new Collection($this->checkMode, $this->uriRetriever), $value, $schema, $path, $i);
    }

    /**
     * Validates an object
     *
     * @param mixed $value
     * @param mixed $schema
     * @param mixed $path
     * @param mixed $i
     * @param mixed $patternProperties
     */
    protected function checkObject($value, $schema = null, $path = null, $i = null, $patternProperties = null)
    {
        $this->checkValidator(new Object($this->checkMode, $this->uriRetriever), $value, $schema, $path, $i, $patternProperties);
    }

    /**
     * Validates the type of a property
     *
     * @param mixed $value
     * @param mixed $schema
     * @param mixed $path
     * @param mixed $i
     */
    protected function checkType($value, $schema = null, $path = null, $i = null)
    {
        $this->checkValidator(new Type($this->checkMode, $this->uriRetriever), $value, $schema, $path, $i);
    }

    /**
     * Checks a undefined element
     *
     * @param mixed $value
     * @param mixed $schema
     * @param mixed $path
     * @param mixed $i
     */
    public function checkUndefined($value, $schema = null, $path = null, $i = null)
    {
        $this->checkValidator(new Undefined($this->checkMode, $this->uriRetriever), $value, $schema, $path, $i);
    }

    /**
     * Checks a string element
     *
     * @param mixed $value
     * @param mixed $schema
     * @param mixed $path
     * @param mixed $i
     */
    protected function checkString($value, $schema = null, $path = null, $i = null)
    {
        $this->checkValidator(new String($this->checkMode, $this->uriRetriever), $value, $schema, $path, $i);
    }

    /**
     * Checks a number element
     *
     * @param mixed $value
     * @param mixed $schema
     * @param mixed $path
     * @param mixed $i
     */
    protected function checkNumber($value, $schema = null, $path = null, $i = null)
    {
        $this->checkValidator(new Number($this->checkMode, $this->uriRetriever), $value, $schema, $path, $i);
    }

    /**
     * Checks a enum element
     *
     * @param mixed $value
     * @param mixed $schema
     * @param mixed $path
     * @param mixed $i
     */
    protected function checkEnum($value, $schema = null, $path = null, $i = null)
    {
        $this->checkValidator(new Enum($this->checkMode, $this->uriRetriever), $value, $schema, $path, $i);
    }

    protected function checkFormat($value, $schema = null, $path = null, $i = null)
    {
        $this->checkValidator(new Format($this->checkMode, $this->uriRetriever), $value, $schema, $path, $i);
    }

    protected function checkValidator($validator, $value, $schema = null, $path = null, $i = null, $patternProperties = null)
    {
        $validator->check($value, $schema, $path, $i, $patternProperties);

        $this->addErrors($validator->getErrors());
    }

    /**
     * @param string $uri JSON Schema URI
     * @return string JSON Schema contents
     */
    protected function retrieveUri($uri)
    {
        if (null === $this->uriRetriever) {
            $this->setUriRetriever(new UriRetriever);
        }
        $jsonSchema = $this->uriRetriever->retrieve($uri);
        // TODO validate using schema
        return $jsonSchema;
    }

    public static function compile($schemaId, $schema, $checkMode = null, $uriRetriever = null, array $classes = array())
    {
        if (isset($classes[$schemaId]['Constraint'])) {
            return array('code' => '', 'classes' => $classes);
        }

        $classes[$schemaId]['Constraint'] = uniqid('Constraint');
        $code = '';

        $compiled = Undefined::compile($schemaId, $schema, $checkMode, $uriRetriever, $classes);
        $classes = $compiled['classes'];
        $code .= $compiled['code'];

        $compiled = Collection::compile($schemaId, $schema, $checkMode, $uriRetriever, $classes);
        $classes = $compiled['classes'];
        $code .= $compiled['code'];

        $compiled = Object::compile($schemaId, $schema, $checkMode, $uriRetriever, $classes);
        $classes = $compiled['classes'];
        $code .= $compiled['code'];

        $compiled = String::compile($schemaId, $schema, $checkMode, $uriRetriever, $classes);
        if ($compiled) {
            $classes = $compiled['classes'];
            $code .= $compiled['code'];
        }

        $compiled = Number::compile($schemaId, $schema, $checkMode, $uriRetriever, $classes);
        if ($compiled) {
            $classes = $compiled['classes'];
            $code .= $compiled['code'];
        }

        if (isset($schema->enum)) {
            $compiled = Enum::compile($schemaId, $schema, $checkMode, $uriRetriever, $classes);
            $classes = $compiled['classes'];
            $code .= $compiled['code'];
        }

        if (isset($schema->format)) {
            $compiled = Format::compile($schemaId, $schema, $checkMode, $uriRetriever, $classes);
            $classes = $compiled['classes'];
            $code .= $compiled['code'];
        }

        $code .= '
trait Trait'.$classes[$schemaId]['Constraint'].'
{
    protected function checkArray($value, $schema = null, $path = null, $i = null)
    {
        $collection = new '.$classes[$schemaId]['Collection'].'();
        $this->checkValidator($collection, $value, $schema, $path, $i);
    }

    protected function checkObject($value, $schema = null, $path = null, $i = null, $patternProperties = null)
    {
        $object = new '.$classes[$schemaId]['Object'].'();
        $this->checkValidator($object, $value, $schema, $path, $i, $patternProperties);
    }

    protected function checkString($value, $schema = null, $path = null, $i = null)
    {';
        if (isset($classes[$schemaId]['String'])) {
            $code .= '
            $string = new '.$classes[$schemaId]['String'].'();
            $this->checkValidator($string, $value, $schema, $path, $i);';
        }
        $code .= '
    }

    protected function checkNumber($value, $schema = null, $path = null, $i = null)
    {';
        if (isset($classes[$schemaId]['Number'])) {
            $code .= '
            $number = new '.$classes[$schemaId]['Number'].'();
            $this->checkValidator($number, $value, $schema, $path, $i);
            ';
        }
        $code .= '
    }

    protected function checkEnum($value, $schema = null, $path = null, $i = null)
    {';
        if (isset($schema->enum)) {
            $code .= '
            $enum = new '.$classes[$schemaId]['Enum'].'();
            $this->checkValidator($enum, $value, $schema, $path, $i);';
        }
        $code .= '
    }

    protected function checkFormat($value, $schema = null, $path = null, $i = null)
    {';
        if (isset($schema->format)) {
            $code .= '
            $format = new '.$classes[$schemaId]['Format'].'();
            $this->checkValidator($format, $value, $schema, $path, $i);';
        }
        $code .= '
    }
}

abstract class '.$classes[$schemaId]['Constraint'].' extends Constraint
{
    use Trait'.$classes[$schemaId]['Constraint'].';
}
        ';

        return array('code' => $code, 'classes' => $classes);
    }
}
