<?php

$plugin_name = "Flood Protect";

class FloodProtect {

  var $maxConPerMin = 20;
  var $bantime      = 60;

  function FloodProtect($autocheck = false) {

    $dt = time() - 60;
    $sql = "DELETE FROM floods WHERE added <= ".$dt." AND banned = 'no'";
    $this -> execute($sql);

    $dt = time() - $this->bantime;
    $sql = "DELETE FROM floods WHERE added <= ".$dt." AND banned = 'yes'";
    $this -> execute($sql);

    if ($autocheck)
    {
      $this -> check();
    }

  }

  function check() {
  	global $client, $i, $getip;

    $ip = $getip;

    $sql = "SELECT *  FROM floods WHERE ip = '".mysql_real_escape_string($ip)."'";
    $res = $this -> execute($sql);
    $arr = mysql_fetch_assoc($res);

    $count = intval($arr['cons']);

    if ($count == 0)
    {
      $sql = "INSERT INTO floods (added, ip, cons, banned) VALUES (".time().", '".mysql_real_escape_string($ip)."', 1, 'no')";
      $this -> execute($sql);
    }
    else
    {
      $count++;
      if ($count > $this-> maxConPerMin)
      {
        if ($arr['banned'] == "yes")
        {
          benc_resp_raw($client[$i]['sock'],"Zuviele Verbindungen. Bitte warte noch ".(($arr['added'] + $this->bantime)- time())." Sekunden");
        }
        else
        {
          $sql = "UPDATE floods SET added = ".time().", banned = 'yes' WHERE ip = '".mysql_real_escape_string($ip)."'";
          $this -> execute($sql);
          benc_resp_raw($client[$i]['sock'],"Zuviele Verbindungen. Deine IP wurde für ".$this->bantime." Sekunden gesperrt");
        }
      }
      else
      {
        $sql = "UPDATE floods set cons = cons + 1 WHERE ip='".mysql_real_escape_string($ip)."'";
        $this -> execute($sql);
      }
    }
  }

  function execute($sql) {
  	global $client, $i;

    return mysql_query($sql) or benc_resp_raw($client[$i]['sock'],"MySQL Error:<hr>Executet SQL:".$sql);

  }

}

?>