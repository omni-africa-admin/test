
<?php


/**
    Function to get Ip address and hostname of client
**/

function get_ClientInfo($post_ID){

    
    $externalContent = file_get_contents('http://checkip.dyndns.com/');
    preg_match('/Current IP Address: \[?([:.0-9a-fA-F]+)\]?/', $externalContent, $m);
    $externalIp = $m[1];

    $hostname = gethostname();
     
    try
    {
        $bdd = new PDO('mysql:host=localhost;dbname=webdb;charset=utf8', 'webuser', 'password');
    }catch (Exception $e){die('Erreur : ' .$e->getMessage());}

    //Verification
    $req = $bdd->query("SELECT * FROM wp_bindu_users_postview WHERE post_id = ".$post_ID." AND ip_address = '".$externalIp."' AND hostname = '".$hostname."'");
    
    if ( $req->rowCount() > 0) {
        $timer = 10;
        
        $end_timestamp = time()+$timer;
        do{
            $current_timestamp = time();
            $difference = $end_timestamp - $current_timestamp;

            if($difference <= 0){
            update_PostViews(get_the_ID());
            }
        }while ( $difference > 0); 

    }
    else{
        $req = $bdd->prepare('INSERT INTO wp_bindu_users_postview(post_id, ip_address, hostname) VALUES(:post_id, :ipaddress, :hostname)');
        $req->execute(array('post_id'=>$post_ID, 'ipaddress'=>$externalIp, 'hostname'=>$hostname));
        update_PostViews(get_the_ID());
    }

}

?>
