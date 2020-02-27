CREATE TABLE `users`(
  `id` string, -- invalid type, should be int
  `name` varchar(255)
)
CLUSTERED BY (id) INTO 1 BUCKETS STORED AS orc
TBLPROPERTIES('transactional'='true');

INSERT INTO `users` (`id`, `name`) VALUES
    ('1', 'User 1'),
    ('2', 'User 2'),
    ('3', 'User 3');

