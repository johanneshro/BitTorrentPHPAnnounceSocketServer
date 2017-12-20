// MySQL Datenbank Tabellen für die Plugins

CREATE TABLE IF NOT EXISTS `floods` (
  `added` int(10) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `cons` int(3) NOT NULL DEFAULT '1',
  `banned` enum('yes','no') NOT NULL DEFAULT 'no'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `agents` (
  `agent_id` int(10) unsigned NOT NULL auto_increment,
  `agent_name` varchar(255) NOT NULL default '',
  `hits` int(10) unsigned NOT NULL default '0',
  `ins_date` int(10) unsigned NOT NULL default '0',
  `aktiv` tinyint(1) NOT NULL default '1',
  PRIMARY KEY (`agent_id`),
  UNIQUE KEY `agent_name` (`agent_name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;