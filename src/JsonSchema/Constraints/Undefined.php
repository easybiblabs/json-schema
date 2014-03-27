<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Constraints;

use JsonSchema\Exception\InvalidArgumentException;
use JsonSchema\Uri\UriResolver;

/**
 * The Undefined Constraints
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Bruno Prieto Reis <bruno.p.reis@gmail.com>
 */
class Undefined extends Constraint
{
    /**
     * {@inheritDoc}
     */
    public function check($value, $schema = null, $path = null, $i = null)
    {
        if (is_null($schema)) {
            return;
        }

        if (!is_object($schema)) {
            throw new InvalidArgumentException(
                'Given schema must be an object in ' . $path
                . ' but is a ' . gettype($schema)
            );
        }

        return $this->checkAnySchema($value, $schema, $path, $i);
    }

    protected function checkAnySchema($value, $schema = null, $path = null, $i = null)
    {
        $i = is_null($i) ? "" : $i;
        $path = $this->incrementPath($path, $i);

        // check special properties
        $this->validateCommonProperties($value, $schema, $path);

        // check allOf, anyOf, and oneOf properties
        $this->validateOfProperties($value, $schema, $path);

        // check known types
        $this->validateTypes($value, $schema, $path, $i);
    }

    /**
     * Validates the value against the types
     *
     * @param mixed  $value
     * @param mixed  $schema
     * @param string $path
     * @param string $i
     */
    public function validateTypes($value, $schema = null, $path = null, $i = null)
    {
        // check array
        if (is_array($value)) {
            $this->checkArray($value, $schema, $path, $i);
        }

        // check object
        if (is_object($value) && (isset($schema->properties) || isset($schema->patternProperties))) {
            $this->checkObject(
                $value,
                isset($schema->properties) ? $schema->properties : null,
                $path,
                isset($schema->additionalProperties) ? $schema->additionalProperties : null,
                isset($schema->patternProperties) ? $schema->patternProperties : null
            );
        }

        // check string
        if (is_string($value)) {
            $this->checkString($value, $schema, $path, $i);
        }

        // check numeric
        if (is_numeric($value)) {
            $this->checkNumber($value, $schema, $path, $i);
        }

        // check enum
        if (isset($schema->enum)) {
            $this->checkEnum($value, $schema, $path, $i);
        }
    }

    /**
     * Validates common properties
     *
     * @param mixed  $value
     * @param mixed  $schema
     * @param string $path
     * @param string $i
     */
    protected function validateCommonProperties($value, $schema = null, $path = null, $i = "")
    {
        // if it extends another schema, it must pass that schema as well
        if (isset($schema->extends)) {
            if (is_string($schema->extends)) {
                $schema->extends = $this->validateUri($schema, $schema->extends);
            }
            if (is_array($schema->extends)) {
                foreach ($schema->extends as $extends) {
                    $this->checkUndefined($value, $extends, $path, $i);
                }
            } else {
                $this->checkUndefined($value, $schema->extends, $path, $i);
            }
        }

        // Verify required values
        if (is_object($value)) {
            if (isset($schema->required) && is_array($schema->required) ) {
                // Draft 4 - Required is an array of strings - e.g. "required": ["foo", ...]
                foreach ($schema->required as $required) {
                    if (!property_exists($value, $required)) {
                        $this->addError($path, "the property " . $required . " is required");
                    }
                }
            } else if (isset($schema->required)) {
                // Draft 3 - Required attribute - e.g. "foo": {"type": "string", "required": true}
                if ( $schema->required && $value instanceof Undefined) {
                    $this->addError($path, "is missing and it is required");
                }
            }
        }

        // Verify type
        if (!($value instanceof Undefined)) {
            $this->checkType($value, $schema, $path);
        }

        // Verify disallowed items
        if (isset($schema->disallow)) {
            $initErrors = $this->getErrors();

            $typeSchema = new \stdClass();
            $typeSchema->type = $schema->disallow;
            $this->checkType($value, $typeSchema, $path);

            // if no new errors were raised it must be a disallowed value
            if (count($this->getErrors()) == count($initErrors)) {
                $this->addError($path, "disallowed value was matched");
            } else {
                $this->errors = $initErrors;
            }
        }

        if (isset($schema->not)) {
            $initErrors = $this->getErrors();
            $this->checkUndefined($value, $schema->not, $path, $i);

            // if no new errors were raised then the instance validated against the "not" schema
            if (count($this->getErrors()) == count($initErrors)) {
                $this->addError($path, "matched a schema which it should not");
            } else {
                $this->errors = $initErrors;
            }
        }

        // Verify minimum and maximum number of properties
        if (is_object($value)) {
            if (isset($schema->minProperties)) {
                if (count(get_object_vars($value)) < $schema->minProperties) {
                    $this->addError($path, "must contain a minimum of " . $schema->minProperties . " properties");
                }
            }
            if (isset($schema->maxProperties)) {
                if (count(get_object_vars($value)) > $schema->maxProperties) {
                    $this->addError($path, "must contain no more than " . $schema->maxProperties . " properties");
                }
            }
        }

        // Verify that dependencies are met
        if (is_object($value) && isset($schema->dependencies)) {
            $this->validateDependencies($value, $schema->dependencies, $path);
        }
    }

