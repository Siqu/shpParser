<?php

namespace sP\classes;

define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_DATABASE', 'shpParser');
define('DB_HOST', 'localhost');

/**
 * Class ConnectionFactory
 * MySQL Connection Singleton
 *
 * @package sP\classes
 * @author Sebastian Paulmichl
 * @version 1.0
 * @copyright Copyright (c) 2014, Sebastian Paulmichl
 * @license http://opensource.org/licenses/MIT  MIT License
 */
class ConnectionFactory {

    private static $factory;

    /**
     * Get the ConnectionFactory or initialize it
     *
     * @return ConnectionFactory
     */
    public static function getFactory() {
        if(!self::$factory) {
            self::$factory = new ConnectionFactory();
        }

        return self::$factory;
    }

    /**
     * @var \PDO
     */
    private $db;

    /**
     * Get the already initialized database connection or if not initialized create a new connection
     *
     * @return \PDO
     */
    public function getConnection() {
        if(!$this->db) {
            $this->db = new \PDO('mysql:dbname='.DB_DATABASE.';host='.DB_HOST, DB_USER, DB_PASSWORD);
        }

        return $this->db;
    }
} 