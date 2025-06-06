**QQ讨论群：237626046**

**技术支持：+微信【Cheng-ShaoYong】**

## 一、功能介绍

## 二、准备安装环境与账户
* 一台Linux服务器，4核8G（本地安装mysql建议16G内存），宽带大于5mb
* 安装Nginx 1.26
* 安装PHP 8.2
* 安装Mysql 8.0
* 安装Redis 7.4
* 安装Mosquitto 2.0
* 准备好5个二级域名，并解析到服务器的IP
* 注册认证微信小程序与为微信公众号
* 注册认证微信开放平台，并将小程序与公众号关联
* 注册认证支付宝小程序（可选）
* 申请企业邮箱SMTP服务
* 注册并认证臻识云平台账户
* 开通阿里云OSS服务
* 开通腾讯云Orc图像识别，车辆识别增强版

## 三、系统安装
### 安装服务端
#### 1、下载服务端源码，https://gitee.com/lcfms/yunqi-smart-parking---server
#### 2、安装数据库和视图，文件所在目录：/storage/sql
#### 3、安装php扩展，fileinfo，redis，Swoole6
#### 4、打开/config/app.php，完成域名配置
```
// 域名绑定（自动多应用模式有效）
'domain_bind'      => [
    //系统管理端web端域名
    'admin.test.com'=>'admin',
    //停车场管理web端域名
    'parking.test.com'=>'parking',
    //岗亭web端域名
    'screen.test.com'=>'screen',
    //api域名
    'api.test.com'=>'api',
    //官网域名
    'www.test.com'=>'index'
],
```
#### 5、配置Nginx，除了screen.test.com，让访问的域名都支持SSL
#### 6、配置Mysql，拷贝.example.env的内容生成.env，修改配置信息
#### 7、访问系统管理端https://admin.test.com/index，默认的用户名：admin，密码：admin123
#### 8、完善系统配置
<img src="./storage/images/1.png" style="width: 70%;">

### 小程序打包（停车场移动管理端，商户移动管理端，车主端）
### H5打包（商户PC管理端）
### 岗亭打包（岗亭PC端）
