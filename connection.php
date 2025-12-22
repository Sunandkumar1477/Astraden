<?php

$servername = "localhost";
$username = "root";
$password = "";
$db_name = "games";

$conn = new mysqli($servername,$username,$password,$db_name);

       if($conn->connect_error){

        //   die("connection failed: ".$conn->connect_error);

        }else{
      //     echo "connection succesfully";
        }
   function uniqid_id(){
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charLength = strlen($chars);
    $randomString = '';
    for ($i=0; $i < 20 ; $i++) {
        $randomString.=$chars[mt_rand(0, $charLength - 1)];
    }
    return $randomString;
   }
?>