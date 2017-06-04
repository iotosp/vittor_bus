<?php
pdo_query(
	"CREATE TABLE IF NOT EXISTS ".tablename('bus_info')." (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `weid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '公司ID',
	  `bdkey` varchar(200) NOT NULL DEFAULT '' COMMENT '管理员openid',
	  `map_amap_server_key` varchar(200) DEFAULT NULL COMMENT '高德地图Web服务端密钥',
	  `map_amap_web_key` varchar(200) DEFAULT NULL COMMENT '高德地图Web前端密钥',
	  `map_baidu_server_key` varchar(200) DEFAULT NULL COMMENT '百度地图Web服务端密钥',
	  PRIMARY KEY (`id`),
	  UNIQUE KEY `weid` (`weid`)
	) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COMMENT='管理员信息表';"
);