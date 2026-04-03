-- payments jadvali -- email orqali kelgan to'lovlar saqlanadi

CREATE TABLE IF NOT EXISTS `payments` (
  `id`          int NOT NULL AUTO_INCREMENT,
  `message_id`  varchar(255) NOT NULL UNIQUE,
  `amount`      bigint NOT NULL,
  `merchant`    varchar(255) DEFAULT NULL,
  `date`        varchar(100) DEFAULT NULL,
  `card_type`   varchar(50) DEFAULT 'unknown',
  `raw_message` text,
  `status`      enum('pending','used') DEFAULT 'pending',
  `used_order`  varchar(255) DEFAULT NULL,
  `created_at`  datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_amount_status` (`amount`, `status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
