SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';

CREATE SCHEMA IF NOT EXISTS `shpParser` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci ;
USE `shpParser` ;

-- -----------------------------------------------------
-- Table `shpParser`.`Shape_Types`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shpParser`.`Shape_Types` ;

CREATE  TABLE IF NOT EXISTS `shpParser`.`Shape_Types` (
  `id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `shpParser`.`Bounding_Boxes`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shpParser`.`Bounding_Boxes` ;

CREATE  TABLE IF NOT EXISTS `shpParser`.`Bounding_Boxes` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `x_min` FLOAT NULL DEFAULT 0.0 ,
  `x_max` FLOAT NULL DEFAULT 0.0 ,
  `y_min` FLOAT NULL DEFAULT 0.0 ,
  `y_max` FLOAT NULL DEFAULT 0.0 ,
  `z_min` FLOAT NULL DEFAULT 0.0 ,
  `z_max` FLOAT NULL DEFAULT 0.0 ,
  `m_min` FLOAT NULL DEFAULT 0.0 ,
  `m_max` FLOAT NULL DEFAULT 0.0 ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `shpParser`.`Shape_Files`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shpParser`.`Shape_Files` ;

CREATE  TABLE IF NOT EXISTS `shpParser`.`Shape_Files` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `path` VARCHAR(255) NOT NULL ,
  `file_length` INT NULL ,
  `version` INT NULL ,
  `shape_type` INT NULL ,
  `bounding_box` INT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `shape_type_to_shape_file` (`shape_type` ASC) ,
  INDEX `bounding_box_to_shape_file` (`bounding_box` ASC) ,
  CONSTRAINT `shape_type_to_shape_file`
    FOREIGN KEY (`shape_type` )
    REFERENCES `shpParser`.`Shape_Types` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `bounding_box_to_shape_file`
    FOREIGN KEY (`bounding_box` )
    REFERENCES `shpParser`.`Bounding_Boxes` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `shpParser`.`MultiPoints`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shpParser`.`MultiPoints` ;

CREATE  TABLE IF NOT EXISTS `shpParser`.`MultiPoints` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `bounding_box` INT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `bounding_box_to_multi_points` (`bounding_box` ASC) ,
  CONSTRAINT `bounding_box_to_multi_points`
    FOREIGN KEY (`bounding_box` )
    REFERENCES `shpParser`.`Bounding_Boxes` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `shpParser`.`PolyLine`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shpParser`.`PolyLine` ;

CREATE  TABLE IF NOT EXISTS `shpParser`.`PolyLine` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `bounding_box` INT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `bounding_box_to_poly_line` (`bounding_box` ASC) ,
  CONSTRAINT `bounding_box_to_poly_line`
    FOREIGN KEY (`bounding_box` )
    REFERENCES `shpParser`.`Bounding_Boxes` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `shpParser`.`Polygon`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shpParser`.`Polygon` ;

CREATE  TABLE IF NOT EXISTS `shpParser`.`Polygon` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `bounding_box` INT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `bounding_box_to_polygon` (`bounding_box` ASC) ,
  CONSTRAINT `bounding_box_to_polygon`
    FOREIGN KEY (`bounding_box` )
    REFERENCES `shpParser`.`Bounding_Boxes` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `shpParser`.`Parts`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shpParser`.`Parts` ;

CREATE  TABLE IF NOT EXISTS `shpParser`.`Parts` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `poly_line` INT NULL ,
  `polygon` INT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `poly_line_to_parts` (`poly_line` ASC) ,
  INDEX `polygon_to_parts` (`polygon` ASC) ,
  CONSTRAINT `poly_line_to_parts`
    FOREIGN KEY (`poly_line` )
    REFERENCES `shpParser`.`PolyLine` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `polygon_to_parts`
    FOREIGN KEY (`polygon` )
    REFERENCES `shpParser`.`Polygon` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `shpParser`.`Points`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shpParser`.`Points` ;

CREATE  TABLE IF NOT EXISTS `shpParser`.`Points` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `x` FLOAT NULL DEFAULT 0.0 ,
  `y` FLOAT NULL DEFAULT 0.0 ,
  `z` FLOAT NULL DEFAULT 0.0 ,
  `m` FLOAT NULL DEFAULT 0.0 ,
  `multi_point` INT NULL ,
  `part` INT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `multi_point_to_points` (`multi_point` ASC) ,
  INDEX `part_to_points` (`part` ASC) ,
  CONSTRAINT `multi_point_to_points`
    FOREIGN KEY (`multi_point` )
    REFERENCES `shpParser`.`MultiPoints` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `part_to_points`
    FOREIGN KEY (`part` )
    REFERENCES `shpParser`.`Parts` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `shpParser`.`Records`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shpParser`.`Records` ;

CREATE  TABLE IF NOT EXISTS `shpParser`.`Records` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `record_number` INT NULL ,
  `record_length` INT NULL ,
  `shape_file` INT NULL ,
  `shape_type` INT NULL ,
  `point` INT NULL ,
  `multi_point` INT NULL ,
  `poly_line` INT NULL ,
  `polygon` INT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `point_to_rec` (`point` ASC) ,
  INDEX `multi_point_to_rec` (`multi_point` ASC) ,
  INDEX `poly_line_to_rec` (`poly_line` ASC) ,
  INDEX `polygon_to_rec` (`polygon` ASC) ,
  INDEX `shape_file_to_rec` (`shape_file` ASC) ,
  INDEX `shape_type_to_rec` (`shape_type` ASC) ,
  CONSTRAINT `point_to_rec`
    FOREIGN KEY (`point` )
    REFERENCES `shpParser`.`Points` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `multi_point_to_rec`
    FOREIGN KEY (`multi_point` )
    REFERENCES `shpParser`.`MultiPoints` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `poly_line_to_rec`
    FOREIGN KEY (`poly_line` )
    REFERENCES `shpParser`.`PolyLine` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `polygon_to_rec`
    FOREIGN KEY (`polygon` )
    REFERENCES `shpParser`.`Polygon` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `shape_file_to_rec`
    FOREIGN KEY (`shape_file` )
    REFERENCES `shpParser`.`Shape_Files` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `shape_type_to_rec`
    FOREIGN KEY (`shape_type` )
    REFERENCES `shpParser`.`Shape_Types` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;



SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

INSERT INTO `Shape_types` (`id`,`name`) VALUES (0,'Null Shape');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (1,'Point');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (3,'PolyLine');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (5,'Polygon');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (8,'MultiPoint');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (11,'PointZ');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (13,'PolyLineZ');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (15,'PolygonZ');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (18,'MultiPointZ');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (21,'PointM');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (23,'PolyLineM');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (25,'PolygonM');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (28,'MultiPointM');
INSERT INTO `Shape_types` (`id`,`name`) VALUES (31,'MultiPatch');