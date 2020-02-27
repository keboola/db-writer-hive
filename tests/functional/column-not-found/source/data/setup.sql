CREATE TABLE `users`(
  `name` varchar(255)
)
CLUSTERED BY (`name`) INTO 1 BUCKETS STORED AS orc
TBLPROPERTIES('transactional'='true');

INSERT INTO `users` (`name`) VALUES
    ('User 1'),
    ('User 2'),
    ('User 3');

