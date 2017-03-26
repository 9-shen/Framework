<?php


namespace wapmorgan\PhpCodeFixer;

if (!defined('T_TRAIT')) define('T_TRAIT', 'trait');

function in_array_column($haystack, $needle, $column, $strict = false) {
    if ($strict) {
        foreach ($haystack as $k => $elem) {
            if ($elem[$column] === $needle)
                return true;
        }
        return false;
    } else {
        foreach ($haystack as $k => $elem) {
            if ($elem[$column] == $needle)
                return true;
        }
        return false;
    }
}

function array_search_column($haystack, $needle, $column, $strict = false) {
    if ($strict) {
        foreach ($haystack as $k => $elem) {
            if ($elem[$column] === $needle)
                return $k;
        }
        return false;
    } else {
        foreach ($haystack as $k => $elem) {
            if ($elem[$column] == $needle)
                return $k;
        }
        return false;
    }
}

function array_filter_by_column($source, $needle, $column, $preserveIndexes = false) {
    $filtered = array();
    if ($preserveIndexes) {
        foreach ($source as $i => $elem)
            if ($elem[$column] == $needle)
                $filtered[$i] = $elem;
    } else {
        foreach ($source as $elem)
            if ($elem[$column] == $needle)
                $filtered[] = $elem;
    }
    return $filtered;
}

class PhpCodeFixer {

    static public $fileSizeLimit;

    static public function checkDir($dir, IssuesBank $issues) {


       echo 'Scanning '.$dir.' ...'.PHP_EOL;
      
        $report = new Report();
        self::checkDirInternal($dir, $issues, $report);
        return $report;
    }

