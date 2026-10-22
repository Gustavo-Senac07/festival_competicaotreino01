-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema festival_viva01
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema festival_viva01
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `festival_viva01` ;
USE `festival_viva01` ;

-- -----------------------------------------------------
-- Table `festival_viva01`.`participantes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `festival_viva01`.`participantes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `cpf` VARCHAR(14) NOT NULL,
  `telefone` VARCHAR(20) NULL,
  `criado_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `cpf_UNIQUE` (`cpf` ASC) VISIBLE,
  UNIQUE INDEX `email_UNIQUE` (`email` ASC) VISIBLE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `festival_viva01`.`atividades`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `festival_viva01`.`atividades` (
  `id` INT NOT NULL,
  `titulo` VARCHAR(100) NOT NULL,
  `descricao` VARCHAR(400) NULL,
  `vagas` INT NOT NULL,
  `data_hora` DATETIME NOT NULL,
  `duracao_minutos` INT NOT NULL DEFAULT '60',
  `criado_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `festival_viva01`.`inscricoes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `festival_viva01`.`inscricoes` (
  `id` INT NOT NULL,
  `data_inscricao` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `atividade_id` INT NOT NULL,
  `participante_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_inscricoes_atividades_idx` (`atividade_id` ASC) VISIBLE,
  INDEX `fk_inscricoes_participantes1_idx` (`participante_id` ASC) VISIBLE,
  CONSTRAINT `fk_inscricoes_atividades`
    FOREIGN KEY (`atividade_id`)
    REFERENCES `festival_viva01`.`atividades` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_inscricoes_participantes1`
    FOREIGN KEY (`participante_id`)
    REFERENCES `festival_viva01`.`participantes` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

