<?php
/**
 * The user module zh-cn file of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv11.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     user
 * @version     $Id: zh-cn.php 5053 2013-07-06 08:17:37Z wyd621@gmail.com $
 * @link        http://www.zentao.net
 */

$lang->ldap->common 		= "LDAP";
$lang->ldap->setting    	= "设置";
$lang->ldap->host 			= 'LDAP服务器:';
$lang->ldap->version 		= '协议版本:';
$lang->ldap->bindDN 		= 'BindDN:';
$lang->ldap->password 		= 'BindDN密码:';
$lang->ldap->baseDN 		= 'BaseDN:';
$lang->ldap->filter 		= '搜索过滤器:';
$lang->ldap->attributes 	= '账号字段:';
$lang->ldap->sync 			= '手动同步';
$lang->ldap->save 			= '保存设置';
$lang->ldap->test 			= '测试连接';
$lang->ldap->mail 			= '邮箱字段:';
$lang->ldap->name  			= '姓名字段:';
$lang->ldap->group  			= '默认权限:';

$lang->ldap->placeholder->group 	= '为从 LDAP 同步过来的用户添加一个默认权限';

$lang->ldap->methodOrder[5] = 'index';
$lang->ldap->methodOrder[10] = 'setting';
