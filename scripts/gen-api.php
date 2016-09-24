<?php

/**
 * This scripts generates the restructuredText for the class API.
 *
 * Change the CPHALCON_DIR constant to point to the ext/ directory in the Phalcon source code
 *
 * php scripts/gen-api.php
 */

if (!extension_loaded('phalcon')) {
    throw new Exception("Phalcon extension is required");
}

defined('CPHALCON_DIR') || define('CPHALCON_DIR', getenv('CPHALCON_DIR'));

if (!CPHALCON_DIR) {
    throw new Exception("Need to set CPHALCON_DIR. Fox example: 'export CPHALCON_DIR=/Users/gutierrezandresfelipe/cphalcon/ext/'");
}

if (!file_exists(CPHALCON_DIR)) {
    throw new Exception("CPHALCON directory does not exist");
}

$languages = array('en', 'es', 'fr', 'id', 'ja', 'pl', 'pt', 'ru', 'uk', 'zh');

/**
 * Class ApiGenerator
 */
class ApiGenerator
{

    protected $docs = array();

    protected $classDocs = array();

    /**
     * @param $directory
     */
    public function __construct($directory)
    {
        $this->scanSources($directory);
    }

    /**
     * @param $directory
     */
    protected function scanSources($directory)
    {
        $recursiveDirectoryIterator = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);

        /** @var $iterator RecursiveDirectoryIterator[] */
        $iterator = new RecursiveIteratorIterator($recursiveDirectoryIterator);

