<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema;

use JsonSchema\Constraints\Schema;
use JsonSchema\Constraints\Constraint;

use JsonSchema\Exception\InvalidSchemaMediaTypeException;
use JsonSchema\Exception\JsonDecodingException;

use JsonSchema\Uri\Retrievers\UriRetrieverInterface;

/**
 * A JsonSchema Constraint
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Bruno Prieto Reis <bruno.p.reis@gmail.com>
 * @see    README.md
 */
class Validator extends Constraint
{
    const SCHEMA_MEDIA_TYPE = 'application/schema+json';

    /**
     * Validates the given data against the schema and returns an object containing the results
     * Both the php object and the schema are supposed to be a result of a json_decode call.
     * The validation works as defined by the schema proposal in http://json-schema.org
     *
     * {@inheritDoc}
     */
    public function check($value, $schema = null, $path = null, $i = null)
    {
        $validator = new Schema($this->checkMode, $this->uriRetriever);
        $validator->check($value, $schema);
        $this->addErrors($validator->getErrors());
    }

    public static function compile($void, $schema, $checkMode = null, $uriRetriever = null, array $classes = array())
    {
        $schemaId = md5(serialize($schema));

        $compiled = Schema::compile($schemaId, $schema, $checkMode, $uriRetriever, $classes);
        $classes = $compiled['classes'];
        $classes[$schemaId]['Validator'] = $validatorClass = uniqid('Validator');

        $code = '<?php
namespace JsonSchema\Constraints;
'.$compiled['code'];
        $code .= '

namespace JsonSchema;
use JsonSchema\Constraints;

trait Trait'.$classes[$schemaId]['Validator'].'
{
    public function check($value, $schema = null, $path = null, $i = null)
    {
        $validator = new Constraints\\'.$classes[$schemaId]['Schema'].'();
        $validator->check($value, null, $path, $i);
        $this->addErrors($validator->getErrors());
    }
}

class '.$classes[$schemaId]['Validator'].' extends Validator
{
    use Constraints\Trait'.$classes[$schemaId]['Constraint'].';
    use Trait'.$classes[$schemaId]['Validator'].';
}
        ';

        return array('code' => $code, 'classes' => $classes, 'validator' => '\JsonSchema\\'.$validatorClass);
    }
}
