<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Constraints;

/**
 * The Object Constraints, validates an object against a given schema
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Bruno Prieto Reis <bruno.p.reis@gmail.com>
 */
class Object extends Constraint
{
    /**
     * {@inheritDoc}
     */
    function check($element, $definition = null, $path = null, $additionalProp = null, $patternProperties = null)
    {
        if ($element instanceof Undefined) {
            return;
        }

        $matches = array();
        if ($patternProperties) {
            $matches = $this->validatePatternProperties($element, $path, $patternProperties);
        }

        if ($definition) {
            // validate the definition properties
            $this->validateDefinition($element, $definition, $path);
        }

        // additional the element properties
        $this->validateElement($element, $matches, $definition, $path, $additionalProp);
    }

    public function validatePatternProperties($element, $path, $patternProperties)
    {
        $matches = array();
        foreach ($patternProperties as $pregex => $schema) {
            // Validate the pattern before using it to test for matches
            if (@preg_match('/'. $pregex . '/', '') === false) {
                $this->addError($path, 'The pattern "' . $pregex . '" is invalid');
                continue;
            }
            foreach ($element as $i => $value) {
                if (preg_match('/' . $pregex . '/', $i)) {
                    $matches[] = $i;
                    $this->checkUndefined($value, $schema ? : new \stdClass(), $path, $i);
                }
            }
        }
        return $matches;
    }

    /**
     * Validates the element properties
     *
     * @param \stdClass $element          Element to validate
     * @param array     $matches          Matches from patternProperties (if any)
     * @param \stdClass $objectDefinition Object definition
     * @param string    $path             Path to test?
     * @param mixed     $additionalProp   Additional properties
     */
    public function validateElement($element, $matches, $objectDefinition = null, $path = null, $additionalProp = null)
    {
        foreach ($element as $i => $value) {

            $property = $this->getProperty($element, $i, new Undefined());
            $definition = $this->getProperty($objectDefinition, $i);

            // no additional properties allowed
            if (!in_array($i, $matches) && $additionalProp === false && $this->inlineSchemaProperty !== $i && !$definition) {
                $this->addError($path, "The property " . $i . " is not defined and the definition does not allow additional properties");
            }

            // additional properties defined
            if (!in_array($i, $matches) && $additionalProp && !$definition) {
                if ($additionalProp === true) {
                    $this->checkUndefined($value, null, $path, $i);
                } else {
                    $this->checkUndefined($value, $additionalProp, $path, $i);
                }
            }

            // property requires presence of another
            $require = $this->getProperty($definition, 'requires');
            if ($require && !$this->getProperty($element, $require)) {
                $this->addError($path, "the presence of the property " . $i . " requires that " . $require . " also be present");
            }

            if (!$definition) {
                // normal property verification
                $this->checkUndefined($value, new \stdClass(), $path, $i);
            }
        }
    }

    /**
     * Validates the definition properties
     *
     * @param \stdClass $element          Element to validate
     * @param \stdClass $objectDefinition Object definition
     * @param string    $path             Path?
     */
    public function validateDefinition($element, $objectDefinition = null, $path = null)
    {
        foreach ($objectDefinition as $i => $value) {
            $property = $this->getProperty($element, $i, new Undefined());
            $definition = $this->getProperty($objectDefinition, $i);
            $this->checkUndefined($property, $definition, $path, $i);
        }
    }

    /**
     * retrieves a property from an object or array
     *
     * @param mixed  $element  Element to validate
     * @param string $property Property to retrieve
     * @param mixed  $fallback Default value if property is not found
     *
     * @return mixed
     */
    protected function getProperty($element, $property, $fallback = null)
    {
        return static::staticGetProperty($element, $property, $fallback);
    }

    static protected function staticGetProperty($element, $property, $fallback = null)
    {
        if (is_array($element) /*$this->checkMode == self::CHECK_MODE_TYPE_CAST*/) {
            return array_key_exists($property, $element) ? $element[$property] : $fallback;
        } elseif (is_object($element)) {
            return property_exists($element, $property) ? $element->$property : $fallback;
        }

        return $fallback;
    }

    static protected function compileGetProperty($element, $property, $fallback)
    {
        return "
        (((\$tmpProp = $property)===$property) && is_array($element) ? (array_key_exists($property, $element) ? $element"."[$property] : $fallback) :
            (is_object($element) ? (property_exists($element, $property) ? $element->\$tmpProp : $fallback) : $fallback))";
    }