        foreach ($iterator as $item) {

            if ($item->getExtension() == 'c') {
                if (strpos($item->getPathname(), 'kernel') === false) {
                    $this->parseDocs($item->getPathname());
                }
            }
        }
    }

    /**
     * Parse docs from file
     *
     * @param $file
     */
    protected function parseDocs($file)
    {
        $firstDoc       = true;
        $openComment    = false;
        $nextLineMethod = false;
        $comment        = '';
        foreach (file($file) as $line) {
            if (trim($line) == '/**') {
                $openComment = true;
                $comment .= $line;
            }
            if ($openComment === true) {
                $comment .= $line;
            } else {
                if ($nextLineMethod === true) {
                    if (preg_match('/^PHP_METHOD\(([a-zA-Z0-9\_]+), (.*)\)/', $line, $matches)) {
                        $this->docs[$matches[1]][$matches[2]] = $comment;
                        $className                            = $matches[1];
                    } else {
                        if (preg_match('/^PHALCON_DOC_METHOD\(([a-zA-Z0-9\_]+), (.*)\)/', $line, $matches)) {
                            $this->docs[$matches[1]][$matches[2]] = $comment;
                            $className                            = $matches[1];
                        } else {
                            if ($firstDoc === true) {
                                $classDoc = $comment;
                                $firstDoc = false;
                                $comment  = '';
                            }
                        }
                    }
                    $nextLineMethod = false;
                } else {
                    $comment = '';
                }
            }
            if ($openComment === true) {
                if (trim($line) == '*/') {
                    $comment .= $line;
                    $openComment    = false;
                    $nextLineMethod = true;
                }
            }
            if (preg_match('/^PHALCON_INIT_CLASS\(([a-zA-Z0-9\_]+)\)/', $line, $matches)) {
                $className = $matches[1];
            }
        }

        if (isset($classDoc)) {

            if (!isset($className)) {

                $fileName = str_replace(CPHALCON_DIR, '', $file);
                $fileName = str_replace('.c', '', $fileName);

                $parts = array();
                foreach (explode(DIRECTORY_SEPARATOR, $fileName) as $part) {
                    $parts[] = ucfirst($part);
                }

                $className = 'Phalcon\\' . join('\\', $parts);
            } else {
                $className = str_replace('_', '\\', $className);
            }

            //echo $className, PHP_EOL;

            if (!isset($this->classDocs[$className])) {
                if (class_exists($className) or interface_exists($className)) {
                    $this->classDocs[$className] = $classDoc;
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getDocs()
    {
        return $this->docs;
    }

    /**
     * @return array
     */
    public function getClassDocs()
    {
        return $this->classDocs;
    }

    /**
     * @param      $phpdoc
     * @param      $className
     * @param null $realClassName
     *
     * @return array
     */
    public function getPhpDoc($phpdoc, $className, $realClassName = null)
    {

        $ret         = array();
        $lines       = array();
        $description = '';

        $phpdoc = trim($phpdoc);
        $phpdoc = str_replace("\r", "", $phpdoc);

        foreach (explode("\n", $phpdoc) as $line) {
            $line  = preg_replace('#^/\*\*#', '', $line);
            $line  = str_replace('*/', '', $line);
            $line  = preg_replace('#^[ \t]+\*#', '', $line);
            $line  = str_replace('*\/', '*/', $line);
            $tline = trim($line);
            if ($className != $tline) {
                $lines[] = $line;
            }
        }

        $rc = str_replace("\\\\", "\\", $realClassName);

        $numberBlock = -1;
        $insideCode  = false;
        $codeBlocks  = array();
        foreach ($lines as $line) {
            if (strpos($line, '<code') !== false) {
                $numberBlock++;
                $insideCode = true;
            }
            if (strpos($line, '</code') !== false) {
                $insideCode = false;
            }
            if ($insideCode == false) {
                $line = str_replace('</code>', '', $line);
                if (trim($line) != $rc) {
                    if (preg_match('/@([a-z0-9]+)/', $line, $matches)) {
                        $content = trim(str_replace($matches[0], '', $line));
                        if ($matches[1] == 'param') {
                            $parts = preg_split('/[ \t]+/', $content);
                            if (count($parts) == 2) {
                                $name = "$" . str_replace("$", "", $parts[1]);
                                $ret['parameters'][$name] = trim($parts[0]);
                            } else {
                                //throw new Exception("Failed proccessing parameters in ".$className.'::'.$methodName);
                            }
                        } else {
                            $ret[$matches[1]] = $content;
                        }
                    } else {
                        $description .= ltrim($line) . "\n";
                    }
                }
            } else {
                if (!isset($codeBlocks[$numberBlock])) {
                    $line                     = str_replace('<code>', '', $line);
                    $codeBlocks[$numberBlock] = $line . "\n";
                    $description .= '%%' . $numberBlock . '%%';
                } else {
                    $codeBlocks[$numberBlock] .= $line . "\n";
                }
            }
        }

        foreach ($codeBlocks as $n => $cc) {
            $c         = '';
            $firstLine = true;
            $p         = explode("\n", $cc);
            foreach ($p as $pp) {
                if ($firstLine) {
                    if (substr(ltrim($pp), 0, 1) != '[') {
                        if (!preg_match('#^<?php#', ltrim($pp))) {
                            if (count($p) == 1) {
                                $c .= '    <?php ';
                            } else {
                                $c .= '    <?php' . PHP_EOL . PHP_EOL;
                            }
                        }
                    }
                    $firstLine = false;
                }
                $pp = preg_replace('#^\t#', '', $pp);
                if (count($p) != 1) {
                    $c .= '    ' . $pp . PHP_EOL;
                } else {
                    $c .= $pp . PHP_EOL;
                }
            }
            $c .= PHP_EOL;
            $codeBlocks[$n] = rtrim($c);
        }

        $description = str_replace('<p>', '', $description);
        $description = str_replace('</p>', PHP_EOL . PHP_EOL, $description);

        $c = $description;
        $c = str_replace("\\", "\\\\", $c);
        $c = trim(str_replace("\t", "", $c));
        $c = trim(str_replace("\n", " ", $c));
        foreach ($codeBlocks as $n => $cc) {
            if (preg_match('#\[[a-z]+\]#', $cc)) {
                $type = 'ini';
            } else {
                $type = 'php';
            }
            $c = str_replace(
                '%%' . $n . '%%',
                PHP_EOL . PHP_EOL . '.. code-block:: ' . $type . PHP_EOL . PHP_EOL . $cc . PHP_EOL . PHP_EOL,
                $c
            );
        }

        $final     = '';
        $blankLine = false;
        foreach (explode("\n", $c) as $line) {
            if (trim($line) == '') {
                if ($blankLine == false) {
                    $final .= $line . "\n";
                    $blankLine = true;
                }
            } else {
                $final .= $line . "\n";
                $blankLine = false;
            }
        }

        $ret['description'] = $final;
        return $ret;
    }

}



$di = new \Phalcon\DI();

$view = new \Phalcon\Mvc\View\Simple();

$volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);

$volt->setOptions(
    [
        "compiledPath"      => "scripts/compiled-views/",
        "compiledExtension" => ".compiled",
    ]
);

$compiler = $volt->getCompiler();

$compiler->addFunction("str_repeat", "str_repeat");

$view->registerEngines(
    [
        ".volt" => $volt,
    ]
);

$view->setDI($di);

// A trailing directory separator is required
$view->setViewsDir("scripts/views/");



$api = new ApiGenerator(CPHALCON_DIR);

$classDocs = $api->getClassDocs();
$docs      = $api->getDocs();

$classes = array();
foreach (get_declared_classes() as $className) {
    if (!preg_match('#^Phalcon#', $className)) {
        continue;
    }
    $classes[] = $className;
}

foreach (get_declared_interfaces() as $className) {
    if (!preg_match('#^Phalcon#', $className)) {
        continue;
    }
    $classes[] = $className;
}

//Exception class docs
$docs['Exception'] = array(
    '__construct'      => '/**
 * Exception constructor
 *
 * @param string $message
 * @param int $code
 * @param Exception $previous
*/',
    'getMessage'       => '/**
 * Gets the Exception message
 *
 * @return string
*/',
    'getCode'          => '/**
 * Gets the Exception code
 *
 * @return int
*/',
    'getLine'          => '/**
 * Gets the line in which the exception occurred
 *
 * @return int
*/',
    'getFile'          => '/**
 * Gets the file in which the exception occurred
 *
 * @return string
*/',
    'getTrace'         => '/**
 * Gets the stack trace
 *
 * @return array
*/',
    'getTraceAsString' => '/**
 * Gets the stack trace as a string
 *
 * @return Exception
*/',
    '__clone'          => '/**
 * Clone the exception
 *
 * @return Exception
*/',
    'getPrevious'      => '/**
 * Returns previous Exception
 *
 * @return Exception
*/',
    '__toString'       => '/**
 * String representation of the exception
 *
 * @return string
*/',
);

sort($classes);

$indexClasses    = array();
$indexInterfaces = array();
foreach ($classes as $className) {

    $realClassName = $className;

    $simpleClassName = str_replace("\\", "_", $className);

    $reflector = new ReflectionClass($className);

    $documentationData = array();

    $typeClass = 'public';
    if ($reflector->isAbstract() == true) {
        $typeClass = 'abstract';
    }

    if ($reflector->isFinal() == true) {
        $typeClass = 'final';
    }

    if ($reflector->isInterface() == true) {
        $typeClass = '';
    }

    $documentationData = array(
        'type'        => $typeClass,
        'description' => $realClassName,
        'extends'     => $reflector->getParentClass(),
        'implements'  => $reflector->getInterfaceNames(),
        'constants'   => $reflector->getConstants(),
        'methods'     => $reflector->getMethods()
    );

    if ($reflector->isInterface() == true) {
        $indexInterfaces[] = '   ' . $simpleClassName . PHP_EOL;
    } else {
        $indexClasses[] = '   ' . $simpleClassName . PHP_EOL;
    }

    $nsClassName = str_replace("\\", "\\\\", $className);

    if ($reflector->isInterface() == true) {
        $title = 'Interface **' . $nsClassName . '**';
    } else {

        $classPrefix = 'Class';
        if (strtolower($typeClass) != 'public') {
            $classPrefix = ucfirst(strtolower($typeClass)) . ' class';
        }

        $title = $classPrefix . ' **' . $nsClassName . '**';
    }

    $extendsString = "";

    if ($documentationData['extends']) {
        $extendsName = $documentationData['extends']->name;
        if (strpos($extendsName, 'Phalcon') !== false) {
            if (class_exists($extendsName)) {
                $extendsClass = $extendsName;
                $extendsPath  = str_replace("\\", "_", $extendsName);
                $extendsName  = str_replace("\\", "\\\\", $extendsName);
                $reflector    = new ReflectionClass($extendsClass);

                $prefix = 'class';
                if ($reflector->isAbstract() == true) {
                    $prefix = 'abstract class';
                }

                $extendsString
                    .= PHP_EOL . '*extends* ' . $prefix . ' :doc:`' . $extendsName . ' <' . $extendsPath . '>`' . PHP_EOL;
            } else {
                $extendsString .= PHP_EOL . '*extends* ' . $extendsName . PHP_EOL;
            }
        } else {
            $extendsString .= PHP_EOL . '*extends* ' . $extendsName . PHP_EOL;
        }
    }

    $implementsString = "";

    //Generate the interfaces part
    if (count($documentationData['implements'])) {
        $implements = array();
        foreach ($documentationData['implements'] as $interfaceName) {
            if (strpos($interfaceName, 'Phalcon') !== false) {
                if (interface_exists($interfaceName)) {
                    $interfacePath = str_replace("\\", "_", $interfaceName);
                    $interfaceName = str_replace("\\", "\\\\", $interfaceName);
                    $implements[]  = ':doc:`' . $interfaceName . ' <' . $interfacePath . '>`';
                } else {
                    $implements[] = str_replace("\\", "\\\\", $interfaceName);
                }
            } else {
                $implements[] = $interfaceName;
            }
        }
        $implementsString .= PHP_EOL . '*implements* ' . join(', ', $implements) . PHP_EOL;
    }

    $githubLink = 'https://github.com/phalcon/cphalcon/blob/master/' . str_replace("\\", "/", strtolower($className)) . '.zep';

    $classDescription = "";

    if (isset($classDocs[$realClassName])) {
        $ret = $api->getPhpDoc($classDocs[$realClassName], $className, $realClassName);
        $classDescription .= $ret['description'] . PHP_EOL . PHP_EOL;
    }

    $constantsString = "";

    if (count($documentationData['constants'])) {
        $constantsString .= 'Constants' . PHP_EOL;
        $constantsString .= '---------' . PHP_EOL . PHP_EOL;
        foreach ($documentationData['constants'] as $name => $constant) {
            $constantsString .= '*' . gettype($constant) . '* **' . $name . '**' . PHP_EOL . PHP_EOL;
        }
    }

    $methodsString = "";

    if (count($documentationData['methods'])) {

        $methodsString .= 'Methods' . PHP_EOL;
        $methodsString .= '-------' . PHP_EOL . PHP_EOL;
        foreach ($documentationData['methods'] as $method) {

            /** @var $method ReflectionMethod */

            $docClassName = str_replace("\\", "_", $method->getDeclaringClass()->name);
            if (isset($docs[$docClassName])) {
                $docMethods = $docs[$docClassName];
            } else {
                $docMethods = array();
            }

            if (isset($docMethods[$method->name])) {
                $ret = $api->getPhpDoc($docMethods[$method->name], $className);
            } else {
                $ret = array();
            }

            $methodsString .= implode(' ', Reflection::getModifierNames($method->getModifiers())) . ' ';

            if (isset($ret['return'])) {
                if (preg_match('/^(\\\\?Phalcon[a-zA-Z0-9\\\\]+)/', $ret['return'], $matches)) {
                    if (class_exists($matches[0]) || interface_exists($matches[0])) {
                        $extendsPath = preg_replace('/^\\\\/', '', $matches[1]);
                        $extendsPath = str_replace("\\", "_", $extendsPath);
                        $extendsName = preg_replace('/^\\\\/', '', $matches[1]);
                        $extendsName = str_replace("\\", "\\\\", $extendsName);
                        $methodsString .= str_replace(
                            $matches[1],
                            ':doc:`' . $extendsName . ' <' . $extendsPath . '>` ',
                            $ret['return']
                        );
                    } else {
                        $extendsName = str_replace("\\", "\\\\", $ret['return']);
                        $methodsString .= '*' . $extendsName . '* ';
                    }

                } else {
                    $methodsString .= '*' . $ret['return'] . '* ';
                }
            }

            $methodsString .= ' **' . $method->name . '** (';

            $cp = array();
            foreach ($method->getParameters() as $parameter) {
                $name = '$' . $parameter->name;
                if (isset($ret['parameters'][$name])) {
                    $parameterType = $ret['parameters'][$name];
                } else if (!is_null($parameter->getClass())) {
                    $parameterType = $parameter->getClass()->name;
                } else if ($parameter->isArray()) {
                    $parameterType = 'array';
                } else {
                    $parameterType = 'mixed';
                }
                if (strpos($parameterType, 'Phalcon') !== false) {
                    if (class_exists($parameterType) || interface_exists($parameterType)) {
                        $parameterPath = preg_replace('/^\\\\/', '', $parameterType);
                        $parameterPath = str_replace("\\", "_", $parameterPath);
                        $parameterName = preg_replace('/^\\\\/', '', $parameterType);
                        $parameterName = str_replace("\\", "\\\\", $parameterName);
                        if (!$parameter->isOptional()) {
                            $cp[] = ':doc:`' . $parameterName . ' <' . $parameterPath . '>` ' . $name;
                        } else {
                            $cp[] = '[:doc:`' . $parameterName . ' <' . $parameterPath . '>` ' . $name . ']';
                        }
                    } else {
                        $parameterName = str_replace("\\", "\\\\", $parameterType);
                        if (!$parameter->isOptional()) {
                            $cp[] = '*' . $parameterName . '* ' . $name;
                        } else {
                            $cp[] = '[*' . $parameterName . '* ' . $name . ']';
                        }
                    }
                } else {
                    if (!$parameter->isOptional()) {
                        $cp[] = '*' . $parameterType . '* ' . $name;
                    } else {
                        $cp[] = '[*' . $parameterType . '* ' . $name . ']';
                    }
                }
            }
            $methodsString .= join(', ', $cp) . ')';

            if ($simpleClassName != $docClassName) {
                $methodsString .= ' inherited from ' . str_replace("\\", "\\\\", $method->getDeclaringClass()->name);
            }

            $methodsString .= PHP_EOL . PHP_EOL;

            if (isset($ret['description'])) {
                foreach (explode("\n", $ret['description']) as $dline) {
                    $methodsString .= "" . $dline . "\n";
                }
            } else {
                $methodsString .= "...\n";
            }
            $methodsString .= PHP_EOL . PHP_EOL;

        }

    }

    foreach ($languages as $lang) {
        @mkdir($lang . '/api/');

        file_put_contents(
            $lang . '/api/' . $simpleClassName . '.rst',
            $view->render(
                "class",
                [
                    "title"            => $title,
                    "extendsString"    => $extendsString,
                    "implementsString" => $implementsString,
                    "githubLink"       => $githubLink,
                    "classDescription" => $classDescription,
                    "constantsString"  => $constantsString,
                    "methodsString"    => $methodsString,
                ]
            )
        );
    }
}

foreach ($languages as $lang) {
    file_put_contents(
        $lang . '/api/index.rst',
        $view->render(
            "index",
            [
                "classes"    => $indexClasses,
                "interfaces" => $indexInterfaces,
            ]
        )
    );
}
