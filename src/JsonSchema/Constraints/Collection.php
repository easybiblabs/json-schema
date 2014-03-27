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
            isset($schema->uniqueItems),
            isset($schema->items)
        );

        $items = isset($schema->items);
        // Verify items
        if ($items) {
            $this->validateItems($value, $schema, $path, $i);
        }
    }

    public function checkNoSchema($value, $path = null, $i = null, $minItems = null, $maxItems = null, $uniqueItems = false, $items = false)
    {
        // Verify minItems
        if (null != $minItems && count($value) < $minItems) {
            $this->addError($path, "There must be a minimum of " . $minItems . " in the array");
        }

        // Verify maxItems
        if (isset($maxItems) && count($value) > $maxItems) {
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

        $code = '
trait Trait'.$classes[$schemaId]['Collection'].'
{
    public function check($value, $schema = null, $path = null, $i = null)
    {
        $this->checkNoSchema(
            $value,
            $path,
            $i,
            '.var_export(isset($schema->minItems) ? $schema->minItems : null, true).',
            '.var_export(isset($schema->maxItems) ? $schema->maxItems : null, true).',
            '.var_export(isset($schema->uniqueItems), true).',
            '.var_export(isset($schema->items), true).'
        );

        $items = isset($schema->items);
        // Verify items
        if ($items) {
            $this->validateItems($value, $schema, $path, $i);
        }
    }
}

class '.$classes[$schemaId]['Collection'].' extends Collection
{
    use Trait'.$classes[$schemaId]['Constraint'].';
    use Trait'.$classes[$schemaId]['Collection'].';
}
        ';

        return array('code' => $code, 'classes' => $classes);
    }
}
