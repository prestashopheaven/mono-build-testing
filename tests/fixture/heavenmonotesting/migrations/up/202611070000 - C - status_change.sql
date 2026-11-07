CREATE TABLE IF NOT EXISTS `{_DB_PREFIX_}heavenmonotesting_status_change` (
    `id_status_change` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order` INT UNSIGNED NOT NULL,
    `id_order_state` INT UNSIGNED NOT NULL,
    `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_status_change`),
    KEY `id_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
