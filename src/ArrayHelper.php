<?php


/**
 * ArrayHelper
 * Verschiedene Helper für Array Operationen
 */
class ArrayHelper
{

    /**
     * Benutze ein key aus dem Array als Index.
     *
     * @param        $array
     * @param string $key
     *
     * @return array
     */
    public static function keyAsIndex ($array, $key = 'id')
    {
        if (empty($array)) {
            return [];
        }

        $tmp = [];

        foreach ($array as $item) {
            $tmp[$item[$key]] = $item;
        }

        return $tmp;

    }

    /**
     * Ein Key in Array umbenennen
     *
     * @param $array
     * @param $oldkey
     * @param $newkey
     *
     * @return mixed
     */
    public static function renameKey ($array, $oldkey, $newkey)
    {
        foreach ($array as &$item) {
            $item[$newkey] = $item[$oldkey];
            unset($item[$oldkey]);
        }

        return $array;
    }


    /**
     * Datentyp für ein Wert/Werte im Array setzen
     *
     * @param array  $array
     * @param string|array $key
     * @param string $type , z.b. int, bool, strint, etc..
     *
     * @return array|mixed $array
     */
    public static function setValueType (&$array, $key, string $type)
    {
        return self::mapValue($array, $key, static function ($val) use ($type) {
            settype($val, $type);
            return $val;
        });
        /*
        if (!$array) {
            //keine Daten
            return $array;
        }

        if (!is_array($key)) {
            $key = [$key];
        }

        foreach ($array as &$item) {
            foreach ($key as $iKey) {
                settype($item[$iKey], $type);
                $item[$iKey] = $item[$iKey];
            }
        }

        return $array;
        */
    }

    /**
     * Einen Wert/Werte im Array transformieren
     *
     * @param array        $array
     * @param string|array $key
     * @param callable     $fn
     * @return array|mixed $array
     */
    public static function mapValue (&$array, $key, callable $fn)
    {

        if (!$array) {
            //keine Daten
            return $array;
        }

        if (!is_array($key)) {
            $key = [$key];
        }

        foreach ($array as &$item) {
            foreach ($key as $iKey) {
                $item[$iKey] = $fn($item[$iKey]);
            }
        }

        return $array;
    }

    /**
     * Wandelt Arrays mit dot Notation in eigene Arrays um
     * z.B. aus ['hello.world' => 'bla'] wird:
     * ['hello' => ['world' => 'bla']]
     * Das kann man z.B. in Formularen als Name verwenden da man keine Arrays speichern kann.
     *
     * @param array  $items
     * @param string $delimiter
     *
     * @return array
     */
    public static function normalizeArray (array $items, $delimiter = '.')
    {
        $new = [];
        foreach ($items as $key => $value) {
            if (strpos($key, $delimiter) === false) {
                $new[$key] = is_array($value) ? self::normalizeArray($value, $delimiter) : $value;
                continue;
            }

            $segments = explode($delimiter, $key);
            $last = &$new[$segments[0]];
            if (!is_null($last) && !is_array($last)) {
                //throw new \LogicException(sprintf("!!!!The '%s' key has already been defined as being '%s'", $segments[0], gettype($last)));
            }

            foreach ($segments as $k => $segment) {
                if ($k !== 0) {
                    $last = &$last[$segment];
                }
            }
            $last = is_array($value) ? self::normalizeArray($value, $delimiter) : $value;
        }
        return $new;
    }

    public static function expandKeys($arr) {
        $result = [];
        foreach($arr as $key => $value) {
            if (is_array($value)) $value = self::expandKeys($value);
            foreach(array_reverse(explode("_", $key)) as $key) $value = [$key => $value];
            $result = array_merge_recursive($result, $value);
        }
        return $result;
    }


