<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Constraints;

/**
 * The String Constraints, validates an string against a given schema
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Bruno Prieto Reis <bruno.p.reis@gmail.com>
 */
class String extends Constraint
{
    /**
     * {@inheritDoc}
     */
    public function check($element, $schema = null, $path = null, $i = null)
    {
        // Verify maxLength
        if (isset($schema->maxLength) && strlen($element) > $schema->maxLength) {
            $this->addError($path, "must be at most " . $schema->maxLength . " characters long");
        }

        //verify minLength
        if (isset($schema->minLength) && strlen($element) < $schema->minLength) {
            $this->addError($path, "must be at least " . $schema->minLength . " characters long");
        }

        // Verify a regex pattern
        if (isset($schema->pattern) && !preg_match('#' . str_replace('#', '\\#', $schema->pattern) . '#', $element)) {
            $this->addError($path, "does not match the regex pattern " . $schema->pattern);
        }

        $this->checkFormat($element, $schema, $path, $i);
    }

    public static function compile($schemaId, $schema, $checkMode = null, $uriRetriever = null, array $classes = array())
    {
        $classes[$schemaId]['String'] = uniqid('String');

        $null = true;

        $code = '
trait Trait'.$classes[$schemaId]['String'].'
{
    public function check($element, $schema = null, $path = null, $i = null)
    {
        ';
        if (isset($schema->maxLength)) {
            $max = var_export($schema->maxLength, true);
            $code .= '
            if (strlen($element) > '.$max.') {
                $this->addError($path, "must be at most '.$max.' characters long");
            }
            ';
            $null = false;
        }
        if (isset($schema->minLength)) {
            $min = var_export($schema->minLength, true);
            $code .= '
            if (strlen($element) < '.$min.') {
                $this->addError($path, "must be at least '.$min.' characters long");
            }
            ';
            $null = false;
        }
        if (isset($schema->pattern)) {
            $code .= '
            if (!preg_match("#" . str_replace("#", "\\\\#", '.var_export($schema->pattern, true).') . "#", $element)) {
                $this->addError($path, "does not match the regex pattern " . '.var_export($schema->pattern, true).');
            }
            ';
            $null = false;
        }
        $null = $null || isset($schema->format);
        $code .= '
        $this->checkFormat($element, null, $path, $i);
    }
}

class '.$classes[$schemaId]['String'].' extends String
{
    use Trait'.$classes[$schemaId]['Constraint'].';
    use Trait'.$classes[$schemaId]['String'].';
}
        ';

        return $null ? null : array('code' => $code, 'classes' => $classes);
    }
}