    public static function compile($schemaId, $schema, $checkMode = null, $uriRetriever = null, array $classes = array())
    {
        $classes[$schemaId]['Object'] = uniqid('Object');

        $objectDefinition = isset($schema->properties) ? $schema->properties : null;
        $additionalProperties = isset($schema->additionalProperties) ? $schema->additionalProperties : null;
        $patternProperties = isset($schema->patternProperties) ? $schema->patternProperties : null;

        if ($objectDefinition === null && $additionalProperties === null && $patternProperties === null) {
            return null;
        }

        $prependCode = '';
        $code = '
class '.$classes[$schemaId]['Object'].' extends Object
{
    use Trait'.$classes[$schemaId]['Constraint'].';
    function check($element, $definition = null, $path = null, $additionalProp = null, $patternProperties = null)
    {
        if ($element instanceof Undefined) {
            return;
        }

        $matches = array();';
        if ($patternProperties) {
            $code .= '
            $matches = $this->validatePatternProperties($element, $path, null);';
        }

        if ($objectDefinition) {
            $code .= '
            // validate the definition properties
            $this->validateDefinition($element, null, $path);';
        }

        $code .= '
        // additional the element properties
        $this->validateElement($element, $matches, null, $path, null);
    }';

    if ($patternProperties) {
        $code .= '
    public function validatePatternProperties($element, $path, $patternProperties)
    {
        $matches = array();';
        foreach ($patternProperties as $pregex => $schema) {
            // Validate the pattern before using it to test for matches
            if (@preg_match('/'. $pregex . '/', '') === false) {
                $code .= '
                $this->addError($path, "The pattern \"' . $pregex . '\" is invalid");';
                continue;
            }
            $code .= '
            foreach ($element as $i => $value) {
                if (preg_match("/" . '.var_export($pregex, true).' . "/", $i)) {
                    $matches[] = $i;';
                    $id = md5(serialize($schema ?: new \stdClass));
                    $compiled = Constraint::compile($id, $schema ?: new \stdClass, $checkMode, $uriRetriever, $classes);
                    $prependCode .= $compiled['code'];
                    $classes = $compiled['classes'];
                    $code .= '
                    $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $value, null, $path, $i);
                }
            }';
        }
        $code .= '
        return $matches;
    }';
    }

    if ($objectDefinition) {
        $code .= '
    public function validateDefinition($element, $objectDefinition = null, $path = null)
    {';
        foreach ($objectDefinition as $i => $value) {
            $code .= '
            $property = '.static::compileGetProperty('$element', var_export($i, true), 'new Undefined()').';';
            $definition = static::staticGetProperty($objectDefinition, $i);
            $id = md5(serialize($definition));
            $compiled = Constraint::compile($id, $definition, $checkMode, $uriRetriever, $classes);
            $prependCode .= $compiled['code'];
            $classes = $compiled['classes'];
            $code .= '
            $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $property, null, $path, '.var_export($i, true).');';
        }
        $code .= '
    }';
    }

    $code .= '
    public function validateElement($element, $matches, $objectDefinition = null, $path = null, $additionalProp = null)
    {
        foreach ($element as $i => $value) {

            $property = '.static::compileGetProperty('$element', '$i', 'new Undefined()').';
            switch ($i) {';
        $hasRequires = false;
        if ($objectDefinition) {
            $start = true;
            foreach ($objectDefinition as $key => $definition) {
                if ($definition) {
                    $requires = static::staticGetProperty($definition, 'requires');
                    if ($requires !== null) {
                        $hasRequires = true;
                        if (!$start) {
                            $code .= '
                                $hasDefinition = true;
                                $require = null;
                                break;';
                        }
                    }
                    $code .= 'case '.var_export($key, true).':';
                    if ($requires !== null) {
                        $code .= '
                            $hasDefinition = true;
                            $require = '.var_export($requires, true).';
                            break;
                        ';
                        $start = true;
                    } else {
                        $start = false;
                    }
                }
            }
            if (!$start) {
                $code .= '
                    $hasDefinition = true;
                    $require = null;
                    break;';
            }
        }
        $code .= '
            default:
                $hasDefinition = false;
                $require = null;
        }';

        if ($additionalProperties === false) {
            $code .= '
            if (!in_array($i, $matches) && $this->inlineSchemaProperty !== $i && !$hasDefinition) {
                $this->addError($path, "The property " . $i . " is not defined and the definition does not allow additional properties");
            }';
        }

        if ($additionalProperties) {
            $code .= '
            if (!in_array($i, $matches) && !$hasDefinition) {';
                if ($additionalProperties === true) {
                    //this does not actually do anything
                    //$this->checkUndefined($value, null, $path, $i);
                } else {
                    $id = md5(serialize($additionalProperties));
                    $compiled = Constraint::compile($id, $additionalProperties, $checkMode, $uriRetriever, $classes);
                    $prependCode .= $compiled['code'];
                    $classes = $compiled['classes'];
                    $code .= '
                    $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $value, null, $path, $i);';
                }
                $code .= '
            }';
        }

        if ($hasRequires) {
            $code .= '
                // property requires presence of another
                if ($require && !'.static::compileGetProperty('$element', '$require', 'null').') {
                    $this->addError($path, "the presence of the property " . $i . " requires that " . $require . " also be present");
                }';
        }
        $code .= '
            if (!$hasDefinition) {';
                $id = md5(serialize(new \stdClass));
                $compiled = Constraint::compile($id, new \stdClass, $checkMode, $uriRetriever, $classes);
                $prependCode .= $compiled['code'];
                $classes = $compiled['classes'];
                $code .= '
                $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $value, null, $path, $i);
            }
        }
    }
}
        ';

        return array('code' => $prependCode.$code, 'classes' => $classes);
    }
}
