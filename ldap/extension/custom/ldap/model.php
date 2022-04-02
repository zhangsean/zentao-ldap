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
    public function identify($host, $dn, $password)
    {
        $ret = false;
        $ds = ldap_connect($host) or die("Could not connect to LDAP server.");
        if ($ds) {
            ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
            $ret = ldap_bind($ds, $dn, $password);
            ldap_unbind($ds);
        }

        return $ret;
    }
    public function getUserDn($config, $account)
    {
        $ret = false;
        $ldapUser = $this->getUser($config, $account);
        if ($ldapUser) {
            $ret = $ldapUser['dn'];
        }
        return $ret;
    }
    public function getUser($config, $account)
    {
        $ret = false;
        $ds = ldap_connect($config->host) or die("Could not connect to LDAP server.");
        if ($ds) {
            ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
            $bind = ldap_bind($ds, $config->bindDN, $config->bindPWD);
            if ($bind) {
                $filter = "($config->uid=$account)";
                if ($config->searchFilter) {
                    $filter = "(&" . $config->searchFilter . $filter. ")";
                }

                $rlt = ldap_search($ds, $config->baseDN, $filter);
                if ($rlt) {
                    $count = ldap_count_entries($ds, $rlt);
                    if ($count > 0) {
                        $entries = ldap_get_entries($ds, $rlt);
                        $ret = $entries[0];
                    }
                }
            }

            ldap_unbind($ds);
        }
        return $ret;
    }
    public function getUsers($config)
    {
        $ret = false;
        $ds = ldap_connect($config->host) or die("Could not connect to LDAP server.");
        if ($ds) {
            ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
            $bind = ldap_bind($ds, $config->bindDN, $config->bindPWD);
            if ($bind) {
                $attrs = [$config->uid, $config->mail, $config->name];
                $rlt = ldap_search($ds, $config->baseDN, $config->searchFilter, $attrs);
                if ($rlt) {
                    $ret = ldap_get_entries($ds, $rlt);
                }
            }
            ldap_unbind($ds);
        }

        return $ret;
    }

    public function sync2db($config)
    {
        $ldapUsers = $this->getUsers($config);
        $i = 0;
        if ($ldapUsers) {
            $user = new stdclass();
            $group = new stdClass(); // 保存同步ldap数据设置的默认权限分组信息
            $account = '';
            for (; $i < $ldapUsers['count']; $i++) {
                $user->account = $ldapUsers[$i][$config->uid][0];
                $user->email = $ldapUsers[$i][$config->mail][0];
                $user->realname = $ldapUsers[$i][$config->name][0];

                $group->account = $ldapUsers[$i][$config->uid][0];
                $group->group = (!empty($config->group) ? $config->group : $this->config->ldap->group); //由于默认权限分组标识不在ldap内存储，所以直接从config中拿。为了兼容zentao自带定时任务所以用了三目运算符
                $account = $this->dao->select('*')->from(TABLE_USER)->where('account')->eq($user->account)->fetch('account');
                if ($account == $user->account) {
                    $this->dao->update(TABLE_USER)->data($user)->where('account')->eq($user->account)->autoCheck()->exec();
                } else {
                    $this->dao->insert(TABLE_USER)->data($user)->exec();
                    $this->dao->insert(TABLE_USERGROUP)->data($group)->exec();
                }

                if (dao::isError()) {
                    echo js::error(dao::getError());
                    die(js::reload('parent'));
                }
            }
        }
        return $i;
    }
}