    /**
     * Ein Array in Gruppen packen
     *
     * @param array  $array
     * @param string $groupBy
     * @param string $index - wie soll das Objekt in der Gruppe indexiert werden? wenn null dann von 0 aufsteigend
     *
     * @return array
     */
    public static function groupArray (array $array, string $groupBy, $index = null): array
    {
        if (empty($array)) {
            return $array;
        }

        $new = [];

        foreach ($array as $item) {
            if ($index === null) {
                $new[$item[$groupBy]][] = $item;
            } else {
                $index_val = $item[$index];
                $new[$item[$groupBy]][$index_val] = $item;
            }

        }

        return $new;
    }


    /**
     * Zähle alle Einträge in einem Array.
     *
     * @param $array
     *
     * @return int
     */
    public static function getCountInside ($array): int
    {
        $count = 0;

        foreach ($array as $item) {
            $count += count($item);
        }

        return $count;
    }

    /**
     * Holt einen value von array und liefert diese als String zurück
     *
     * @param        $array
     * @param        $key - welcher key soll genommen werden?
     * @param string $separator
     *
     * @return string
     */
    public static function extractByKey ($array, $key, $separator = ',')
    {

        $re = [];

        foreach ($array as $item) {
            if (isset($item[$key])) {
                $re[] = $item[$key];
            }
        }

        return implode($separator, $re);

    }


    /**
     * Ordnet den Keys eines arrays ein bestimmtes Subelement des zugeordneten Arrays zu
     * (z.B. kann man aus 'a'=>['b'=>1] 'a'=>1 machen)
     *
     * @param array  $array
     * @param string $value_key
     *
     * @return array
     */
    public static function flatten (array $array, string $value_key): array
    {
        $new = [];

        foreach ($array as $key => $values) {
            $new[$key] = $values[$value_key];
        }

        return $new;
    }

    /**
     * Löscht einen Wert aus einem Array
     *
     * @param $value
     * @param array  $array
     * @param bool $strict Benutze === statt ==
     *
     * @return array
     */
    public static function deleteValue($value, array &$array, bool $strict = true):array
    {
        foreach (array_keys($array, $value, $strict) as $key) {
            unset($array[$key]);
        }
        return $array;
    }

    public static function deleteValueSequential($value, array &$array, bool $strict = true):array {
        return array_values(self::deleteValue($value, $array, $strict));
    }


    /**
     * case für keys in einem Array ändern (recursiv)
     * @param     $arr
     * @param int $case | CASE_UPPER oder CASE_LOWER
     *
     * @return array
     */
    public static function array_change_key_case_recursive($arr, int $case): array
    {
        return array_map(static function($item) use ($case){
            if(is_array($item)) {
                $item = ArrayHelper::array_change_key_case_recursive($item, $case);
            }
            return $item;
        },array_change_key_case($arr, $case));
    }

    /**
     * Filtert die Daten in dem Valuesarray eines Arrays, nur Keys in $keys werden beibehalten
     *
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function array_filter_value_keys_intersect(array $array, array $keys): array
    {
        $keys = array_flip($keys);
        foreach ($array as $key=>&$values) {
            $values = array_intersect_key($values, $keys);
        }
        return $array;
    }

    /**
     * Filtert die Daten in dem Valuesarray eines Arrays, Keys in $keys werden entfernt
     *
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function array_filter_value_keys_diff(array $array, array $keys): array
    {
        $keys = array_flip($keys);
        foreach ($array as $key=>&$values) {
            $values = array_diff_key($values, $keys);
        }
        return $array;
    }

    /**
     * Entfernt mehrere Keys aus einem Array
     * @param  array  &$array
     * @param  array  $keys
     * @return array
     */
    public static function deleteKeys(array &$array, array $keys)
    {
        foreach ($keys as $key) {
            if(isset($array[$key])){
                unset($array[$key]);
            }
        }
    }


    /**
     * Version for php < 7.3
     * @param array $arr
     *
     * @return int|string|null
     */
    public static function array_key_first(array $arr) {
        foreach($arr as $key => $unused) {
            return $key;
        }
        return NULL;
    }




}
