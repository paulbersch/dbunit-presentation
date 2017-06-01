<?php

class Calculator {

    public function add($a, $b) {
        return $a + $b;
    }

    public function divide($dividend, $divisor) {
        return $dividend / $divisor;
    }
}

class CalculatorTest extends TestCase {
    public $calculator;

    public function setUp() {
        $this->calculator = new Calculator();
    }

    public function testAddPositiveIntegers() {
        $this->assertEqual(4, $this->calculator->add(2, 2));
    }
}

