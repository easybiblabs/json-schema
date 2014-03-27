<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Constraints;

/**
 * The Collection Constraints, validates an array against a given schema
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Bruno Prieto Reis <bruno.p.reis@gmail.com>
 */
class Collection extends Constraint
{
    /**
     * {@inheritDoc}
     */
    public function check($value, $schema = null, $path = null, $i = null)
    {
        $this->checkNoSchema(
            $value,
            $path,
            $i,
            isset($schema->minItems) ? $schema->minItems : null,
            isset($schema->maxItems) ? $schema->maxItems : null,
            isset($schema->uniqueItems)
        );

        // Verify items
        if (isset($schema->items)) {
            $this->validateItems($value, $schema, $path, $i);
        }
    }

    public function checkNoSchema($value, $path = null, $i = null, $minItems = null, $maxItems = null, $uniqueItems = false)
    {
        // Verify minItems
        if (null !== $minItems && count($value) < $minItems) {
            $this->addError($path, "There must be a minimum of " . $minItems . " in the array");
        }

        // Verify maxItems
        if (null !== $maxItems && count($value) > $maxItems) {
            $this->addError($path, "There must be a maximum of " . $maxItems . " in the array");
        }

        // Verify uniqueItems
        if ($uniqueItems) {
            $unique = $value;
            if (is_array($value) && count($value)) {
                $unique = array_map(function($e) { return var_export($e, true); }, $value);
            }
            if (count(array_unique($unique)) != count($value)) {
                $this->addError($path, "There are no duplicates allowed in the array");
            }
        }
    }

    /**
     * Validates the items
     *
     * @param array     $value
     * @param \stdClass $schema
     * @param string    $path
     * @param string    $i
     */
    protected function validateItems($value, $schema = null, $path = null, $i = null)
    {
        if (is_object($schema->items)) {
            // just one type definition for the whole array
            foreach ($value as $k => $v) {
                $initErrors = $this->getErrors();

                // First check if its defined in "items"
                $this->checkUndefined($v, $schema->items, $path, $k);

                // Recheck with "additionalItems" if the first test fails
                if (count($initErrors) < count($this->getErrors()) && (isset($schema->additionalItems) && $schema->additionalItems !== false)) {
                    $secondErrors = $this->getErrors();
                    $this->checkUndefined($v, $schema->additionalItems, $path, $k);
                }

                // Reset errors if needed
                if (isset($secondErrors) && count($secondErrors) < count($this->getErrors())) {
                    $this->errors = $secondErrors;
                } else if (isset($secondErrors) && count($secondErrors) === count($this->getErrors())) {
                    $this->errors = $initErrors;
                }
            }
        } else {
            // Defined item type definitions
            foreach ($value as $k => $v) {
                if (array_key_exists($k, $schema->items)) {
                    $this->checkUndefined($v, $schema->items[$k], $path, $k);
                } else {
                    // Additional items
                    if (property_exists($schema, 'additionalItems')) {
                        if ($schema->additionalItems !== false) {
                            $this->checkUndefined($v, $schema->additionalItems, $path, $k);
                        } else {
                            $this->addError(
                                $path, 'The item ' . $i . '[' . $k . '] is not defined and the definition does not allow additional items');
                        }
                    } else {
                        // Should be valid against an empty schema
                        $this->checkUndefined($v, new \stdClass(), $path, $k);
                    }
                }
            }

            // Treat when we have more schema definitions than values, not for empty arrays
            if(count($value) > 0) {
                for ($k = count($value); $k < count($schema->items); $k++) {
                    $this->checkUndefined(new Undefined(), $schema->items[$k], $path, $k);
                }
            }
        }
    }

