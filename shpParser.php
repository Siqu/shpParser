<?php

namespace sP;

use sP\classes\ConnectionFactory;
use sP\exception\FileNotFoundException;
use sP\exception\InvalidDataTypeException;
use sP\exception\InvalidFileCodeException;
use sP\exception\InvalidShapeTypeException;
use sP\exception\InvalidContentLengthException;

require_once('classes/ConnectionFactory.php');
require_once('classes/exceptions/InvalidContentLengthException.php');
require_once('classes/exceptions/FileNotFoundException.php');
require_once('classes/exceptions/InvalidDataTypeException.php');
require_once('classes/exceptions/InvalidFileCodeException.php');
require_once('classes/exceptions/InvalidShapeTypeException.php');

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
    private $file_length = 0;
    private $read_length = 0;

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
        $this->loadMainFileData();

        //$this->conn->commit();

        fclose($this->shpFile);
    }

    /**
     * Load the main SHP File Header.
     *
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

        $this->file_length = $this->loadData(BIG_ENDIAN);
        $version = $this->loadData(LITTLE_ENDIAN);
        $shape_type = $this->loadData(LITTLE_ENDIAN);

        if(!$this->doesShapeTypeExist($shape_type)) {
            throw new InvalidShapeTypeException('The shape type '.$shape_type.' is unknown.');
        }

        $box_id = $this->loadBoundingBox(true, true);

        $st = $this->conn->prepare('INSERT INTO Shape_Files (path, file_length, version, shape_type, bounding_box) VALUES (:path, :file_length, :version, :shape_type, :bounding_box)');
        $st->execute(
            array(
                ':path' => $this->path,
                ':file_length' => $this->file_length,
                ':version' => $version,
                ':shape_type' => $shape_type,
                ':bounding_box' => $box_id
            ));

        $this->shpId = $this->conn->lastInsertId();
    }

    /**
     * Load all records
     *
     * The record header is 8 bytes long and structured as follows:
     * <ul>
     *  <li>Byte 0: Record Number (Integer Big Endian)</li>
     *  <li>Byte 4: Content Length (Integer Big Endian)</li>
     * </ul>
     *
     * The record content depends on the first 4 bytes, which define the shape type.
     * @see loadRecord()
     */
    private function loadMainFileData() {
        $this->read_length = 50;

        while($this->read_length < $this->file_length) {
            $cl = $this->loadRecord();

            $this->read_length += $cl + 4;
        }
    }

    /**
     * Load a single record into the database.
     *
     * The record content is structured as follows:
     * <ul>
     *  <li>Byte 0: Shape Type (Integer Little Endian)</li>
     *  <li>
     *      Depends on the shape type.
     *
     *      See:
     *      <ul>
     *          <li>{@link loadPoint()}</li>
     *          <li>{@link loadMultiPoint()}</li>
     *      </ul>
     *  </li>
     *
     * </ul>
     *
     * @return int The byte size of the read record.
     * @throws exception\InvalidShapeTypeException Thrown when the read Shape Type is unknown.
     */
    private function loadRecord() {
        $rec_number = $this->loadData(BIG_ENDIAN);
        $content_length = $this->loadData(BIG_ENDIAN);
        $shape_type = $this->loadData(LITTLE_ENDIAN);

        if(!$this->doesShapeTypeExist($shape_type)) {
            throw new InvalidShapeTypeException('The shape type '.$shape_type.' is unknown.');
        }

        $st = $this->conn->prepare('INSERT INTO Records (record_number, record_length, shape_file, shape_type) VALUES (:record_number, :record_length, :shape_file, :shape_type)');
        $st->execute(
            array(
                ':record_number' => $rec_number,
                ':record_length' => $content_length,
                ':shape_file' => $this->shpId,
                ':shape_type' => $shape_type
            ));

        $rec_id = $this->conn->lastInsertId();

        switch($shape_type) {
            case 0:
                //NullShape
                break;
            case 1:
                //Point
                $id = $this->loadPoint($content_length, false, false);

                $st = $this->conn->prepare('UPDATE Records SET point = :point WHERE id = :id');
                $st->execute(
                    array(
                        ':point' => $id,
                        ':id' => $rec_id
                    ));
                break;
            case 3:
                //PolyLine
                break;
            case 5:
                //Polygon
                break;
            case 8:
                //MultiPoint
                $id = $this->loadMultiPoint($content_length, false, false);

                $st = $this->conn->prepare('UPDATE Records SET multi_point = :multi_point WHERE id = :id');
                $st->execute(
                    array(
                        ':multi_point' => $id,
                        ':id' => $rec_id
                    ));
                break;
            case 11:
                //PointZ
                $id = $this->loadPoint($content_length, true, true);

                $st = $this->conn->prepare('UPDATE Records SET point = :point WHERE id = :id');
                $st->execute(
                    array(
                        ':point' => $id,
                        ':id' => $rec_id
                    ));
                break;
            case 13:
                //PolyLineZ
                break;
            case 15:
                //PolygonZ
                break;
            case 18:
                //MultiPointZ
                $id = $this->loadMultiPoint($content_length, true, true);

                $st = $this->conn->prepare('UPDATE Records SET multi_point = :multi_point WHERE id = :id');
                $st->execute(
                    array(
                        ':multi_point' => $id,
                        ':id' => $rec_id
                    ));
                break;
            case 21:
                //PointM
                $id = $this->loadPoint($content_length, true, false);

                $st = $this->conn->prepare('UPDATE Records SET point = :point WHERE id = :id');
                $st->execute(
                    array(
                        ':point' => $id,
                        ':id' => $rec_id
                    ));
                break;
            case 23:
                //PolyLineM
                break;
            case 25:
                //PolygonM
                break;
            case 28:
                //MultiPointM
                $id = $this->loadMultiPoint($content_length, true, false);

                $st = $this->conn->prepare('UPDATE Records SET multi_point = :multi_point WHERE id = :id');
                $st->execute(
                    array(
                        ':multi_point' => $id,
                        ':id' => $rec_id
                    ));
                break;
            case 31:
                //MultiPatch
                break;
        }

        return $content_length;
    }

    /**
     * Load a Point and insert it into the database.
     *
     * A point record is structured as follows:
     * <ul>
     *  <li>Byte 4: x (Double Little Endian)</li>
     *  <li>Byte 12: y (Double Little Endian)</li>
     *  <li>Byte 20 *: m (Double Little Endian)</li>
     *  <li>Byte 20 **: z (Double Little Endian)</li>
     *  <li>Byte 28 **: m (Double Little Endian)</li>
     * </ul>
     *
     * *  these bytes are available when it is a PointM shape
     *
     * ** these bytes are available when it is a PointZ shape
     *
     * @param int $content_length The content length of the point record.
     * @param boolean $measure This values tells if the values of the point are measured.
     * @param boolean $depth This values tells if the values of the point are 3D.
     * @return string The id with which the point was added to the database.
     * @throws exception\InvalidContentLengthException Thrown when the read content length is different to the length in the record header.
     */
    private function loadPoint($content_length, $measure, $depth) {

        //2 because the header was already read
        $cl = 2;

        $x = $this->loadData(DOUBLE_TYPE);
        $cl += 4;

        $y = $this->loadData(DOUBLE_TYPE);
        $cl += 4;

        $z = 0.0;
        $m = 0.0;

        if($depth) {
            $z = $this->loadData(DOUBLE_TYPE);
            $cl += 4;
        }

        if($cl == $content_length) {
            $measure = false;
        }

        if($measure) {
            $m = $this->loadData(DOUBLE_TYPE);

            $cl += 4;
        }

        $this->checkContentLengthIsSame($content_length, $cl);

        $st = $this->conn->prepare('INSERT INTO Points (x, y, z, m) VALUES (:x, :y, :z, :m)');
        $st->execute(
            array(
                ':x' => $x,
                ':y' => $y,
                ':z' => $z,
                ':m' => $m
            ));

        return $this->conn->lastInsertId();
    }

    private function loadMultiPoint($content_length, $measure, $depth) {

        //2 because the header was already read
        $cl = 2;

        $box_id = $this->loadBoundingBox(false, false);
        $cl += 16;

        $st = $this->conn->prepare('INSERT INTO MultiPoints (bounding_box) VALUES (:bounding_box)');
        $st->execute(
            array(
                ':bounding_box' => $box_id
            ));
        $mp_id = $this->conn->lastInsertId();

        $num_points = $this->loadData(LITTLE_ENDIAN);
        $cl += 2;

        $p_ids = array();
        for($i = 0; $i < $num_points; $i++) {
            $p_id = $this->loadPoint(10, false, false);
            $p_ids[] = $p_id;

            $st = $this->conn->prepare('UPDATE Points SET multi_point = :multi_point WHERE id = :id');
            $st->execute(
                array(
                    ':multi_point' => $mp_id,
                    ':id' => $p_id
                ));

            $cl += 8;
        }

        if($depth) {
            $z_min = $this->loadData(DOUBLE_TYPE);
            $cl += 4;

            $z_max = $this->loadData(DOUBLE_TYPE);
            $cl += 4;

            $st = $this->conn->prepare('UPDATE Bounding_Boxes SET z_min = :z_min, z_max = :z_max WHERE id = :id');
            $st->execute(
                array(
                    ':z_min' => $z_min,
                    ':z_max' => $z_max,
                    ':id' => $box_id
                ));

            for($i = 0; $i < $num_points; $i++) {
                $z = $this->loadData(DOUBLE_TYPE);
                $cl += 4;

                $st = $this->conn->prepare('UPDATE Points SET z = :z WHERE id = :id');
                $st->execute(
                    array(
                        ':z' => $z,
                        ':id' => $p_ids[$i]
                    ));
            }
        }

        if($cl == $content_length) {
            $measure = false;
        }

        if($measure) {
            $m_min = $this->loadData(DOUBLE_TYPE);
            $cl += 4;

            $m_max = $this->loadData(DOUBLE_TYPE);
            $cl += 4;

            $st = $this->conn->prepare('UPDATE Bounding_Boxes SET m_min = :m_min, m_max = :m_max WHERE id = :id');
            $st->execute(
                array(
                    ':m_min' => $m_min,
                    ':m_max' => $m_max,
                    ':id' => $box_id
                ));

            for($i = 0; $i < $num_points; $i++) {
                $m = $this->loadData(DOUBLE_TYPE);
                $cl += 4;

                $st = $this->conn->prepare('UPDATE Points SET m = :m WHERE id = :id');
                $st->execute(
                    array(
                        ':m' => $m,
                        ':id' => $p_ids[$i]
                    ));
            }
        }

        $this->checkContentLengthIsSame($content_length, $cl);

        return $this->conn->lastInsertId();
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

    /**
     * This function looks into the database to check if a specific shape type id exists.
     *
     * @param string $id The shape type id which should be checked.
     * @return bool True if the shape type id exists, otherwise false.
     */
    private function doesShapeTypeExist($id) {
        $st = $this->conn->prepare('SELECT * FROM Shape_types WHERE id = ":id"');
        $st->execute(
            array(
                ':id' => $id
            ));

        if($st->rowCount() == 0) {
            return false;
        }

        return true;
    }

    /**
     * Helper function which checks if two content lengths are the same.
     *
     * @param int $expected The expected content length.
     * @param int $read The read content length.
     * @throws exception\InvalidContentLengthException Thrown when the first content length is different to the second length in the record header.
     */
    private function checkContentLengthIsSame($expected, $read) {
        if($expected != $read) {
            throw new InvalidContentLengthException('Content length was excepted to be '.$expected.' but read '.$read);
        }
    }
} 