<?php
global $table_prefix;

$create_sms_subscribes = ( "CREATE TABLE IF NOT EXISTS {$table_prefix}sms_subscribes(
	ID int(10) NOT NULL auto_increment,
	date DATETIME,
	name VARCHAR(20),
	mobile VARCHAR(20) NOT NULL,
	status tinyint(1),
	activate_key INT(11),
	group_ID int(5),
	PRIMARY KEY(ID)) CHARSET=utf8
" );

$create_sms_subscribes_group = ( "CREATE TABLE IF NOT EXISTS {$table_prefix}sms_subscribes_group(
	ID int(10) NOT NULL auto_increment,
	name VARCHAR(250),
	PRIMARY KEY(ID)) CHARSET=utf8
" );

$create_sms_send = ( "CREATE TABLE IF NOT EXISTS {$table_prefix}sms_send(
	ID int(10) NOT NULL auto_increment,
	date DATETIME,
	sender VARCHAR(20) NOT NULL,
	message TEXT NOT NULL,
	recipient TEXT NOT NULL,
	PRIMARY KEY(ID)) CHARSET=utf8
" );