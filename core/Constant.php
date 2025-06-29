<?php
/**
 * This file is part of doba.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    jinhanjiang<jinhanjiang@foxmail.com>
 * @copyright jinhanjiang<jinhanjiang@foxmail.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Doba;

class Constant {

    private static $constants = [];

    public static function setConstant($name, $value) {
        if (! array_key_exists($name, self::$constants)) {
            self::$constants[$name] = $value;
        }
    }

    public static function getConstant($name) {
        if (array_key_exists($name, self::$constants)) {
            return self::$constants[$name];
        }
        return null;
    }

}