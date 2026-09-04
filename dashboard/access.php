<?php
  $username = $_POST["username"];
  $password = $_POST["password"];
echo $username . "1</br>";
echo $password . "2</br>";
echo $node_user . "3</br>";
echo $node_password ."4</br>";
  // Get the existing username and password information from the Raspberry Pi
  
  // Check if the user input matches the stored information
  if ($username == $node_user and $password == $node_password) {
    echo "Access granted!"."</br>";
  } else {
    echo "Access denied."."</br>";
  }
?>
