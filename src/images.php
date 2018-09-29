<?php
/**
 * this7 PHP Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright 2016-2018 Yan TianZeng<qinuoyun@qq.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://www.ub-7.com
 */
namespace this7\images;
class images {
    protected $link;

    protected function driver() {
        $this->link = new \this7\images\build\base();

        return $this;
    }

    public function __call($method, $params) {
        if (is_null($this->link)) {
            $this->driver();
        }

        return call_user_func_array([$this->link, $method], $params);
    }

    public static function single() {
        static $link;
        if (is_null($link)) {
            $link = new static();
        }

        return $link;
    }

    public static function __callStatic($name, $arguments) {
        return call_user_func_array([static::single(), $name], $arguments);
    }
}