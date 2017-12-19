<?php

$plugin_name = "Client Ban";

class UserAgent {

    var $agent_id = 0;
    var $agent_name = "";

    function UserAgent($agent_name) {
    	global $client, $i;

        if(strlen($agent_name) > 2) {

        	$agent_name = stripslashes($agent_name);
            preg_match("/^([^;]*).*$/", $agent_name, $m);
			$this->agent_name = $m[1];

            if($agent = $this->getAgent()) {

                $this->updateHits();

                if($agent["aktiv"] == 0) {
                	benc_resp_raw($client[$i]['sock'],"Du benutzt einen ungültigen Client!","text/plain");
                }

            } else {
            	$this->insert();
            }
        } else {
        	benc_resp_raw($client[$i]['sock'],"Du benutzt einen ungültigen Client!","text/plain");
        }

    }

    function getAgent() {

        $ret = false;

        $sql = "SELECT * FROM agents WHERE agent_name = '".mysql_real_escape_string($this->agent_name)."'";
        $qry = mysql_query($sql);
        $a = mysql_fetch_assoc($qry);

        if ($a["agent_id"] > 0) {

            $this->agent_id = $a["agent_id"];
            $ret = $a;

        }

        return $ret;

    }

    function updateHits() {

        $sql = "UPDATE agents SET hits=hits+1 WHERE agent_id = '".$this->agent_id."'";
        mysql_query($sql);

    }

    function insert() {

        if(strlen($this->agent_name) > 2) {

            $sql = "INSERT INTO agents VALUES ('', '".mysql_real_escape_string($this->agent_name)."', 1, ".time().", 1)";
            mysql_query($sql);

        }
    }

}

?>