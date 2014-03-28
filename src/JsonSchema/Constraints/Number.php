<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Constraints;

/**
 * The Number Constraints, validates an number against a given schema
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Bruno Prieto Reis <bruno.p.reis@gmail.com>
 */
class Number extends Constraint
{
    /**
     * {@inheritDoc}
     */
    public function check($element, $schema = null, $path = null, $i = null)
    {
        // Verify minimum
        if (isset($schema->exclusiveMinimum)) {
            if (isset($schema->minimum)) {
                if ($schema->exclusiveMinimum && $element === $schema->minimum) {
                    $this->addError($path, "must have a minimum value greater than boundary value of " . $schema->minimum);
                } else if ($element < $schema->minimum) {
                    $this->addError($path, "must have a minimum value of " . $schema->minimum);
                }
            } else {
                $this->addError($path, "use of exclusiveMinimum requires presence of minimum");
            }
        } else if (isset($schema->minimum) && $element < $schema->minimum) {
            $this->addError($path, "must have a minimum value of " . $schema->minimum);
        }

        // Verify maximum
        if (isset($schema->exclusiveMaximum)) {
            if (isset($schema->maximum)) {
                if ($schema->exclusiveMaximum && $element === $schema->maximum) {
                    $this->addError($path, "must have a maximum value less than boundary value of " . $schema->maximum);
                } else if ($element > $schema->maximum) {
                    $this->addError($path, "must have a maximum value of " . $schema->maximum);
                }
            } else {
                $this->addError($path, "use of exclusiveMaximum requires presence of maximum");
            }
        } else if (isset($schema->maximum) && $element > $schema->maximum) {
            $this->addError($path, "must have a maximum value of " . $schema->maximum);
        }

        // Verify divisibleBy - Draft v3
        if (isset($schema->divisibleBy) && $this->fmod($element, $schema->divisibleBy) != 0) {
            $this->addError($path, "is not divisible by " . $schema->divisibleBy);
        }

        // Verify multipleOf - Draft v4
        if (isset($schema->multipleOf) && $this->fmod($element, $schema->multipleOf) != 0) {
            $this->addError($path, "must be a multiple of " . $schema->multipleOf);
        }

        $this->checkFormat($element, $schema, $path, $i);
    }

    protected function fmod($number1, $number2)
    {
        $modulus = fmod($number1, $number2);
        $precision = abs(0.0000000001);
        $diff = (float)($modulus - $number2);

        if (-$precision < $diff && $diff < $precision) {
            return 0.0;
        }

        $decimals1 = mb_strpos($number1, ".") ? mb_strlen($number1) - mb_strpos($number1, ".") - 1 : 0;
        $decimals2 = mb_strpos($number2, ".") ? mb_strlen($number2) - mb_strpos($number2, ".") - 1 : 0;

        return (float)round($modulus, max($decimals1, $decimals2));
    }

    public static function compile($schemaId, $schema, $checkMode = null, $uriRetriever = null, array $classes = array())
    {
        $classes[$schemaId]['Number'] = uniqid('Number');

        $null = true;

        $code = '
class '.$classes[$schemaId]['Number'].' extends Number
{
    use Trait'.$classes[$schemaId]['Constraint'].';
    public function check($element, $schema = null, $path = null, $i = null)
    {
        ';
        if (isset($schema->exclusiveMinimum)) {
            if (isset($schema->minimum)) {
                if ($schema->exclusiveMinimum) {
                    $code .= '
                    if ($element === '.var_export($schema->minimum, true).') {
                        $this->addError($path, "must have a minimum value greater than boundary value of " . '.var_export($schema->minimum, true).');
                    }';
                }
                $code .= '
                if ($element < '.var_export($schema->minimum, true).') {
                    $this->addError($path, "must have a minimum value of " . '.var_export($schema->minimum, true).');
                }';
            } else {
                $code .= '
                   $this->addError($path, "use of exclusiveMinimum requires presence of minimum");
                ';
            }
            $null = false;
        } else if (isset($schema->minimum)) {
            $code .= '
            if ($element < '.var_export($schema->minimum, true).') {
                $this->addError($path, "must have a minimum value of " . '.var_export($schema->minimum, true).');
            }';
            $null = false;
        }

        if (isset($schema->exclusiveMaximum)) {
            if (isset($schema->maximum)) {
                if ($schema->exclusiveMaximum) {
                    $code .= 'if ($element === '.var_export($schema->maximum, true).') {
                        $this->addError($path, "must have a maximum value less than boundary value of " . '.var_export($schema->maximum, true).');
                    }';
                }
                $code .= 'if ($element > '.var_export($schema->maximum, true).') {
                    $this->addError($path, "must have a maximum value of " . '.var_export($schema->maximum, true).');
                }';
            } else {
                $code .= '
                    $this->addError($path, "use of exclusiveMaximum requires presence of maximum");
                ';
            }
            $null = false;
        } else if (isset($schema->maximum)) {
            $code .= '
            if ($element > '.var_export($schema->maximum, true).') {
                $this->addError($path, "must have a maximum value of " . '.var_export($schema->maximum, true).');
            }';
            $null = false;
        }

        if (isset($schema->divisibleBy)) {
            $code .= '
            if ($this->fmod($element, '.var_export($schema->divisibleBy, true).') != 0) {
                $this->addError($path, "is not divisible by " . '.var_export($schema->divisibleBy, true).');
            }';
            $null = false;
        }

        if (isset($schema->multipleOf)) {
            $code .= '
            if ($this->fmod($element, '.var_export($schema->multipleOf, true).') != 0) {
                $this->addError($path, "must be a multiple of " . '.var_export($schema->multipleOf, true).');
            }';
            $null = false;
        }

        if (isset($schema->format)) {
            $code .= '
            $this->checkFormat($element, null, $path, $i);';
            $null = false;
        }
        $code .= '
    }
}';

        return $null ? null : array('code' => $code, 'classes' => $classes);
    }
}
