<?php

namespace JsonSchema;

class ConstraintCompiler
{
    protected $constraints;
    protected $classes;
    protected $code;

    public function schemaId($schema)
    {
        return md5(serialize($schema));
    }

    public function declareClass($baseClass, $schema)
    {
        $schemaId = $this->schemaId($schema);

        if (isset($this->classes[$schemaId][$baseClass])) {
            return false;
        }

        return $this->classes[$schemaId][$baseClass] = uniqid($baseClass);
    }

    public function add($baseClass, $schema, $code, $className = null)
    {
        $schemaId = $this->schemaId($schema);

/*        if (!$className && isset($this->constraints[md5($code)])) {
            $className = $this->constraints[md5($code)];
} else {*/
            if (!$className) {
                $className = uniqid($baseClass);
            }

            $class = '';
            if ($baseClass == 'Validator') {
                $fullBaseClass = '\JsonSchema\\' . $baseClass;
                $class = 'namespace JsonSchema;';
                if (strpos($className, '\\') !== false) {
                    $parts = explode('\\', $className);
                    $className = array_pop($parts);
                    $namespace = implode('\\', $parts);
                    $class = "namespace $namespace;"
                           . "use \JsonSchema\Constraints;";
                }
            } else {
                $class = 'namespace JsonSchema\Constraints;';
                $fullBaseClass = '\JsonSchema\Constraints\\' . $baseClass;
            }

            if ($baseClass == 'Constraint') {
                $class .= 'trait Trait'.$className.' {';
            } else {
                $class .= 'class '.$className.' extends '.$fullBaseClass.' {';
                $class .= 'use \JsonSchema\Constraints\Trait'.$this->classes[$schemaId]['Constraint'].';';
            }
            $class .= $code;
            $class .= '}';

            $this->constraints[md5($code)] = $className;
            $this->code[$className] = $class;
//        }

        $this->classes[$schemaId][$baseClass] = $className;
    }

    public function getClass($baseClass, $schema)
    {
        $schemaId = $this->schemaId($schema);

        return isset($this->classes[$schemaId][$baseClass]) ? $this->classes[$schemaId][$baseClass] : null;
    }

    public function getCode()
    {
        return "<?php /* This is compiled code, don't edit by hand */\n".implode("\n", $this->code);
    }
}
