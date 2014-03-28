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

    public static function compile($validatorClassName, $schema, $checkMode = null, $uriRetriever = null)
    {
        if (null === $schema) {
            throw new \InvalidArgumentException("Cannot compile null schema: inline schema is not supported in compiled validators.");
        }

        $compiler = new ConstraintCompiler;

        Schema::compile($compiler, $schema, $checkMode, $uriRetriever);

        $code = '
    public function check($value, $schema = null, $path = null, $i = null)
    {
        $validator = new Constraints\\'.$compiler->getClass('Schema', $schema).'();
        $validator->check($value, null, $path, $i);
        $this->addErrors($validator->getErrors());
    }';

        $compiler->add('Validator', $schema, $code, $validatorClassName);

        return $compiler;
    }
}
