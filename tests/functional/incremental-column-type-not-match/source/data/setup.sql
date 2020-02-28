CREATE TABLE `users`(
  `id` string, -- invalid type, should be int
  `name` varchar(255)
);

INSERT INTO `users` VALUES
    ('1', 'User 1'),
    ('2', 'User 2'),
    ('3', 'User 3');

