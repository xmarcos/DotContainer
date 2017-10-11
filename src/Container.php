<?php
/**
 * (c) 2017 Marcos Sader.
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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

    public function reset()
    {
        $this->exchangeArray([]);

        return $this;
    }

    private function buildTree($path, $value = null)
    {
        $keys = $this->parsePath($path);
        $tree = [];
        $copy = &$tree;

        while (count($keys)) {
            $key  = array_shift($keys);
            $copy = &$copy[$key];
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
