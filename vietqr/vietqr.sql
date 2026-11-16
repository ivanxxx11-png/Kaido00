-- Chạy 1 lần trong phpMyAdmin (chỉ cần nếu dùng webhook tự động)
CREATE TABLE `vietqr_giaodich` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `gateway_id` varchar(64) NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `content` varchar(500) DEFAULT NULL,
  `matched` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vietqr_gateway` (`gateway_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
