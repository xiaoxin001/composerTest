<?php
/**
 * Created by PhpStorm.
 * User: ouyangxiaoxin
 * Date: 2018/4/7
 * Time: 下午10:35
 */

#test
require_once __DIR__."/src/Test.class.php";

$test = new \xx\wxmini\Test();
$test->sayHelloWorld();

echo "<br/>";

$test->sayName();

