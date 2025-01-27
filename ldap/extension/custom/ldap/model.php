<?php
/**
 * The model file of ldap module of ZenTaoPMS.
 *
 * @license     ZPL (http://zpl.pub/page/zplv11.html)
 * @author      TigerLau
 * @package     ldap
 * @link        http://www.zentao.net
 */
?>
<?php
class ldapModel extends model
{
    public function identify($host, $dn, $pwd)
    {
        $ret = '';
        $ds = ldap_connect($host);
        if ($ds) {
            ldap_set_option($ds,LDAP_OPT_PROTOCOL_VERSION,3);
            ldap_bind($ds, $dn, $pwd);

            $ret = ldap_error($ds);
            ldap_close($ds);
        }  else {
            $ret = ldap_error($ds);
        }

        return $ret;
    }
    public function getUserDn($config, $account){
        $ret = null;
        $ds = ldap_connect($config->host);
        if ($ds) {
            ldap_set_option($ds,LDAP_OPT_PROTOCOL_VERSION,3);
            ldap_bind($ds, $config->bindDN, $config->bindPWD);
            $filter = "($config->uid=$account)";
            $rlt = ldap_search($ds, $config->baseDN, $filter);
            $count=ldap_count_entries($ds, $rlt);

            if($count > 0){
                $data = ldap_get_entries($ds, $rlt);
                $ret = $data[0]['dn'];
                $str = serialize($data);
            }

            ldap_unbind($ds);
            ldap_close($ds);
        }
        return $ret;
    }
    public function getUsers($config)
    {
        $ds = ldap_connect($config->host);
        if ($ds) {
            ldap_set_option($ds,LDAP_OPT_PROTOCOL_VERSION,3);
            ldap_bind($ds, $config->bindDN, $config->bindPWD);

            $attrs = [$config->uid, $config->mail, $config->name];

            $rlt = ldap_search($ds, $config->baseDN, $config->searchFilter, $attrs);
            $data = ldap_get_entries($ds, $rlt);
            return $data;
        }

        return null;
    }

    /**
     * sync2db
     * @param $accountOne 仅同步一个指定用户
     */
    public function sync2db($config, $accountOne = '')
    {
        $ldapUsers = $this->getUsers($config);
        $user = new stdclass();
        $group = new stdClass(); // 保存同步 LDAP 数据设置的默认权限分组信息
        $i=0;
        for (; $i < $ldapUsers['count']; $i++) {
            $user->account = $ldapUsers[$i][$config->uid][0];
            $user->email = $ldapUsers[$i][$config->mail][0];
            $user->realname = $ldapUsers[$i][$config->name][0];

            $group->account = $ldapUsers[$i][$config->uid][0];
            $group->group = (!empty($config->group) ? $config->group : $this->config->ldap->group); //由于默认权限分组标识不在 LDAP 内存储，所以直接从 config 中拿。为了兼容 zentao 自带定时任务所以用了三目运算符
            // 不是指定要同步的用户，则跳过
            if (!empty($accountOne) && $accountOne != $user->account) continue;
            $account = $this->dao->select('*')->from(TABLE_USER)->where('account')->eq($user->account)->fetch('account');
            if ($account == $user->account) {
                $this->dao->update(TABLE_USER)->data($user)->where('account')->eq($user->account)->autoCheck()->exec();
            } else {
                $this->dao->insert(TABLE_USER)->data($user)->exec();
                $this->dao->insert(TABLE_USERGROUP)->data($group)->exec();
            }
            // 指定用户同步完成后退出循环
            if (!empty($accountOne)) break;

            if(dao::isError())
            {
                echo js::error(dao::getError());
                die(js::reload('parent'));
            }
        }

        return $i;
    }
}