    /**
     * Validate allOf, anyOf, and oneOf properties
     *
     * @param mixed  $value
     * @param mixed  $schema
     * @param string $path
     * @param string $i
     */
    protected function validateOfProperties($value, $schema, $path, $i = "")
    {
        // Verify type
        if ($value instanceof Undefined) {
            return;
        }

        if (isset($schema->allOf)) {
            $isValid = true;
            foreach ($schema->allOf as $allOf) {
                $initErrors = $this->getErrors();
                $this->checkUndefined($value, $allOf, $path, $i);
                $isValid = $isValid && (count($this->getErrors()) == count($initErrors));
            }
            if (!$isValid) {
                $this->addError($path, "failed to match all schemas");
            }
        }

        if (isset($schema->anyOf)) {
            $isValid = false;
            $startErrors = $this->getErrors();
            foreach ($schema->anyOf as $anyOf) {
                $initErrors = $this->getErrors();
                $this->checkUndefined($value, $anyOf, $path, $i);
                if ($isValid = (count($this->getErrors()) == count($initErrors))) {
                    break;
                }
            }
            if (!$isValid) {
                $this->addError($path, "failed to match at least one schema");
            } else {
                $this->errors = $startErrors;
            }
        }

        if (isset($schema->oneOf)) {
            $allErrors = array();
            $matchedSchemas = 0;
            $startErrors = $this->getErrors();
            foreach ($schema->oneOf as $oneOf) {
                $this->errors = array();
                $this->checkUndefined($value, $oneOf, $path, $i);
                if (count($this->getErrors()) == 0) {
                    $matchedSchemas++;
                }
                $allErrors = array_merge($allErrors, array_values($this->getErrors()));
            }
            if ($matchedSchemas !== 1) {
                $this->addErrors(
                    array_merge(
                        $allErrors,
                        array(array(
                            'property' => $path,
                            'message' => "failed to match exactly one schema"
                        ),),
                        $startErrors
                    )
                );
            } else {
                $this->errors = $startErrors;
            }
        }
    }

    /**
     * Validate dependencies
     *
     * @param mixed  $value
     * @param mixed  $dependencies
     * @param string $path
     * @param string $i
     */
    protected function validateDependencies($value, $dependencies, $path, $i = "")
    {
        foreach ($dependencies as $key => $dependency) {
            if (property_exists($value, $key)) {
                if (is_string($dependency)) {
                    // Draft 3 string is allowed - e.g. "dependencies": {"bar": "foo"}
                    if (!property_exists($value, $dependency)) {
                        $this->addError($path, "$key depends on $dependency and $dependency is missing");
                    }
                } else if (is_array($dependency)) {
                    // Draft 4 must be an array - e.g. "dependencies": {"bar": ["foo"]}
                    foreach ($dependency as $d) {
                        if (!property_exists($value, $d)) {
                            $this->addError($path, "$key depends on $d and $d is missing");
                        }
                    }
                } else if (is_object($dependency)) {
                    // Schema - e.g. "dependencies": {"bar": {"properties": {"foo": {...}}}}
                    $this->checkUndefined($value, $dependency, $path, $i);
                }
            }
        }
    }

    protected function validateUri($schema, $schemaUri = null)
    {
        return static::staticValidateUri($this->getUriRetriever(), $schema, $schemaUri);
    }

    public static function staticValidateUri($uriRetriever, $schema, $schemaUri = null)
    {
        $resolver = new UriResolver();
        $retriever = $uriRetriever;

        $jsonSchema = null;
        if ($resolver->isValid($schemaUri)) {
            $schemaId = property_exists($schema, 'id') ? $schema->id : null;
            $jsonSchema = $retriever->retrieve($schemaId, $schemaUri);
        }

        return $jsonSchema;
    }

