#
# Table structure for table 'tt_news'
#
CREATE TABLE tx_weeaargooglesitemap (
  id int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  disabled tinyint(3) unsigned DEFAULT '0' NOT NULL,
	priority varchar(30) DEFAULT '0.5' NOT NULL,
	changefreq varchar(30) DEFAULT '' NOT NULL,

  PRIMARY KEY (id),
  KEY parent (pid),
);

