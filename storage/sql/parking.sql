SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for yun_parking
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking`;
CREATE TABLE `yun_parking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pid` int DEFAULT NULL,
  `title` varchar(50) DEFAULT NULL,
  `property_id` int DEFAULT NULL,
  `province_id` int DEFAULT NULL,
  `city_id` int DEFAULT NULL,
  `area_id` int DEFAULT NULL,
  `plate_begin` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `longitude` varchar(30) DEFAULT NULL,
  `latitude` varchar(30) DEFAULT NULL,
  `contact` varchar(30) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `uniqid` varchar(50) DEFAULT NULL,
  `pay_type_handle` varchar(30) DEFAULT NULL,
  `sub_merch_no` varchar(30) DEFAULT NULL,
  `sub_merch_key` varchar(50) DEFAULT NULL,
  `split_merch_no` varchar(30) DEFAULT NULL,
  `etc_able` tinyint DEFAULT '0',
  `etc_appid` varchar(30) DEFAULT NULL,
  `parking_records_persent` varchar(10) DEFAULT NULL,
  `parking_recharge_persent` varchar(10) DEFAULT NULL,
  `parking_merch_persent` varchar(10) DEFAULT NULL,
  `inside` tinyint DEFAULT '0',
  `status` varchar(20) DEFAULT 'normal',
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int unsigned DEFAULT NULL,
  `deletetime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uniqid` (`uniqid`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_admin
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_admin`;
CREATE TABLE `yun_parking_admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `role` varchar(30) DEFAULT NULL,
  `rules` varchar(255) DEFAULT NULL,
  `auth_rules` varchar(255) DEFAULT NULL,
  `mobile_rules` varchar(1200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_barrier
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_barrier`;
CREATE TABLE `yun_parking_barrier` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pid` int DEFAULT '0',
  `parking_id` int DEFAULT NULL,
  `title` varchar(30) DEFAULT NULL,
  `barrier_type` varchar(30) DEFAULT NULL,
  `trigger_type` varchar(30) DEFAULT 'outfield',
  `serialno` varchar(50) DEFAULT NULL,
  `virtual_serialno` varchar(50) DEFAULT NULL,
  `plate_type` varchar(255) DEFAULT NULL,
  `rules_type` varchar(255) DEFAULT NULL,
  `rules_id` varchar(255) DEFAULT NULL,
  `camera` varchar(30) DEFAULT NULL,
  `limit_pay_time` int DEFAULT '300',
  `screen_time` int DEFAULT NULL,
  `local_ip` varchar(30) DEFAULT NULL,
  `show_last_space` varchar(255) DEFAULT NULL,
  `tjtc` varchar(255) DEFAULT NULL COMMENT '同进同出',
  `manual_confirm` tinyint DEFAULT '0' COMMENT '人工确认',
  `manual_confirm_time` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'all_time',
  `screen_support` varchar(30) DEFAULT NULL,
  `voice_support` varchar(30) DEFAULT NULL,
  `monthly_voice` varchar(30) DEFAULT NULL,
  `monthly_voice_day` int DEFAULT NULL,
  `monthly_screen` varchar(30) DEFAULT NULL,
  `monthly_screen_day` int DEFAULT NULL,
  `status` varchar(30) DEFAULT 'normal',
  `createtime` int DEFAULT NULL,
  `updatetime` int DEFAULT NULL,
  `deletetime` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `serialno` (`serialno`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_barrier_tjtc
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_barrier_tjtc`;
CREATE TABLE `yun_parking_barrier_tjtc` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `serialno` varchar(255) DEFAULT NULL,
  `times` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_black
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_black`;
CREATE TABLE `yun_parking_black` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_cars
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_cars`;
CREATE TABLE `yun_parking_cars` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `rules_type` varchar(30) DEFAULT 'monthly',
  `rules_id` int DEFAULT NULL,
  `plates_count` int DEFAULT '1' COMMENT '车辆数',
  `occupat_number` int DEFAULT '1' COMMENT '占用车位数',
  `third_id` int DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `contact` varchar(30) DEFAULT NULL COMMENT '联系人',
  `mobile` varchar(30) DEFAULT NULL COMMENT '手机号',
  `starttime` int unsigned DEFAULT NULL COMMENT '开始日期',
  `endtime` int unsigned DEFAULT NULL COMMENT '结束日期',
  `remark` varchar(255) DEFAULT NULL,
  `remark_line` varchar(255) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT '0.00' COMMENT '储值卡余额',
  `insufficient_balance` varchar(30) DEFAULT 'cutbalance' COMMENT '余额不足如何处理',
  `synch` varchar(30) DEFAULT NULL COMMENT '离线同步',
  `status` varchar(30) DEFAULT 'normal',
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int unsigned DEFAULT NULL,
  `deletetime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5838 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_cars_apply
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_cars_apply`;
CREATE TABLE `yun_parking_cars_apply` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `apply_type` varchar(30) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `cars_id` int DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `plate_type` varchar(30) DEFAULT NULL,
  `car_models` varchar(30) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `contact` varchar(30) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `rules_type` varchar(30) DEFAULT NULL,
  `rules_id` int DEFAULT NULL,
  `pay_id` int DEFAULT NULL,
  `merch_id` int DEFAULT NULL,
  `status` tinyint DEFAULT '0',
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int DEFAULT NULL,
  `deletetime` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `yun_parking_contactless`;
