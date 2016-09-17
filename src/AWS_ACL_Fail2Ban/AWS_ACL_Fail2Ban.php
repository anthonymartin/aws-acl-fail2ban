<?php
namespace AnthonyMartin\AWS_ACL_Fail2Ban;

class AWS_ACL_Fail2Ban {
  static $TARGET_ACL_ID = '';
  public function __construct() {
    $options = getopt('b:u:i:');
    if (array_key_exists('i', $options)) {
      self::$TARGET_ACL_ID = $options['i'];
    } else {
      echo "You must set a target ACL ID";
      exit(1);
    }
    if  (array_key_exists('b', $options)) {
      // ban IP
      self::ban($options['b']);
    }
    if  (array_key_exists('u', $options)) {
      // unban IP
      self::unban($options['u']);
    }
    
  }
  public static function getAllIpsBanned() {
    $banned = array();
    $response = shell_exec('aws ec2 describe-network-acls');
    $acls = json_decode($response, 1);
    foreach ($acls['NetworkAcls'] as $acl) {
      if($acl['NetworkAclId'] == self::$TARGET_ACL_ID) {
        foreach($acl['Entries'] as $entry) {
          if ($entry['RuleAction'] == 'deny') {
            $banned[$entry['RuleNumber']] = $entry['CidrBlock']; 
          }
        }
      }
    }
    return $banned;
    
  }
  public static function unban($ip) {
    $rule_number = self::getRuleNumber($ip);
    if (!empty($rule_number)) {
      foreach($rule_number as $number) {
        echo "Deleting rule ".$number;
        shell_exec('aws ec2 delete-network-acl-entry --network-acl-id '.self::$TARGET_ACL_ID. ' --rule-number '.$number. ' --ingress');  
      }
    } else {
      echo "ACL Rule ddoes not exist!";
    }
  }
  private static function getRuleNumber($ip) {
    $ip = self::formatIp($ip);
    $banned = self::getAllIpsBanned();
    $rule_number = array_keys($banned, $ip);
    return $rule_number;
  }
  private static function ruleExists($ip) {
    return empty(self::getRuleNumber($ip)) ? false : true; 
  }
  private static function getAvailableRuleNumber() {
    $number = rand(1, 32766);
    if (array_key_exists($number, self::getAllIpsBanned())) {
      return self::getAvailableRuleNumber();
    } else {
      return $number;
    }
  }
  private static function formatIp($ip) {
    $ip = trim($ip);
    $ip = str_replace('/32', '', $ip);
    return $ip.'/32';

  }
  public static function ban($ip) {
    $ip = self::formatIp($ip);
    if(!self::ruleExists($ip)) {
      $rule_number = self::getAvailableRuleNumber();
      echo "Adding $ip DENY rule to ACL";
      shell_exec('aws ec2 create-network-acl-entry --network-acl-id '.self::$TARGET_ACL_ID.' --rule-number '.$rule_number.' --protocol -1 --rule-action deny --ingress --cidr-block '.$ip);
    } else {
      echo "Rule Exists! Not importing!";
    }
  }

}
