<?php
public function identify($account, $password)
{
  $user = false;
  $user = parent::identify($account, $password);
  if ($user) {
    return $user;
  }
  $ldap = $this->loadModel('ldap');
  $ldapUser = $ldap->getUser($this->config->ldap, $account);
  if ($ldapUser) {
    $pass = $ldap->identify($this->config->ldap->host, $ldapUser['dn'], $password);
    if ($pass) {
      $ip   = $this->server->remote_addr;
      $last = $this->server->request_time;
      $record = $this->dao->select('*')->from(TABLE_USER)
            ->where('account')->eq($account)
            ->andWhere('deleted')->eq(0)
            ->fetch();
      if ($record) {
        $user = $record;
      } else {
        $user = new stdclass();
        $group = new stdClass(); // 保存同步ldap数据设置的默认权限分组信息
        $user->account = $account;
        $user->email = $ldapUser[$this->config->ldap->mail][0];
        $user->realname = $ldapUser[$this->config->ldap->name][0];
        $user->visits = 1;
        $user->ip = $ip;
        $user->last = $last;
        $group->account = $ldapUser[$this->config->ldap->uid][0];
        $group->group = (!empty($this->config->ldap->group) ? $this->config->ldap->group : ''); //由于默认权限分组标识不在ldap内存储，所以直接从config中拿。为了兼容zentao自带定时任务所以用了三目运算符
        $this->dao->insert(TABLE_USER)->data($user)->exec();
        $this->dao->insert(TABLE_USERGROUP)->data($group)->exec();
      }
      // 禅道有多处地方需要二次验证密码, 所以需要保存密码的MD5在session中以供后续验证
      $user->password = md5($password);
      // 判断用户是否来自ldap
      $user->fromldap = true;
  
       /* code for bug #2729. */
       if(defined('IN_USE')) $this->dao->update(TABLE_USER)->set('visits = visits + 1')->set('ip')->eq($ip)->set('last')->eq($last)->where('account')->eq($account)->exec();

       /* Create cycle todo in login. */
       $todoList = $this->dao->select('*')->from(TABLE_TODO)->where('cycle')->eq(1)->andWhere('deleted')->eq('0')->andWhere('account')->eq($user->account)->fetchAll('id');
       $this->loadModel('todo')->createByCycle($todoList);
    }
  }
  
  return $user;
}
