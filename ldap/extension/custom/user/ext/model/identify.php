<?php
public function identify($account, $password) 
{
  if (!$account or !$password) {
    return false;
  }

  /* Get the user first */
  $record = $this->dao->select('*')->from(TABLE_USER)
      ->where('account')->eq($account)
      ->andWhere('deleted')->eq(0)
      ->fetch();

  $user = false;
  if ($record and empty($record->ldap)) {
    $passwordLength = strlen($password);
    if ($passwordLength == 32) {
        $hash = $this->session->rand ? md5($record->password . $this->session->rand) : $record->password;
        $user = $password == $hash ? $record : '';
    }
    elseif ($passwordLength == 40) {
        $hash = sha1($record->account . $record->password . $record->last);
        $user = $password == $hash ? $record : '';
    }
    if (!$user and md5($password) == $record->password) {
      $user = $record;
    }
  } else {
    $ldap = $this->loadModel('ldap');
    $ldapUser = $ldap->getUser($this->config->ldap, $account);
    if ($ldapUser) {
      $pass = $ldap->identify($this->config->ldap->host, $ldapUser['dn'], $password);
      if ($pass) {
        if ($record) {
          $user = $record;
        } else {
          $user = new stdclass();
          $group = new stdClass(); // 保存同步ldap数据设置的默认权限分组信息
          $user->account = $account;
          $user->ldap = $account;
          $user->email = $ldapUser[$this->config->ldap->mail][0];
          $user->realname = $ldapUser[$this->config->ldap->name][0];
          $group->account = $ldapUser[$this->config->ldap->uid][0];
          // 由于默认权限分组标识不在ldap内存储，所以直接从config中拿。为了兼容zentao自带定时任务所以用了三目运算符
          $group->group = (!empty($this->config->ldap->group) ? $this->config->ldap->group : ''); 
          $this->dao->insert(TABLE_USER)->data($user)->exec();
          $this->dao->insert(TABLE_USERGROUP)->data($group)->exec();
        }
        // 禅道有多处地方需要二次验证密码, 所以需要保存密码的MD5在session中以供后续验证
        $user->password = md5($password);
        // 判断用户是否来自ldap
        $user->fromldap = true;
      }
    }
  }

  if ($user) {
    $ip = $this->server->remote_addr;
    $last = $this->server->request_time;
    $user->lastTime = $user->last;
    $user->last = date(DT_DATETIME1, $last);
    $user->admin = strpos($this->app->company->admins, ",{$user->account},") !== false;
    if ($user->fromldap) {
      $user->modifyPassword = false;
    } else {
      $user->modifyPassword = ($user->visits == 0 and !empty($this->config->safe->modifyPasswordFirstLogin));
    }
    if ($user->modifyPassword) {
      $user->modifyPasswordReason = 'modifyPasswordFirstLogin';
    }
    if (!$user->fromldap and !$user->modifyPassword and !empty($this->config->safe->changeWeak)) {
        $user->modifyPassword = $this->loadModel('admin')->checkWeak($user);
        if ($user->modifyPassword) {
          $user->modifyPasswordReason = 'weak';
        }
    }
    /* code for bug #2729. */
    if (defined('IN_USE')) {
      $this->dao->update(TABLE_USER)
          ->set('visits = visits + 1')
          ->set('ip')->eq($ip)
          ->set('last')->eq($last)
          ->where('account')->eq($account)
          ->exec();
    } 

    /* Create cycle todo in login. */
    $todoList = $this->dao->select('*')->from(TABLE_TODO)
        ->where('cycle')->eq(1)
        ->andWhere('deleted')->eq('0')
        ->andWhere('account')->eq($user->account)
        ->fetchAll('id');
    $this->loadModel('todo')->createByCycle($todoList);
  }
  
  return $user;
}