    public static function compile($schemaId, $schema, $checkMode = null, $uriRetriever = null, array $classes = array())
    {
        $classes[$schemaId]['Collection'] = uniqid('Collection');

        $minItems = isset($schema->minItems) ? $schema->minItems : null;
        $maxItems = isset($schema->maxItems) ? $schema->maxItems : null;
        $uniqueItems = isset($schema->uniqueItems);

        if ($minItems === null && $maxItems === null && $uniqueItems === false && !isset($schema->items)) {
            return null;
        }

        $prependCode = '';
        $code = '
class '.$classes[$schemaId]['Collection'].' extends Collection
{
    use Trait'.$classes[$schemaId]['Constraint'].';
    public function check($value, $schema = null, $path = null, $i = null)
    {
        $this->checkNoSchema(
            $value,
            $path,
            $i,
            '.var_export($minItems, true).',
            '.var_export($maxItems, true).',
            '.var_export($uniqueItems, true).'
        );
        ';
        if (isset($schema->items)) {
            $code .= '
                $this->validateItems($value, null, $path, $i);
            ';
        }
        $code .= '
    }

    protected function validateItems($value, $schema = null, $path = null, $i = null)
    {
        ';
        if (isset($schema->items)) {
            if (is_object($schema->items)) {
                $code .= '
            // just one type definition for the whole array
            foreach ($value as $k => $v) {
                $initErrors = $this->getErrors();

                // First check if its defined in "items"
                ';
                $id = md5(serialize($schema->items));
                $compiled = Constraint::compile($id, $schema->items, $checkMode, $uriRetriever, $classes);
                $prependCode .= $compiled['code'];
                $classes = $compiled['classes'];
                $code .= '
                    $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $v, null, $path, $k);
                ';

                if (isset($schema->additionalItems) && $schema->additionalItems !== false)
                {
                    $code .= '
                    // Recheck with "additionalItems" if the first test fails
                    if (count($initErrors) < count($this->getErrors())) {
                        $secondErrors = $this->getErrors();
                        ';
                        $id = md5(serialize($schema->additionalItems));
                        $compiled = Constraint::compile($id, $schema->additionalItems, $checkMode, $uriRetriever, $classes);
                        $prependCode .= $compiled['code'];
                        $classes = $compiled['classes'];
                        $code .= '
                            $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $v, null, $path, $k);
                        ';

                        $code .= '
                    }';
                }

                $code .= '

                // Reset errors if needed
                if (isset($secondErrors) && count($secondErrors) < count($this->getErrors())) {
                    $this->errors = $secondErrors;
                } else if (isset($secondErrors) && count($secondErrors) === count($this->getErrors())) {
                    $this->errors = $initErrors;
                }
            }
            ';
        } else {

            $itemsCode = array();
            foreach ($schema->items as $k => $subSchema) {
                $id = md5(serialize($subSchema));
                $compiled = Constraint::compile($id, $subSchema, $checkMode, $uriRetriever, $classes);
                $prependCode .= $compiled['code'];
                $classes = $compiled['classes'];
                $itemsCode[$k] = '
                    $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $v, null, $path, $k);
                ';
            }

            $code .= '
            // Defined item type definitions
            foreach ($value as $k => $v) {
                switch ($k) {
                    ';
                    foreach ($itemsCode as $k => $itemCode) {
                        $code .= 'case '.var_export($k, true).':
                            '.$itemCode.'
                            break;
                        ';
                    }
                    $code .= '
                    default:
                    // Additional items
                    ';
                    if (property_exists($schema, 'additionalItems')) {
                        if ($schema->additionalItems !== false) {
                            $id = md5(serialize($schema->additionalItems));
                            $compiled = Constraint::compile($id, $schema->additionalItems, $checkMode, $uriRetriever, $classes);
                            $prependCode .= $compiled['code'];
                            $classes = $compiled['classes'];
                            $code .= '
                                $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $v, null, $path, $k);
                            ';
                        } else {
                            $code .= '
                            $this->addError(
                                $path, "The item " . $i . "[" . $k . "] is not defined and the definition does not allow additional items");
                            ';
                        }
                    } else {
                        $id = md5(serialize(new \stdClass()));
                        $compiled = Constraint::compile($id, new \stdClass(), $checkMode, $uriRetriever, $classes);
                        $prependCode .= $compiled['code'];
                        $classes = $compiled['classes'];
                        $code .= '
                            $this->checkValidator(new '.$classes[$id]['Undefined'].'(), $v, null, $path, $k);
                        ';
                    }
                    $code .= '
                    break;
                }
            }

            // Treat when we have more schema definitions than values, not for empty arrays
            if(count($value) > 0) {
                for ($k = count($value); $k < '.var_export(count($schema->items), true).'; $k++) {
                    switch ($k) {
                        ';
                    foreach ($itemsCode as $k => $itemCode) {
                        $code .= 'case '.var_export($k, true).':
                            $v = new Undefined();
                            '.$itemCode.'
                            break;
                        ';
                    }
                    $code .= '
                    }
                }
            }
            ';
            }
        }
        $code .= '
    }
}';

        return array('code' => $prependCode.$code, 'classes' => $classes);
    }
}
