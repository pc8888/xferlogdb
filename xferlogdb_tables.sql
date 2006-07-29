CREATE TABLE log (
   id int(11) NOT NULL auto_increment,
   time timestamp(14),
   transfertime int(11),
   host varchar(255),
   bytes bigint(20),
   file varchar(255),
   mode tinyint(4),
   direction tinyint(4),
   user varchar(255),
   pgroup varchar(255),
   PRIMARY KEY (id),
   KEY user (user)
);


CREATE TABLE stats (
   id int(11) NOT NULL auto_increment,
   lastupdate datetime,
   numlines int(11),
   PRIMARY KEY (id)
);


CREATE TABLE online (
   id int(11) NOT NULL auto_increment,
   time timestamp(14),
   type int(11),
   host varchar(255),
   user varchar(50),
   PRIMARY KEY (id)
);
