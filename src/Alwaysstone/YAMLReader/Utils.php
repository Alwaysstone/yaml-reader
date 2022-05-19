<?php
namespace Alwaysstone\YAMLReader;

/**
 * @internal
 */
class Utils {
   
    
    private static function _dumpValue($value) {
        switch (true) {
            case is_resource($value):
                return 'null';
            case is_object($value):
                return 'null';
            case is_array($value):
                return self::_dumpArray($value);
            case null === $value:
                return 'null';
            case true === $value:
                return 'true';
            case false === $value:
                return 'false';
            case is_numeric($value):
                return $value;
            case '' == $value:
                return "''";
            default:
                return $value;
        }
    }
    
    private static function _dumpArray($value) {
        // array
        if ($value && !self::_isHash($value)) {
            $output = array();
            foreach ($value as $val) {
                $output[] = self::_dumpValue($val);
            }
            return sprintf('[%s]', implode(', ', $output));
        }

        // hash
        $output = array();
        foreach ($value as $key => $val) {
            $output[] = sprintf('%s: %s', self::_dumpValue($key), self::_dumpValue($val));
        }
        return sprintf('{ %s }', implode(', ', $output));
    }

    private static function _isHash(array $value) {
        $expectedKey = 0;
        foreach ($value as $key => $val) {
            if ($key !== $expectedKey++) {
                return true;
            }
        }
        return false;
    }
    
    public static function buildOutYAML($array, int $indent = 0): string {
        $output = '';
        $prefix = $indent ? str_repeat(' ', $indent) : '';
        if (!is_array($array) || empty($array)) {
            $output .= $prefix . self::_dumpValue($array);
        } else {
            $isAHash = self::_isHash($array);
            foreach ($array as $k => $v) {
                $k = Utils::ripristinaKey($k);
                $willBeInlined = !is_array($v) || empty($v);
                $output .= sprintf('%s%s%s%s', $prefix, $isAHash ? self::_dumpValue($k) . ':' : '-', $willBeInlined ? ' ' : "\n", self::buildOutYAML($v, $willBeInlined ? 0 : $indent + 2)) .  ($willBeInlined ? "\n" : '');
            }
        }
        return $output;
    }
    
    public static function bonificaKey($key) {
        if (substr($key, -1) == "/" ) {
            $key = substr($key, 0, -1);
        }
        return str_replace(" ", "__SP__", $key);

    }
    
    public static function ripristinaKey($key) {
       return str_replace("__SP__", " ", $key);
    }
}