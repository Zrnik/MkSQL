CREATE TABLE `account` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(60) NOT NULL,
    `password` char(64) NOT NULL COMMENT 'sha256',
    PRIMARY KEY (`id`),
    UNIQUE KEY `account_username_unique_index` (`username`),
    UNIQUE KEY `account_id_unique_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3
