CREATE TABLE `sales` (
  `unused1` varchar(255),
  `county` varchar(255),
  `usergender` varchar(100),
  `unused2` varchar(255),
  `countycode-db` int,
  `unused3` varchar(255)
);

INSERT INTO `sales` VALUES
    (null, '123', 'SHOULD BE OVERWRITTEN', null, 123, null),
    (null, '456', 'SHOULD BE OVERWRITTEN', null, 456, null),
    (null, '789', 'SHOULD BE OVERWRITTEN', null, 789, null);
