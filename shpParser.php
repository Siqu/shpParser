<?php

namespace sP;

use sP\classes\ConnectionFactory;
use sP\exception\FileNotFoundException;
use sP\exception\InvalidDataTypeException;
use sP\exception\InvalidFileCodeException;
use sP\exception\InvalidShapeTypeException;

require_once('classes/ConnectionFactory.php');

define('DOUBLE_TYPE', 'd');
define('BIG_ENDIAN', 'N');
define('LITTLE_ENDIAN', 'V');
define('TOO_SMALL', -pow(10, 38));

/**
 * Class shpParser
 * Parser for ESRI ShapeFiles
 *
 * @package sP
 * @author Sebastian Paulmichl siquent@me.com
 * @version 1.0
 */
class shpParser {

    private $path = null;
    private $conn = null;
    private $shpFile = null;
    private $shpId = null;

    /**
     * This array contains the known data types.
     * d - Double value 8 bytes long
     * V - unsigned long with little endian 4 bytes long
     * N - unsigned long with big endian 4 bytes long
     * @var array
     */
    private $dataLength = array(
        'd' => 8,
        'V' => 4,
        'N' => 4
    );

    /**
     * Creates a new shpParser object, establishes a new Database connection and starts loading the data
     *
     * @param string $path
     */
    public function __construct($path) {
        $this->path = $path;
        $this->conn = ConnectionFactory::getFactory()->getConnection();

        $st = $this->conn->prepare('SELECT * FROM Shape_Files WHERE path = ":path"');
        $st->execute(
            array(
                ':path' => $path
            ));

        if($st->rowCount() != 0) {
            print 'ShapeFile is allready loaded in the database';
        } else {
            $this->load();
        }
    }

    /**
     * Start loading the Shape File
     */
    private function load() {
        if(!file_exists($this->path)) {
            throw new FileNotFoundException('File "'.$this->path.'" could not be found.');
        }

        $this->shpFile = fopen($this->path, 'r');

        $this->conn->beginTransaction();

        $this->loadMainFileHeader();

        //$this->conn->commit();

        fclose($this->shpFile);
    }

    private function loadMainFileHeader() {
        $file_code = $this->loadData(BIG_ENDIAN);

        if($file_code != 9994) {
            throw new InvalidFileCodeException('Shape File has to have file code "9994" but found "'.$file_code.'"');
        }

        fseek($this->shpFile, 24);

        $file_length = $this->loadData(BIG_ENDIAN);
        $version = $this->loadData(LITTLE_ENDIAN);
        $shape_type = $this->loadData(LITTLE_ENDIAN);

        $st = $this->conn->prepare('SELECT * FROM Shape_types WHERE id = ":id"');
        $st->execute(
            array(
                ':id' => $shape_type
            ));

        if($st->rowCount() == 0) {
            throw new InvalidShapeTypeException('The shape type '.$shape_type.' is unknown.');
        }

        $st = $this->conn->prepare('INSERT INTO Shape_Files (path, file_length, version, shape_type) VALUES (:path, :file_length, :version, :shape_type)');
        $st->execute(
            array(
                ':path' => $this->path,
                ':file_length' => $file_length,
                ':version' => $version,
                ':shape_type' => $shape_type
            ));

        $this->shpId = $this->conn->lastInsertId();
    }

    /**
     * Low level data type reading function
     *
     * @param string $type the data type
     * @return float|int|mixed|null 0 if it read no a ESRI no data concept, null if the data type is unknown or the read value
     * @throws \Exception when an unknown Exception occurs
     */
    private function loadData($type) {
        try {
            $length = $this->getLengthForDataFormat($type);

            $r_data = fread($this->shpFile, $length);

            $r_data =  unpack($type, $r_data);

            if($type == DOUBLE_TYPE) {
                $val = current($r_data);

                if($val < TOO_SMALL) {
                    return 0;
                }
                return round($val, 3);
            } else {
                return current($r_data);
            }
        } catch (InvalidDataTypeException $e) {
            return null;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Returns the data length for a specific type.
     * Known types:
     *              - d for double values
     *              - V for unsigned long with little endian
     *              - N for unsigned long with big endian
     * @param string $type the data type
     * @return mixed the length for the specified type
     * @throws InvalidDataTypeException When the data type is unknown
     */
    private function getLengthForDataFormat($type) {

        if(array_key_exists($type, $this->dataLength)) {
            return $this->dataLength[$type];
        }

        throw new InvalidDataTypeException('The data type '.$type.' is unknown. Type can only be d,V or N');
    }
} 