    public static function compile($schemaId, $schema, $checkMode = null, $uriRetriever = null, array $classes = array())
    {
        $classes[$schemaId]['Undefined'] = uniqid('Undefined');

        $prependCode = '';
        $code = '
class '.$classes[$schemaId]['Undefined'].' extends Undefined
{
    use Trait'.$classes[$schemaId]['Constraint'].';
    public function check($value, $schema = null, $path = null, $i = null)
    {
        ';
        if (is_null($schema)) {
            $code .= 'return;';
        }

        if (!is_object($schema)) {
            $code .= '
            throw new \JsonSchema\Exception\InvalidArgumentException(
                "Given schema must be an object in " . $path
                . " but is a '.gettype($schema).'"
            );
            ';
        }
        $code .= '
        parent::checkAnySchema($value, null, $path, $i);
    }

    public function validateTypes($value, $schema = null, $path = null, $i = null)
    {
        ';
        if (isset($schema->properties) || isset($schema->patternProperties)) {
            $code .= '
            if (is_object($value)) {
                $this->checkObject(
                    $value,
                    null,
                    $path,
                    null,
                    null
                );
            }';
        }

        $code .= '
        parent::validateTypes($value, null, $path, $i);';
        if (isset($schema->enum)) {
            $code .= '$this->checkEnum($value, null, $path, $i);';
        }
        $code .= '
    }

    protected function validateOfProperties($value, $schema, $path, $i = "")
    {
        if ($value instanceof Undefined) {
            return;
        }';

        if (isset($schema->allOf)) {
            $code .= '$isValid = true;';
            foreach ($schema->allOf as $allOf) {
                $code .= '
                    $initErrors = $this->getErrors();';

                $id = md5(serialize($allOf));
                $compiled = Constraint::compile($id, $allOf, $checkMode, $uriRetriever, $classes);
                $prependCode .= $compiled['code'];
                $classes = $compiled['classes'];
                $code .= '
                    $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $value, null, $path, $i);
                ';

                $code .= '
                $isValid = $isValid && (count($this->getErrors()) == count($initErrors));';
            }
            $code .= '
            if (!$isValid) {
                $this->addError($path, "failed to match all schemas");
            }';
        }

        if (isset($schema->anyOf)) {
            $code .= '
            $isValid = false;
            $startErrors = $this->getErrors();';
            foreach ($schema->anyOf as $anyOf) {
                $code .= '
                if (!$isValid) {
                    $initErrors = $this->getErrors();';

                    $id = md5(serialize($anyOf));
                    $compiled = Constraint::compile($id, $anyOf, $checkMode, $uriRetriever, $classes);
                    $prependCode .= $compiled['code'];
                    $classes = $compiled['classes'];
                    $code .= '
                        $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $value, null, $path, $i);
                    ';

                    $code .= '
                    $isValid = (count($this->getErrors()) == count($initErrors));
                }';
            }
            $code .= '
            if (!$isValid) {
                $this->addError($path, "failed to match at least one schema");
            } else {
                $this->errors = $startErrors;
            }';
        }

        if (isset($schema->oneOf)) {
            $code .= '
            $allErrors = array();
            $matchedSchemas = 0;
            $startErrors = $this->getErrors();';
            foreach ($schema->oneOf as $oneOf) {
                $code .= '
                    $this->errors = array();';

                $id = md5(serialize($oneOf));
                $compiled = Constraint::compile($id, $oneOf, $checkMode, $uriRetriever, $classes);
                $prependCode .= $compiled['code'];
                $classes = $compiled['classes'];
                $code .= '
                    $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $value, null, $path, $i);
                ';

                $code .= '
                if (count($this->getErrors()) == 0) {
                    $matchedSchemas++;
                }
                $allErrors = array_merge($allErrors, array_values($this->getErrors()));
                ';
            }
            $code .= '
            if ($matchedSchemas !== 1) {
                $this->addErrors(
                    array_merge(
                        $allErrors,
                        array(array(
                            "property" => $path,
                            "message" => "failed to match exactly one schema"
                        ),),
                        $startErrors
                    )
                );
            } else {
                $this->errors = $startErrors;
            }';
        }
        $code .= '
    }

    protected function validateCommonProperties($value, $schema = null, $path = null, $i = "")
    {';
        if (isset($schema->extends)) {
            if (is_string($schema->extends)) {
                $schema->extends = static::staticValidateUri($schema, $schema->extends);
            }
            if (is_array($schema->extends)) {
                foreach ($schema->extends as $extends) {
                    $id = md5(serialize($extends));
                    $compiled = Constraint::compile($id, $extends, $checkMode, $uriRetriever, $classes);
                    $prependCode .= $compiled['code'];
                    $classes = $compiled['classes'];
                    $code .= '
                        $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $value, null, $path, $i);
                    ';
                }
            } else {
                $id = md5(serialize($schema->extends));
                $compiled = Constraint::compile($id, $schema->extends, $checkMode, $uriRetriever, $classes);
                $prependCode .= $compiled['code'];
                $classes = $compiled['classes'];
                $code .= '
                    $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $value, null, $path, $i);
                ';
            }
        }

        $code .= '
        if (is_object($value)) {';
            if (isset($schema->required) && is_array($schema->required) ) {
                // Draft 4 - Required is an array of strings - e.g. "required": ["foo", ...]
                foreach ($schema->required as $required) {
                    $code .= '
                    if (!property_exists($value, '.var_export($required, true).')) {
                        $this->addError($path, "the property " . '.var_export($required, true).' . " is required");
                    }';
                }
            } else if (isset($schema->required)) {
                // Draft 3 - Required attribute - e.g. "foo": {"type": "string", "required": true}
                if ($schema->required) {
                    $code .= '
                    if ($value instanceof Undefined) {
                        $this->addError($path, "is missing and it is required");
                    }';
                }
            }
            $code .= '
        }

        if (!($value instanceof Undefined)) {
            $this->checkType($value, null, $path);
        }';

        if (isset($schema->disallow)) {
            $code .= '
            $initErrors = $this->getErrors();';

            $typeSchema = new \stdClass();
            $typeSchema->type = $schema->disallow;

            $id = md5(serialize($typeSchema));
            $compiled = Constraint::compile($id, $typeSchema, $checkMode, $uriRetriever, $classes);
            $prependCode .= $compiled['code'];
            $classes = $compiled['classes'];
            $code .= '
            $this->checkValidator(new '.$classes[$id]['Type'].'(), $value, null, $path, $i);

            // if no new errors were raised it must be a disallowed value
            if (count($this->getErrors()) == count($initErrors)) {
                $this->addError($path, "disallowed value was matched");
            } else {
                $this->errors = $initErrors;
            }';
        }

        if (isset($schema->not)) {
            $code .= '
            $initErrors = $this->getErrors();';
            $id = md5(serialize($schema->not));
            $compiled = Constraint::compile($id, $schema->not, $checkMode, $uriRetriever, $classes);
            $prependCode .= $compiled['code'];
            $classes = $compiled['classes'];
            $code .= '
            $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $value, null, $path, $i);

            // if no new errors were raised then the instance validated against the "not" schema
            if (count($this->getErrors()) == count($initErrors)) {
                $this->addError($path, "matched a schema which it should not");
            } else {
                $this->errors = $initErrors;
            }';
        }

        if (isset($schema->minProperties)) {
            $code .= '
            if (is_object($value)) {
                if (count(get_object_vars($value)) < '.var_export($schema->minProperties, true).') {
                    $this->addError($path, "must contain a minimum of " . '.var_export($schema->minProperties, true).' . " properties");
                }
            }';
        }
        if (isset($schema->maxProperties)) {
            $code .= '
            if (is_object($value)) {
                if (count(get_object_vars($value)) > '.var_export($schema->maxProperties, true).') {
                    $this->addError($path, "must contain no more than " . '.var_export($schema->maxProperties, true).' . " properties");
                }
            }';
        }

        if (isset($schema->dependencies)) {
            $code .= '
            if (is_object($value)) {
                $this->validateDependencies($value, null, $path);
            }';
        }

        $code .= '
    }';

    if (isset($schema->dependencies)) {
        $code .= '
    protected function validateDependencies($value, $dependencies, $path, $i = "")
    {';
        foreach ($schema->dependencies as $key => $dependency) {
            $code .= '
            if (property_exists($value, '.var_export($key, true).')) {';
                if (is_string($dependency)) {
                    $code .= '
                    if (!property_exists($value, '.var_export($dependency, true).')) {
                        $this->addError($path, "'.var_export($key, true).' depends on '.var_export($dependency, true).' and '.var_export($dependency, true).' is missing");
                    }';
                } else if (is_array($dependency)) {
                    foreach ($dependency as $d) {
                        $code .= '
                        if (!property_exists($value, '.var_export($d, true).')) {
                            $this->addError($path, "'.var_export($key, true).' depends on '.var_export($d, true).' and '.var_export($d, true).' is missing");
                        }';
                    }
                } else if (is_object($dependency)) {
                    $id = md5(serialize($dependency));
                    $compiled = Constraint::compile($id, $dependency, $checkMode, $uriRetriever, $classes);
                    $prependCode .= $compiled['code'];
                    $classes = $compiled['classes'];
                    $code .= '
                    $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $value, null, $path, $i);';
                }
                $code .= '
            }';
        }
        $code .= '
    }';
    }
    $code .= '
}';

        return array('code' => $prependCode.$code, 'classes' => $classes);
    }
}