CREATE TABLE `yun_parking_contactless` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `property_id` int DEFAULT NULL,
  `parking_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '停车场标识',
  `handle` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `money_limit` int DEFAULT NULL COMMENT '无感支付签约额度 ，单位分',
  `records_id` int DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `records_id` (`records_id`)
) ENGINE=InnoDB AUTO_INCREMENT=677 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for yun_parking_cars_logs
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_cars_logs`;
CREATE TABLE `yun_parking_cars_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `cars_id` int DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `merch_id` int DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_cars_occupat
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_cars_occupat`;
CREATE TABLE `yun_parking_cars_occupat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `cars_id` int DEFAULT NULL,
  `code` smallint unsigned DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `entry_time` int unsigned DEFAULT NULL,
  `exit_time` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `records_id` (`records_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_cars_stop
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_cars_stop`;
CREATE TABLE `yun_parking_cars_stop` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `cars_id` int DEFAULT NULL,
  `change_type` varchar(10) DEFAULT NULL,
  `date` varchar(30) DEFAULT NULL,
  `status` tinyint DEFAULT '0',
  `createtime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_charge
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_charge`;
CREATE TABLE `yun_parking_charge` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `merch_id` int DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `channel` varchar(30) DEFAULT NULL,
  `trigger` varchar(30) DEFAULT NULL,
  `rules_value` varchar(255) DEFAULT NULL,
  `use_diy_rules` tinyint DEFAULT '0',
  `rules_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_charge_list
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_charge_list`;
CREATE TABLE `yun_parking_charge_list` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `fee` decimal(10,2) DEFAULT NULL,
  `kwh` decimal(10,2) DEFAULT NULL,
  `time` int DEFAULT NULL,
  `rules_id` int DEFAULT NULL,
  `coupon_id` int DEFAULT NULL,
  `coupon_list_id` int DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1190 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_daily_cash_flow
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_daily_cash_flow`;
CREATE TABLE `yun_parking_daily_cash_flow` (
  `parking_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `total_income` decimal(11,2) DEFAULT NULL,
  `parking_income` decimal(11,2) DEFAULT NULL,
  `parking_recovery` decimal(11,2) DEFAULT NULL,
  `parking_monthly_income` decimal(11,2) DEFAULT NULL,
  `parking_stored_income` decimal(11,2) DEFAULT NULL,
  `merch_recharge_income` decimal(11,2) DEFAULT NULL,
  `handling_fees` decimal(11,2) DEFAULT NULL,
  `total_refund` decimal(11,2) DEFAULT NULL,
  `net_income` decimal(11,2) DEFAULT NULL,
  KEY `parking_id` (`parking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_download
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_download`;
CREATE TABLE `yun_parking_download` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `filetype` varchar(10) DEFAULT NULL,
  `datestr` varchar(255) DEFAULT NULL,
  `createtime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_exception
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_exception`;
CREATE TABLE `yun_parking_exception` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `rules_type` varchar(30) DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `plate_type` varchar(30) DEFAULT NULL,
  `barrier` varchar(30) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `createtime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_holiday
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_holiday`;
CREATE TABLE `yun_parking_holiday` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `remark` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_infield
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_infield`;
CREATE TABLE `yun_parking_infield` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `title` varchar(30) DEFAULT NULL,
  `entry_barrier` varchar(255) DEFAULT NULL,
  `exit_barrier` varchar(255) DEFAULT NULL,
  `rules` varchar(30) DEFAULT NULL,
  `mode` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_infield_records
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_infield_records`;
CREATE TABLE `yun_parking_infield_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `infield_id` int DEFAULT NULL,
  `entry_barrier` int DEFAULT NULL,
  `exit_barrier` int DEFAULT NULL,
  `entry_time` int unsigned DEFAULT NULL,
  `exit_time` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parking_id` (`parking_id`),
  KEY `records_id` (`records_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_invoice
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_invoice`;
CREATE TABLE `yun_parking_invoice` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `pay_id` varchar(1200) DEFAULT NULL,
  `invoice_send` varchar(30) DEFAULT NULL,
  `invoice_type` varchar(30) DEFAULT NULL,
  `title` varchar(50) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `code` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `bank` varchar(30) DEFAULT NULL,
  `bank_account` varchar(30) DEFAULT NULL,
  `email` varchar(30) DEFAULT NULL,
  `mobile` char(11) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `error` varchar(255) DEFAULT NULL,
  `file` varchar(1255) DEFAULT NULL,
  `status` tinyint DEFAULT '0',
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int unsigned DEFAULT NULL,
  `deletetime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=554 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_log
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_log`;
CREATE TABLE `yun_parking_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `type` varchar(30) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `manual` tinyint DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB AUTO_INCREMENT=76801 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_manual_open
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_manual_open`;
CREATE TABLE `yun_parking_manual_open` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `barrier_id` int DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `openuser` varchar(50) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `is_checked` tinyint DEFAULT '0' COMMENT '检查该开闸后是否有出闸记录',
  `createtime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=449 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_merchant
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_merchant`;
CREATE TABLE `yun_parking_merchant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `merch_name` varchar(50) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `coupon` varchar(255) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `salt` varchar(30) DEFAULT NULL,
  `password` varchar(50) DEFAULT NULL,
  `settle_type` varchar(30) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `price_time` int DEFAULT NULL,
  `allow_arrears` int DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT '0.00',
  `is_self` tinyint DEFAULT '0',
  `static_able` tinyint DEFAULT '0',
  `static_expire` int DEFAULT NULL,
  `limit_send` tinyint DEFAULT '0',
  `limit_type` varchar(30) DEFAULT NULL,
  `limit_number` int DEFAULT NULL,
  `limit_money` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `online_recharge` tinyint DEFAULT '1',
  `day_shenhe` varchar(50) DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int unsigned DEFAULT NULL,
  `deletetime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_merchant_coupon
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_merchant_coupon`;
CREATE TABLE `yun_parking_merchant_coupon` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `title` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `coupon_type` varchar(30) DEFAULT NULL,
  `time` int DEFAULT NULL,
  `cash` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `period` int DEFAULT NULL,
  `timespan` varchar(255) DEFAULT NULL,
  `timespan_time` int DEFAULT NULL,
  `timespan_discount` decimal(10,2) DEFAULT NULL,
  `discount_time` int DEFAULT NULL,
  `before_entry` varchar(30) DEFAULT 'refuse' COMMENT '是否允许进场前领券',
  `limit_one` tinyint DEFAULT '1',
  `multiple_use` tinyint DEFAULT '0',
  `effective` int DEFAULT '30' COMMENT '有效时长',
  `subscribe_mpapp` tinyint DEFAULT '1',
  `weigh` int DEFAULT NULL,
  `status` varchar(30) DEFAULT 'normal',
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int unsigned DEFAULT NULL,
  `deletetime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_merchant_coupon_list
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_merchant_coupon_list`;
CREATE TABLE `yun_parking_merchant_coupon_list` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `merch_id` int DEFAULT NULL,
  `merch_title` varchar(50) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `coupon_id` int DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `status` tinyint DEFAULT '0',
  `starttime` int unsigned DEFAULT NULL COMMENT '时效券入场时间',
  `expiretime` int unsigned DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int unsigned DEFAULT NULL,
  `deletetime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parking_id` (`parking_id`),
  KEY `plate_number` (`plate_number`),
  KEY `plate` (`parking_id`,`plate_number`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=1282 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_merchant_log
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_merchant_log`;
CREATE TABLE `yun_parking_merchant_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `merch_id` int DEFAULT NULL,
  `log_type` varchar(30) DEFAULT NULL,
  `change` decimal(10,2) DEFAULT NULL,
  `before` decimal(10,2) DEFAULT NULL,
  `after` decimal(10,2) DEFAULT NULL,
  `pay_id` int DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pay_id` (`pay_id`) USING BTREE,
  KEY `parking_id` (`parking_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1003 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_merchant_setting
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_merchant_setting`;
CREATE TABLE `yun_parking_merchant_setting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `merch_id` int DEFAULT NULL,
  `coupon_id` int DEFAULT NULL,
  `coupon_title` varchar(50) DEFAULT NULL,
  `limit_send` tinyint DEFAULT '0',
  `limit_type` varchar(30) DEFAULT NULL,
  `limit_number` int DEFAULT NULL,
  `limit_money` int DEFAULT NULL,
  `limit_time` int DEFAULT NULL,
  `limit_instock` int DEFAULT NULL,
  `settle_type` varchar(30) DEFAULT 'normal',
  `settle_money` decimal(10,2) DEFAULT NULL,
  `settle_time` int DEFAULT NULL,
  `settle_max` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coupon` (`parking_id`,`merch_id`,`coupon_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_merchant_user
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_merchant_user`;
CREATE TABLE `yun_parking_merchant_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `merch_id` int DEFAULT NULL,
  `third_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `third_id` (`third_id`),
  KEY `merch_id` (`merch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_mode
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_mode`;
CREATE TABLE `yun_parking_mode` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int NOT NULL,
  `title` varchar(50) NOT NULL,
  `fee_setting` varchar(30) NOT NULL DEFAULT 'normal',
  `diy_class` varchar(255) DEFAULT NULL COMMENT '定制收费',
  `free_time` int DEFAULT NULL COMMENT '免费时长',
  `start_fee` varchar(255) DEFAULT NULL COMMENT '起步收费',
  `period_fee` varchar(1200) DEFAULT NULL,
  `step_fee` varchar(1200) DEFAULT NULL,
  `add_time` int DEFAULT NULL,
  `add_fee` decimal(10,2) DEFAULT NULL,
  `top_time` int DEFAULT NULL COMMENT '封顶时长',
  `top_fee` decimal(10,2) DEFAULT NULL COMMENT '封顶收费',
  `day_top_fee` decimal(10,2) DEFAULT NULL,
  `plate_type` varchar(255) DEFAULT NULL COMMENT '支持车牌',
  `time_setting` varchar(30) DEFAULT 'all',
  `time_setting_rules` varchar(1200) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'normal',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_monthly_recharge
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_monthly_recharge`;
CREATE TABLE `yun_parking_monthly_recharge` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `cars_id` int DEFAULT NULL,
  `rules_id` int DEFAULT NULL,
  `money` decimal(10,2) DEFAULT NULL,
  `starttime` int unsigned DEFAULT NULL,
  `endtime` int unsigned DEFAULT NULL,
  `pay_id` int DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parking_id` (`parking_id`) USING BTREE,
  KEY `pay_id` (`pay_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_plate
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_plate`;
CREATE TABLE `yun_parking_plate` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `cars_id` int DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `plate_type` varchar(30) DEFAULT 'blue',
  `car_models` varchar(30) DEFAULT 'small',
  PRIMARY KEY (`id`),
  KEY `car` (`parking_id`,`cars_id`),
  KEY `plate_number` (`plate_number`,`parking_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5945 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_qrcode
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_qrcode`;
CREATE TABLE `yun_parking_qrcode` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `name` varchar(30) DEFAULT NULL,
  `background` varchar(255) DEFAULT NULL,
  `size` int DEFAULT NULL,
  `left` int DEFAULT NULL,
  `top` int DEFAULT NULL,
  `text` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_records
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_records`;
CREATE TABLE `yun_parking_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `parking_title` varchar(255) DEFAULT NULL,
  `rules_type` varchar(30) DEFAULT NULL,
  `rules_id` int DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `plate_type` varchar(30) DEFAULT '',
  `special` varchar(30) DEFAULT '',
  `entry_type` varchar(30) DEFAULT NULL,
  `entry_barrier` varchar(255) DEFAULT NULL,
  `entry_time` int unsigned DEFAULT NULL,
  `entry_photo` varchar(255) DEFAULT NULL,
  `exit_type` varchar(255) DEFAULT NULL,
  `exit_barrier` varchar(255) DEFAULT NULL,
  `exit_time` int unsigned DEFAULT NULL,
  `exit_photo` varchar(255) DEFAULT NULL,
  `account_time` int DEFAULT NULL,
  `total_fee` decimal(10,2) DEFAULT '0.00',
  `activities_fee` decimal(10,2) DEFAULT '0.00',
  `activities_time` int DEFAULT NULL,
  `pay_fee` decimal(10,2) DEFAULT '0.00',
  `infield_diy` tinyint DEFAULT '0' COMMENT '内场自定义收费',
  `cars_id` int DEFAULT NULL,
  `status` tinyint DEFAULT '0' COMMENT '0为进场，1为缴费未出场，2为异常进场，3为缴费出场，4为免费出场，5为手动出场，6为未缴费出场',
  `remark` varchar(255) DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int unsigned DEFAULT NULL,
  `deletetime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_time` (`entry_time`),
  KEY `exit_time` (`exit_time`),
  KEY `plate` (`parking_id`,`plate_number`),
  KEY `barrier1` (`entry_barrier`),
  KEY `barrier2` (`exit_barrier`),
  KEY `status` (`status`),
  KEY `parking_id` (`parking_id`),
  KEY `plate_number` (`plate_number`),
  KEY `cars_id` (`cars_id`) USING BTREE,
  KEY `entry_parking` (`parking_id`,`entry_time`,`deletetime`) USING BTREE,
  KEY `exit_parking` (`parking_id`,`exit_time`,`deletetime`),
  KEY `parking_status` (`parking_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=17392 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_records_coupon
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_records_coupon`;
CREATE TABLE `yun_parking_records_coupon` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `merch_id` int DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `coupon_list_id` int DEFAULT NULL,
  `coupon_type` varchar(30) DEFAULT NULL,
  `title` varchar(50) DEFAULT NULL,
  `time` int DEFAULT NULL,
  `cash` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `period` int DEFAULT NULL,
  `status` tinyint DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2268 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_records_detail
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_records_detail`;
CREATE TABLE `yun_parking_records_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `start_time` int unsigned DEFAULT NULL,
  `end_time` int unsigned DEFAULT NULL,
  `fee` decimal(10,2) DEFAULT NULL,
  `mode` varchar(30) DEFAULT NULL,
  `error` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `records` (`parking_id`,`records_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2424 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_records_filter
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_records_filter`;
CREATE TABLE `yun_parking_records_filter` (
  `parking_id` int DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `pay_id` int DEFAULT NULL,
  `records_pay_id` int DEFAULT NULL,
  `pay_price` decimal(10,2) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  `status` tinyint DEFAULT '0',
  KEY `pay_id` (`records_pay_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_records_pay
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_records_pay`;
CREATE TABLE `yun_parking_records_pay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `barrier_id` int DEFAULT NULL,
  `pay_id` int DEFAULT NULL,
  `total_fee` decimal(10,2) DEFAULT NULL,
  `activities_fee` decimal(10,2) DEFAULT NULL,
  `activities_time` int DEFAULT NULL,
  `pay_fee` decimal(10,2) DEFAULT NULL,
  `pay_price` decimal(10,2) DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `records_id` (`records_id`),
  KEY `parking_id` (`parking_id`),
  KEY `pay_id` (`pay_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1004 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_recovery
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_recovery`;
CREATE TABLE `yun_parking_recovery` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `recovery_type` varchar(30) DEFAULT NULL,
  `search_parking` varchar(255) DEFAULT NULL,
  `total_fee` decimal(10,2) DEFAULT NULL,
  `entry_set` tinyint DEFAULT NULL,
  `exit_set` tinyint DEFAULT NULL,
  `msg` tinyint DEFAULT NULL,
  `pay_id` int DEFAULT NULL,
  `entry_barrier` varchar(50) DEFAULT NULL,
  `entry_time` int unsigned DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plate_number` (`plate_number`),
  KEY `records_id` (`records_id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_recovery_auto
-- ----------------------------
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_recovery_auto`;
CREATE TABLE `yun_parking_recovery_auto` (
 `id` int NOT NULL AUTO_INCREMENT,
 `parking_id` int DEFAULT NULL,
 `recovery_type` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `entry_set` tinyint DEFAULT NULL,
 `exit_set` tinyint DEFAULT NULL,
 `msg` tinyint DEFAULT NULL,
 `status` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for yun_parking_sentrybox_operate
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_sentrybox_operate`;
CREATE TABLE `yun_parking_sentrybox_operate` (
 `id` int NOT NULL AUTO_INCREMENT,
 `parking_id` int DEFAULT NULL,
 `sentrybox_id` int DEFAULT NULL,
 `operator_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `operator_desc` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `starttime` datetime DEFAULT NULL,
 `endtime` datetime DEFAULT NULL,
 `entry` int DEFAULT NULL,
 `exit` int DEFAULT NULL,
 `online_fee` decimal(10,2) DEFAULT NULL,
 `underline_fee` decimal(10,2) DEFAULT NULL,
 `createtime` int unsigned DEFAULT NULL,
 `updatetime` int DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for yun_parking_rules
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_rules`;
CREATE TABLE `yun_parking_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `rules_type` varchar(30) DEFAULT 'monthly',
  `mode_id` int DEFAULT NULL,
  `title` varchar(50) DEFAULT NULL,
  `mode` varchar(255) DEFAULT NULL,
  `expire_day` int DEFAULT '0' COMMENT '过期宽限期',
  `fee` decimal(10,2) DEFAULT NULL,
  `min_month` tinyint DEFAULT NULL,
  `max_month` tinyint DEFAULT NULL,
  `online_apply` varchar(10) DEFAULT 'yes',
  `online_apply_days` int DEFAULT '100' COMMENT '申请日卡自动审核过期时间',
  `auto_online_apply` varchar(10) DEFAULT 'yes' COMMENT '审核在线申请',
  `online_apply_remark` varchar(255) DEFAULT NULL,
  `online_recharge` varchar(10) DEFAULT 'yes' COMMENT '是否支持在线续租',
  `min_renew` tinyint DEFAULT NULL,
  `max_renew` tinyint DEFAULT NULL,
  `renew_limit_day` tinyint DEFAULT '7' COMMENT '月租续费宽限期',
  `online_renew` varchar(10) DEFAULT 'yes',
  `online_renew_days` int DEFAULT '100' COMMENT '过期日卡续期自动审核过期时间',
  `auto_online_renew` varchar(10) DEFAULT 'yes' COMMENT '审核过期续费',
  `online_renew_remark` varchar(255) DEFAULT NULL,
  `remark_list` varchar(255) DEFAULT NULL,
  `gifts` varchar(255) DEFAULT NULL,
  `min_stored` decimal(10,2) DEFAULT NULL,
  `discount` decimal(4,2) DEFAULT NULL,
  `notice` tinyint DEFAULT '3' COMMENT '微信到期通知',
  `weigh` int DEFAULT NULL,
  `time_limit_entry` tinyint DEFAULT '0',
  `time_limit_setting` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'normal',
  PRIMARY KEY (`id`),
  KEY `parking_id` (`parking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_screen
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_screen`;
CREATE TABLE `yun_parking_screen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `barrier_id` int DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_sentrybox
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_sentrybox`;
CREATE TABLE `yun_parking_sentrybox` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `barriers` varchar(50) DEFAULT NULL,
  `uniqid` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `token` varchar(50) DEFAULT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `operator` varchar(255) DEFAULT NULL,
  `hide_window` tinyint DEFAULT '10',
  `open_set` varchar(30) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqid` (`uniqid`),
  KEY `token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_setting
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_setting`;
CREATE TABLE `yun_parking_setting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `provisional` tinyint DEFAULT '1',
  `monthly` tinyint DEFAULT '1',
  `day` tinyint DEFAULT '0',
  `member` tinyint DEFAULT '0',
  `stored` tinyint DEFAULT '0',
  `vip` tinyint DEFAULT '1',
  `provisional_space_full` tinyint DEFAULT '0',
  `monthly_space_full` tinyint DEFAULT '0',
  `day_space_full` tinyint DEFAULT '0',
  `member_space_full` tinyint DEFAULT '0',
  `stored_space_full` tinyint DEFAULT '0',
  `vip_space_full` tinyint DEFAULT '0',
  `provisional_no_entry` tinyint DEFAULT '0',
  `monthly_no_entry` tinyint DEFAULT '1',
  `day_no_entry` tinyint DEFAULT '0',
  `member_no_entry` tinyint DEFAULT '0',
  `stored_no_entry` tinyint DEFAULT '0',
  `vip_no_entry` tinyint DEFAULT '1',
  `provisional_match_last` tinyint DEFAULT '0',
  `monthly_match_last` tinyint DEFAULT '0',
  `day_match_last` tinyint DEFAULT '0',
  `member_match_last` tinyint DEFAULT '0',
  `stored_match_last` tinyint DEFAULT '0',
  `vip_match_last` tinyint DEFAULT '0',
  `parking_space_total` int DEFAULT '100',
  `autoupdate_space_total` tinyint DEFAULT '1',
  `one_entry` tinyint DEFAULT '0',
  `match_no_rule` tinyint DEFAULT '1' COMMENT '1为禁止入场，2为允许入场',
  `rules_txt` varchar(1200) DEFAULT NULL,
  `invoice_type` varchar(30) DEFAULT 'handle',
  `invoice_entity` varchar(255) DEFAULT NULL,
  `invoice_code` varchar(255) DEFAULT NULL,
  `invoice_persent` decimal(8,2) DEFAULT NULL,
  `special_free` varchar(255) DEFAULT NULL,
  `push_traffic` tinyint DEFAULT '0' COMMENT '推送到交管平台',
  `infield` varchar(255) DEFAULT NULL,
  `fuzzy_match` tinyint DEFAULT '0' COMMENT '模糊匹配',
  `temporary_check_cars` tinyint DEFAULT '1' COMMENT '无牌车检测车辆通行',
  `monthly_entry_tips` tinyint DEFAULT '39',
  `monthly_exit_tips` tinyint DEFAULT '2',
  `beian_pad` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parking_id` (`parking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_stored_log
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_stored_log`;
CREATE TABLE `yun_parking_stored_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `cars_id` int DEFAULT NULL,
  `log_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '变更余额',
  `before` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '变更前余额',
  `after` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '变更后余额',
  `pay_id` int DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '备注',
  `createtime` int DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `pay_id` (`pay_id`),
  KEY `record` (`records_id`),
  KEY `parking_id` (`parking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='储值卡余额变动表';

-- ----------------------------
-- Table structure for yun_parking_temporary
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_temporary`;
CREATE TABLE `yun_parking_temporary` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `openid` varchar(50) DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_traffic
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_traffic`;
CREATE TABLE `yun_parking_traffic` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `area` varchar(30) DEFAULT NULL,
  `filings_code` varchar(30) DEFAULT NULL COMMENT '备案编号',
  `total_parking_number` int DEFAULT '0' COMMENT '总车位',
  `remain_parking_number` int DEFAULT '0' COMMENT '空余车位',
  `open_parking_number` int DEFAULT '0' COMMENT '开放车位',
  `reserved_parking_number` int DEFAULT '0' COMMENT '保留车位',
  `rule_info` varchar(255) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'normal',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_traffic_records
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_traffic_records`;
CREATE TABLE `yun_parking_traffic_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `records_id` int DEFAULT NULL,
  `traffic_type` varchar(30) DEFAULT NULL,
  `pay_id` int DEFAULT NULL,
  `status` tinyint DEFAULT '0',
  `error` varchar(255) DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_trigger
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_trigger`;
CREATE TABLE `yun_parking_trigger` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `supplier` varchar(30) DEFAULT NULL,
  `serialno` varchar(50) DEFAULT NULL,
  `trigger_type` varchar(50) DEFAULT NULL,
  `plate_number` varchar(30) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `plate_type` varchar(30) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `file` varchar(255) DEFAULT NULL,
  `line` int DEFAULT NULL,
  `usetime` varchar(30) DEFAULT NULL,
  `createtime` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plate_number` (`serialno`,`plate_number`),
  KEY `parking_id` (`parking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37233 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for yun_parking_white
-- ----------------------------
DROP TABLE IF EXISTS `yun_parking_white`;
CREATE TABLE `yun_parking_white` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parking_id` int DEFAULT NULL,
  `rules_id` varchar(255) DEFAULT NULL,
  `day` int DEFAULT NULL,
  `time` tinyint DEFAULT NULL,
  `updatedate` date DEFAULT NULL,
  `createtime` int unsigned DEFAULT NULL,
  `updatetime` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
