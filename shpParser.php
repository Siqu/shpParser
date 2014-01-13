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
 * Parser for ESRI ShapeFiles.
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
     * <ul>
     *  <li>d - Double value 8 bytes long</li>
     *  <li>V - unsigned long with little endian 4 bytes long</li>
     *  <li>N - unsigned long with big endian 4 bytes long</li>
     * </ul>
     * @var array
     */
    private $dataLength = array(
        'd' => 8,
        'V' => 4,
        'N' => 4
    );

    /**
     * Creates a new shpParser object, establishes a new Database connection and starts loading the data.
     *
     * @param string $path The relative path to the ESRI Shape File.
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
            print 'ShapeFile is already loaded in the database';
        } else {
            $this->load();
        }
    }

    /**
     * Start loading the Shape File.
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

    /**
     * Load the main SHP File Header.
     * The header is 100 bytes long and is structured as follows:
     * <ul>
     *  <li>Byte 0: File Code (Integer Big Endian)</li>
     *  <li>Byte 4 - 20: Unused (Integer Big Endian)</li>
     *  <li>Byte 24: File Length (Integer Big Endian)</li>
     *  <li>Byte 28: Version (Integer Little Endian)</li>
     *  <li>Byte 32: Shape Type (Integer Little Endian)</li>
     *  <li>Byte 36: Bounding box x_min (Double Big Endian)</li>
     *  <li>Byte 44: Bounding box y_min (Double Big Endian)</li>
     *  <li>Byte 52: Bounding box x_max (Double Big Endian)</li>
     *  <li>Byte 60: Bounding box y_max (Double Big Endian)</li>
     *  <li>Byte 68: Bounding box z_min (Double Big Endian)</li>
     *  <li>Byte 76: Bounding box z_max (Double Big Endian)</li>
     *  <li>Byte 84: Bounding box m_min (Double Big Endian)</li>
     *  <li>Byte 92: Bounding box m_max (Double Big Endian)</li>
     * </ul>
     *
     * @throws exception\InvalidShapeTypeException Thrown when the read Shape Type is unknown.
     * @throws exception\InvalidFileCodeException Thrown when the read File Code is not 9994.
     */
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

        $box_id = $this->loadBoundingBox(true, true);

        $st = $this->conn->prepare('INSERT INTO Shape_Files (path, file_length, version, shape_type, bounding_box) VALUES (:path, :file_length, :version, :shape_type, :bounding_box)');
        $st->execute(
            array(
                ':path' => $this->path,
                ':file_length' => $file_length,
                ':version' => $version,
                ':shape_type' => $shape_type,
                ':bounding_box' => $box_id
            ));

        $this->shpId = $this->conn->lastInsertId();
    }

    /**
     * Load a bounding box and insert it into the database.
     *
     * @param boolean $measure This values tells if the values of the bounding box are measured.
     * @param boolean $depth This values tells if the values of the bounding box are 3D.
     * @return string The id with which the bounding box was added to the database.
     */
    private function loadBoundingBox($measure, $depth) {

        $x_min = $this->loadData(DOUBLE_TYPE);
        $y_min = $this->loadData(DOUBLE_TYPE);
        $x_max = $this->loadData(DOUBLE_TYPE);
        $y_max = $this->loadData(DOUBLE_TYPE);
        $z_min = 0.0;
        $z_max = 0.0;
        $m_min = 0.0;
        $m_max = 0.0;

        if($depth) {
            $z_min = $this->loadData(DOUBLE_TYPE);
            $z_max = $this->loadData(DOUBLE_TYPE);
        }

        if($measure) {
            $m_min = $this->loadData(DOUBLE_TYPE);
            $m_max = $this->loadData(DOUBLE_TYPE);
        }

        $st = $this->conn->prepare('INSERT INTO Bounding_Boxes (x_min, x_max, y_min, y_max, z_min, z_max, m_min, m_max) VALUES (:x_min, :x_max, :y_min, :y_max, :z_min, :z_max, :m_min, :m_max)');
        $st->execute(
            array(
                ':x_min' => $x_min,
                ':x_max' => $x_max,
                ':y_min' => $y_min,
                ':y_max' => $y_max,
                ':z_min' => $z_min,
                ':z_max' => $z_max,
                ':m_min' => $m_min,
                ':m_max' => $m_max
            ));

        return $this->conn->lastInsertId();
    }

    /**
     * Low level data type reading function.
     *
     * @param string $type The data type.
     * @see getLengthForDataFormat()
     * @return float|int|mixed|null 0 if it read a ESRI no data concept, null if the data type is unknown or the read value.
     * @throws \Exception When an unknown Exception occurs.
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
     *
     * Known types:
     * <ul>
     *  <li>d for double values - has a length of 8</li>
     *  <li>V for unsigned long with little endian - has a length of 4</li>
     *  <li>N for unsigned long with big endian- has a length of 4</li>
     * </ul>
     *
     * @param string $type The data type.
     * @return integer The length for the specified type.
     * @throws InvalidDataTypeException When the data type is unknown.
     */
    private function getLengthForDataFormat($type) {

        if(array_key_exists($type, $this->dataLength)) {
            return $this->dataLength[$type];
        }

        throw new InvalidDataTypeException('The data type '.$type.' is unknown. Type can only be d,V or N');
    }
} 