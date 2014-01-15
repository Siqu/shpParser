<?php

namespace sP\exception;

/**
 * Class InvalidDataTypeException
 *
 * This exception gets thrown when a invalid data type is used to read data from the shp file.
 * Known data types are d,V,N for further Information {@link pack()}
 *
 * @package sP\exception
 * @author Sebastian Paulmichl siquent@me.com
 * @version 1.0
 * @copyright Copyright (c) 2014, Sebastian Paulmichl
 * @license http://opensource.org/licenses/MIT  MIT License
 */
class InvalidDataTypeException extends \Exception {

} 