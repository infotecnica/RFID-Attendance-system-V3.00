<?php  
//Connect to database
require 'connectDB.php';
date_default_timezone_set('Europe/Rome');
$d = date("Y-m-d");
$t = date("H:i:sa");
ini_set('display_errors','On');
error_reporting(E_ALL);
function getLastValidRead($conn, $card_uid) {
    $sql = "SELECT MAX(timein) as last_read FROM users_logs WHERE card_uid = ? AND checkindate = CURDATE()";
    $result = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($result, $sql)) {
        return false;
    }
    mysqli_stmt_bind_param($result, "s", $card_uid);
    mysqli_stmt_execute($result);
    $resultl = mysqli_stmt_get_result($result);
    if ($row = mysqli_fetch_assoc($resultl)) {
        return $row['last_read'];
    }
    return false;
}
if (isset($_GET['card_uid']) && isset($_GET['device_token'])) {
    
    $card_uid = $_GET['card_uid'];
    $device_uid = $_GET['device_token'];

    $sql = "SELECT * FROM devices WHERE device_uid=?";
    $result = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($result, $sql)) {
        echo "SQL_Error_Select_device";
        exit();
    }
    else{
        mysqli_stmt_bind_param($result, "s", $device_uid);
        mysqli_stmt_execute($result);
        $resultl = mysqli_stmt_get_result($result);
        if ($row = mysqli_fetch_assoc($resultl)){
            $device_mode = $row['device_mode'];
            $device_dep = $row['device_dep'];
            if ($device_mode == 1) {
                $sql = "SELECT * FROM users WHERE card_uid=?";
                $result = mysqli_stmt_init($conn);
                if (!mysqli_stmt_prepare($result, $sql)) {
                    echo "SQL_Error_Select_card";
                    exit();
                }
                else{
                    mysqli_stmt_bind_param($result, "s", $card_uid);
                    mysqli_stmt_execute($result);
                    $resultl = mysqli_stmt_get_result($result);
                    if ($row = mysqli_fetch_assoc($resultl)){
                        //*****************************************************
                        //An existed Card has been detected for Login or Logout
                        if ($row['add_card'] == 1){
                        if ($row['device_uid'] == $device_uid || $row['device_uid'] == 0){
                                $Uname = $row['username'];
                                $Number = $row['serialnumber'];
                                $lastRead = getLastValidRead($conn, $card_uid);
                                $currentTime = new DateTime();

                                if ($lastRead) {
                                    $lastReadTime = new DateTime($lastRead);
                                    $timeDifference = $currentTime->getTimestamp() - $lastReadTime->getTimestamp();
                                    
                                    if ($timeDifference < 1200) { // 1200 secondi = 20 minuti
                                        echo "Lettura gia effettuata";
                                        exit();
                                    }
                                }



                                $sql = "SELECT * FROM users_logs WHERE card_uid=? AND checkindate=? AND card_out=0";
                                $result = mysqli_stmt_init($conn);
                                if (!mysqli_stmt_prepare($result, $sql)) {
                                    echo "SQL_Error_Select_logs";
                                    exit();
                                }
                                else{
                                    mysqli_stmt_bind_param($result, "ss", $card_uid, $d);
                                    mysqli_stmt_execute($result);
                                    $resultl = mysqli_stmt_get_result($result);
                                    //*****************************************************
                                    //Entrata
                                    if (!$row = mysqli_fetch_assoc($resultl)){

                                        $sql = "INSERT INTO users_logs (username, serialnumber, card_uid, device_uid, device_dep, checkindate, timein, timeout) VALUES (? ,?, ?, ?, ?, ?, ?, ?)";
                                        $result = mysqli_stmt_init($conn);
                                        if (!mysqli_stmt_prepare($result, $sql)) {
                                            echo "SQL_Error_Select_login1";
                                            exit();
                                        }
                                        else{
                                            $timeout = "00:00:00";
                                            mysqli_stmt_bind_param($result, "sdssssss", $Uname, $Number, $card_uid, $device_uid, $device_dep, $d, $t, $timeout);
                                            mysqli_stmt_execute($result);

                                            echo "login".$Uname;
                                            exit();
                                        }
                                    }
                                    //*****************************************************
                                    //Uscita
                                    else{
                                        $sql="UPDATE users_logs SET timeout=?, card_out=1 WHERE card_uid=? AND checkindate=? AND card_out=0";
                                        $result = mysqli_stmt_init($conn);
                                        if (!mysqli_stmt_prepare($result, $sql)) {
                                            echo "SQL_Error_insert_logout1";
                                            exit();
                                        }
                                        else{
                                            mysqli_stmt_bind_param($result, "sss", $t, $card_uid, $d);
                                            mysqli_stmt_execute($result);

                                            echo "logout ".$Uname;
                                            exit();
                                        }
                                    }
                                }
                            }
                            else {
                                echo "Non consentito!";
                                exit();
                            }
                        }
                        else if ($row['add_card'] == 0){
                            echo "Alunno non registrato!";
                            exit();
                        }
                    }
                    else{
                        echo "Card non registrata!";
                        exit();
                    }
                }
            }
            else if ($device_mode == 0) {
                //New Card has been added
                $sql = "SELECT * FROM users WHERE card_uid=?";
                $result = mysqli_stmt_init($conn);
                if (!mysqli_stmt_prepare($result, $sql)) {
                    echo "SQL_Error_Select_card";
                    exit();
                }
                else{
                    mysqli_stmt_bind_param($result, "s", $card_uid);
                    mysqli_stmt_execute($result);
                    $resultl = mysqli_stmt_get_result($result);
                    //The Card is available
                    if ($row = mysqli_fetch_assoc($resultl)){
                        $sql = "SELECT card_select FROM users WHERE card_select=1";
                        $result = mysqli_stmt_init($conn);
                        if (!mysqli_stmt_prepare($result, $sql)) {
                            echo "SQL_Error_Select";
                            exit();
                        }
                        else{
                            mysqli_stmt_execute($result);
                            $resultl = mysqli_stmt_get_result($result);
                            
                            if ($row = mysqli_fetch_assoc($resultl)) {
                                $sql="UPDATE users SET card_select=0";
                                $result = mysqli_stmt_init($conn);
                                if (!mysqli_stmt_prepare($result, $sql)) {
                                    echo "SQL_Error_insert";
                                    exit();
                                }
                                else{
                                    mysqli_stmt_execute($result);

                                    $sql="UPDATE users SET card_select=1 WHERE card_uid=?";
                                    $result = mysqli_stmt_init($conn);
                                    if (!mysqli_stmt_prepare($result, $sql)) {
                                        echo "SQL_Error_insert_An_available_card";
                                        exit();
                                    }
                                    else{
                                        mysqli_stmt_bind_param($result, "s", $card_uid);
                                        mysqli_stmt_execute($result);

                                        echo "Card gia registrata";
                                        exit();
                                    }
                                }
                            }
                            else{
                                $sql="UPDATE users SET card_select=1 WHERE card_uid=?";
                                $result = mysqli_stmt_init($conn);
                                if (!mysqli_stmt_prepare($result, $sql)) {
                                    echo "SQL_Error_insert_An_available_card";
                                    exit();
                                }
                                else{
                                    mysqli_stmt_bind_param($result, "s", $card_uid);
                                    mysqli_stmt_execute($result);

                                    echo "available";
                                    exit();
                                }
                            }
                        }
                    }
                    //The Card is new
                    else{
                        $sql="UPDATE users SET card_select=0";
                        $result = mysqli_stmt_init($conn);
                        if (!mysqli_stmt_prepare($result, $sql)) {
                            echo "SQL_Error_insert";
                            exit();
                        }
                        else{
                            mysqli_stmt_execute($result);
                            $sql = "INSERT INTO users (card_uid, card_select, device_uid, device_dep, user_date) VALUES (?, 1, ?, ?, CURDATE())";
                            $result = mysqli_stmt_init($conn);
                            if (!mysqli_stmt_prepare($result, $sql)) {
                                echo "SQL_Error_Select_add";
                                exit();
                            }
                            else{
                                mysqli_stmt_bind_param($result, "sss", $card_uid, $device_uid, $device_dep );
                                mysqli_stmt_execute($result);

                                echo "Registrazione card OK";
                                exit();
                            }
                        }
                    }
                }    
            }
        }
        else{
            echo "Invalid Device!";
            exit();
        }
    }          
}
?>