CREATE TABLE `users`(
  `unused1` varchar(255),
  `name` varchar(255),
  `unused2` varchar(255),
  `id` int,
  `unused3` varchar(255)
);


INSERT INTO `users` VALUES
    (null, 'User 1', null, 1, null),
    (null, 'User 2', null, 2, null),
    (null, 'User 3 - DUPLICATE', null, 3, null);
