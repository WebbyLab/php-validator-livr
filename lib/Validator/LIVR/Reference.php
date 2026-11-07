<?php

namespace Validator\LIVR;

/**
 * References to other fields of "required_if" and "when" (LIVR 2.5, 2.5): "name" is a field of the
 * containing object, "a/b/0" is a path down from it, "~1" stands for "/" and "~0" for "~" as in
 * JSON Pointer (RFC 6901)
 */
class Reference
{
    /**
     * The segments of a reference: "a/b/0" -> ['a', 'b', '0']. Not a string, an empty reference,
     * a leading or a trailing "/", an empty segment ("a//b") and a wrong escape ("~2", a trailing
     * "~") are schema errors. A leading "/" is reserved for references from the root
     */
    public static function parse($reference, $ruleName)
    {
        if (!is_string($reference) || $reference === '') {
            throw new \Exception("LIVR: a reference of \"$ruleName\" must be a non-empty string");
        }

        // The common case: a field name without escapes
        if (strpbrk($reference, '/~') === false) {
            return array($reference);
        }

        $path = explode('/', $reference);

        foreach ($path as $i => $segment) {
            if ($segment === '') {
                throw new \Exception("LIVR: the reference \"$reference\" of \"$ruleName\" has an empty segment");
            }

            if (strpos($segment, '~') !== false) {
                if (preg_match('/~(?![01])/', $segment)) {
                    throw new \Exception("LIVR: the reference \"$reference\" of \"$ruleName\" has a wrong escape");
                }

                $path[$i] = str_replace(array('~1', '~0'), array('/', '~'), $segment);
            }
        }

        return $path;
    }

    /**
     * The input value at the path from the containing object, null when a segment is missing or a
     * value on the way is not an object (or a list for an index segment). On a list a segment is
     * an index only in the form "0" or [1-9][0-9]*
     */
    public static function resolve($data, $path)
    {
        $value = $data;

        foreach ($path as $segment) {
            if (!is_array($value)) {
                return null;
            }

            // PHP has no empty object or list to tell apart: an empty array has no segments
            if ($value && !Util::isAssocArray($value)) {
                if (!preg_match('/^(?:0|[1-9][0-9]*)\z/', $segment)) {
                    return null;
                }

                $segment = (int) $segment;
            }

            if (!array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
