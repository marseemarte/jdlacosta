-- Crear tabla para almacenar el número de sorteo (histórico)
CREATE TABLE IF NOT EXISTS `num_sorteo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `numero` VARCHAR(3) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
