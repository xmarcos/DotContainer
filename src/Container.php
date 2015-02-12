<?php
namespace xmarcos\Dot;

use ArrayAccess;
use ArrayObject;

class Container extends ArrayObject
{
    const PATH_SEPARATOR = '.';

    public static function create($data = null)
    {
        if (is_array($data) || (is_object($data) && $data instanceof ArrayAccess)) {
            return new static($data);
        }

        return new static();
    }

    public function all()
    {
        return $this->getArrayCopy();
    }

    public function get($path, $default = null)
    {
        $keys = $this->parsePath($path);

        return array_reduce(
            $keys,
            function ($acc, $key) use ($default) {
                return isset($acc[$key]) ? $acc[$key] : $default;
            },
            $this->getArrayCopy()
        );
    }

    public function has($path)
    {
        $control = md5(uniqid());

        return $this->get($path, $control) !== $control;
    }

    public function set($path, $value = null)
    {
        $this->exchangeArray(
            array_replace_recursive(
                $this->getArrayCopy(),
                $this->buildTree($path, $value)
            )
        );

        return $this;
    }

    public function delete($path)
    {
        if ($this->has($path)) {
            $this->exchangeArray(
                array_replace_recursive(
                    $this->getArrayCopy(),
                    $this->buildTree($path, null)
                )
            );
        }

        return $this;
    }

    /**
     * Destroys an element from the Container with the given path
     *
     * @param $path The path of the key to destroy
     * @return \Container
     */
    public function offsetUnset($path)
    {
        if ($this->has($path)) {
            $this->exchangeArray(
                self::recursive_diff_key(
                    $this->getArrayCopy(),
                    $this->buildTree($path, null)
                )
            );
        }

        return $this;
    }

    public function reset()
    {
        $this->exchangeArray([]);

        return $this;
    }

    private function recursive_diff_key(array $array1, array $array2)
    {
        $diff = array_diff_key($array1, $array2);
        $to_check = array_intersect_key($array1, $array2);

        foreach ($to_check as $k => $v) {
            if (is_array($array1[$k]) && is_array($array2[$k])) {
                $keep = self::recursive_diff_key($array1[$k], $array2[$k]);

                if ($keep) {
                    $diff[$k] = $keep;
                }
            }
        }

        return $diff;
    }

    private function buildTree($path, $value = null)
    {
        $keys = $this->parsePath($path);
        $tree = [];
        $copy = & $tree;

        while (count($keys)) {
            $key  = array_shift($keys);
            $copy = & $copy[$key];
        }
        $copy = $value;

        return $tree;
    }

    private function parsePath($path)
    {
        $parts = array_filter(
            explode(static::PATH_SEPARATOR, (string) $path),
            'strlen'
        );

        return array_reduce(
            $parts,
            function ($acc, $v) {
                $acc[] = ctype_digit($v) ? intval($v) : $v;

                return $acc;
            },
            []
        );
    }
}
