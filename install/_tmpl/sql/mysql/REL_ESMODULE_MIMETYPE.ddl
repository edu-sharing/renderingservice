CREATE TABLE `REL_ESMODULE_MIMETYPE` (
  `REL_ESMODULE_MIMETYPE_ID` int(11) NOT NULL auto_increment,
  `REL_ESMODULE_MIMETYPE_ESMODULE_ID` int(11) default NULL,
  `REL_ESMODULE_MIMETYPE_TYPE` varchar(200) default NULL,
  PRIMARY KEY  (`REL_ESMODULE_MIMETYPE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8