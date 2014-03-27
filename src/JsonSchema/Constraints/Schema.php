<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Constraints;

use JsonSchema\Exception\InvalidArgumentException;

/**
 * The Schema Constraints, validates an element against a given schema
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Bruno Prieto Reis <bruno.p.reis@gmail.com>
 */
class Schema extends Constraint
{
    /**
     * {@inheritDoc}
     */
    public function check($element, $schema = null, $path = null, $i = null)
    {
        if ($schema !== null) {
            // passed schema
            $this->checkUndefined($element, $schema, '', '');
        } elseif (property_exists($element, $this->inlineSchemaProperty)) {
            // inline schema
            $this->checkUndefined($element, $element->{$this->inlineSchemaProperty}, '', '');
        } else {
            throw new InvalidArgumentException('no schema found to verify against');
        }
    }

    public static function compile($schemaId, $schema, $checkMode = null, $uriRetriever = null, array $classes = array())
    {
        $compiled = parent::compile($schemaId, $schema, $checkMode, $uriRetriever, $classes);
        $classes = $compiled['classes'];
        $classes[$schemaId]['Schema'] = uniqid('Schema');

        $code = $compiled['code'];
        $code .= '
trait Trait'.$classes[$schemaId]['Schema'].'
{
    public function check($value, $schema = null, $path = null, $i = null)
    {
        $this->checkValidator(new '.$classes[$schemaId]['Undefined'].'(), $value, null, $path, $i);
    }
}

class '.$classes[$schemaId]['Schema'].' extends Schema
{
    use Trait'.$classes[$schemaId]['Constraint'].';
    use Trait'.$classes[$schemaId]['Schema'].';
}
        ';

        return array('code' => $code, 'classes' => $classes);
    }
}