    static protected function checkDirInternal($dir, IssuesBank $issues, Report $report) {
        foreach (glob($dir.'/*') as $file) {
            if (is_dir($file))
                self::checkDirInternal($file, $issues, $report);
            else if (is_file($file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), array('php', 'php5', 'phtml'))) {
                self::checkFile($file, $issues, $report);
            }
        }
    }

    static public function checkFile($file, IssuesBank $issues, Report $report = null) {
        if (self::$fileSizeLimit !== null && filesize($file) > self::$fileSizeLimit) {
            fwrite(STDOUT, 'Skipping file '.$file.' due to file size limit.'.PHP_EOL);
            return;
        }
        if (empty($report)) $report = new Report();

        $tokens = token_get_all(file_get_contents($file));

        // cut off heredoc, comments
        while (in_array_column($tokens, T_START_HEREDOC, 0)) {
            $start = array_search_column($tokens, T_START_HEREDOC, 0);
            $end = array_search_column($tokens, T_END_HEREDOC, 0);
            array_splice($tokens, $start, ($end - $start + 1));
        }

        // find for deprecated functions
        $deprecated_functions = $issues->getAll('functions');
        $used_functions = array_filter_by_column($tokens, T_STRING, 0, true);
        foreach ($used_functions as $used_function_i => $used_function) {
            if (isset($deprecated_functions[$used_function[1]])) {
                // additional check for "(" after this token
                if (!isset($tokens[$used_function_i+1]) || $tokens[$used_function_i+1] != '(')
                    continue;
                $function = $deprecated_functions[$used_function[1]];
                $report->add($function[1], 'function', $used_function[1], ($function[0] != $used_function[1] ? $function[0] : null), $file, $used_function[2]);
            }
        }

        // find for deprecated ini settings
        $deprecated_ini_settings = $issues->getAll('ini_settings');
        foreach ($tokens as $i => $token) {
            if ($token[0] == T_STRING && in_array($token[1], array('ini_alter', 'ini_set', 'ini_​get', 'ini_restore'))) {
                // syntax structure check
                if ($tokens[$i+1] == '(' && is_array($tokens[$i+2]) && $tokens[$i+2][0] == T_CONSTANT_ENCAPSED_STRING) {
                    $ini_setting = $tokens[$i+2]; // ('ini_setting'
                    $ini_setting[1] = trim($ini_setting[1], '\'"');
                    if (isset($deprecated_ini_settings[$ini_setting[1]])) {
                        $deprecated_setting = $deprecated_ini_settings[$ini_setting[1]];
                        $report->add($deprecated_setting[1], 'ini', $ini_setting[1], ($deprecated_setting[0] != $ini_setting[1] ? $deprecated_setting[0] : null), $file, $ini_setting[2]);
                    }
                }
            }
        }

        // find for deprecated functions usage
        $deprecated_functions_usage = $issues->getAll('functions_usage');
        foreach ($tokens as $i => $token) {
            if ($token[0] != T_STRING)
                continue;
            if (!isset($deprecated_functions_usage[$token[1]]))
                continue;
            // get func arguments
            $function = array($token);
            $k = $i+2;
            $braces = 1;
            while ($braces > 0 && isset($tokens[$k])) {
                $function[] = $tokens[$k];
                if ($tokens[$k] == ')') {/*var_dump($tokens[$k]);*/ $braces--;}
                else if ($tokens[$k] == '(') {/*var_dump($tokens[$k]);*/ $braces++; }
                // var_dump($braces);
                $k++;
            }
            //$function[] = $tokens[$k];
            $fixer = ltrim($deprecated_functions_usage[$token[1]][0], '@');
            require_once dirname(dirname(__FILE__)).'/data/'.$fixer.'.php';
            $fixer = __NAMESPACE__.'\\'.$fixer;
            $result = $fixer($function);
            if ($result) {
                $report->add($deprecated_functions_usage[$token[1]][1], 'function_usage', $token[1].' ('.$deprecated_functions_usage[$token[1]][0].')', null, $file, $token[2]);
            }
        }

        // find for deprecated variables
        $deprecated_varibales = $issues->getAll('variables');
        $used_variables = array_filter_by_column($tokens, T_VARIABLE, 0);
        foreach ($used_variables as $used_variable) {
            if (isset($deprecated_varibales[$used_variable[1]])) {
                $variable = $deprecated_varibales[$used_variable[1]];
                $report->add($variable[1], 'variable', $used_variable[1], ($variable[0] != $used_variable[1] ? $variable[0] : null), $file, $used_variable[2]);
            }
        }

        // find for reserved identifiers used as names
        $identifiers = $issues->getAll('identifiers');
        if (!empty($identifiers)) {
            foreach ($tokens as $i => $token) {
                if (in_array($token[0], array(T_CLASS, T_INTERFACE, T_TRAIT))) {
                    if (isset($tokens[$i+2]) && is_array($tokens[$i+2]) && $tokens[$i+2][0] == T_STRING) {
                        $used_identifier = $tokens[$i+2];
                        if (isset($identifiers[$used_identifier[1]])) {
                            $identifier = $identifiers[$used_identifier[1]];
                            $report->add($identifier[1], 'identifier', $used_identifier[1], null, $file, $used_identifier[2]);
                        }
                    }
                }
            }
        }

        // find for methods naming deprecations
        $methods_naming = $issues->getAll('methods_naming');
        if (!empty($methods_naming)) {
            while (in_array_column($tokens, T_CLASS, 0)) {
                $total = count($tokens);
                $i = array_search_column($tokens, T_CLASS, 0);
                $class_start = $i;
                $class_name = $tokens[$i+2][1];
                $braces = 1;
                $i += 5;
                while (($braces > 0) && (($i+1) <= $total)) {
                    if ($tokens[$i] == '{') {
                        $braces++;
                        /*echo '++';*/
                    } else if ($tokens[$i] == '}') {
                        $braces--;
                        /*echo '--';*/
                    } else if (is_array($tokens[$i]) && $tokens[$i][0] == T_FUNCTION && is_array($tokens[$i+2])) {
                        $function_name = $tokens[$i+2][1];
                        foreach ($methods_naming as $methods_naming_checker) {
                            $checker = ltrim($methods_naming_checker[0], '@');
                            require_once dirname(dirname(__FILE__)).'/data/'.$checker.'.php';
                            $checker = __NAMESPACE__.'\\'.$checker;
                            $result = $checker($class_name, $function_name);
                            if ($result) {
                                $report->add($methods_naming_checker[1], 'method_name', $function_name.':'.$class_name.' ('.$methods_naming_checker[0].')', null, $file, $tokens[$i][2]);
                            }

                        }
                    }
                    $i++;
                }
                array_splice($tokens, $class_start, $i - $class_start);
            }
        }
        return $report;
    }

    static public function makeFunctionCallTree(array $tokens) {
        $tree = array();
        $braces = 0;
        $i = 1;
        while (/*$braces > 0 &&*/ isset($tokens[$i])) {
            if ($tokens[$i] == '(') $braces++;
            else if ($tokens[$i] == ')') $braces--;
            else $tree[$braces][] = $tokens[$i];
            $i++;
        }
        return $tree;
    }

    static public function delimByComma(array $tokens) {
        $delimited = array();
        $comma = 0;
        foreach ($tokens as $token) {
            if ($token == ',') $comma++;
            else $delimited[$comma][] = $token;
        }
        return $delimited;
    }

    static public function trimSpaces(array $tokens) {
        $trimmed = array();
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] == T_WHITESPACE)
                    continue;
                else
                    $trimmed[] = self::trimSpaces($token);
            }
            else
                $trimmed[] = $token;
        }
        return $trimmed;
    }
}
