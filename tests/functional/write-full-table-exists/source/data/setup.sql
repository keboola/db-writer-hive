CREATE TABLE `sales` (
  `usergender` varchar(100),
  `county` varchar(255),
  `countycode-db` int
);

INSERT INTO `sales` (`usergender`, `county`, `countycode-db`) VALUES
    ('setup-sql', '123', 123),
    ('setup-sql', '345', 345),
    ('setup-sql', '567', 567);
