CREATE TABLE IF NOT EXISTS `openid_users` (
  `id` int(11) NOT NULL auto_increment,
  `openid_url` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  `last_login` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `user_id` (`user_id`),
  KEY `openid_url` (`openid_url`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
