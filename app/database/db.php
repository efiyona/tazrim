<?php
session_start();

require("connect.php");


function dd($value)
{
    echo "<pre>", print_r($value, true), "</pre>";
    die();
}

function excuteQuery($sql, $data)
{
    global $conn;
    $stmt = $conn->prepare($sql);
    $values = array_values($data);
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    return $stmt;
}


function selectAll($table, $conditions = [])
{
    global $conn;
    $sql = "SELECT * FROM $table";
    if(empty ($conditions))
    {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    else
    {
        $i=0;
        foreach($conditions as $key=> $value){
            if($i === 0){
                $sql = $sql . " WHERE $key=?";
            }else{
                $sql = $sql . " AND $key=?";
            }
            $i++;
        } 
        
        $stmt = excuteQuery($sql, $conditions);
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // --- מנגנון פענוח אוטומטי ---
    if ($records) {
        // רשימת העמודות שדורשות פענוח (אפשר להוסיף עוד בעתיד עם פסיק)
        $encrypted_columns = ['initial_balance']; 
        
        foreach ($records as &$row) {
            foreach ($encrypted_columns as $col) {
                if (array_key_exists($col, $row)) {
                    $row[$col] = decryptBalance($row[$col]);
                }
            }
        }
    }
    // ----------------------------

    return $records;
}

function selectOne($table, $conditions)
{
    global $conn;
    $sql = "SELECT * FROM $table";

    $i=0;
    foreach($conditions as $key=> $value){
        if($i === 0){
            $sql = $sql . " WHERE $key=?";
        }else{
            $sql = $sql . " AND $key=?";
        }
        $i++;
    } 
    
    $sql = $sql . " LIMIT 1";
    $stmt = excuteQuery($sql, $conditions);
    $records = $stmt->get_result()->fetch_assoc();

    // --- מנגנון פענוח אוטומטי ---
    if ($records) {
        $encrypted_columns = ['initial_balance']; 
        
        foreach ($encrypted_columns as $col) {
            if (array_key_exists($col, $records)) {
                $records[$col] = decryptBalance($records[$col]);
            }
        }
    }
    // ----------------------------

    return $records;
}

function create($table, $data)
{
    global $conn;
    
    $sql = "INSERT INTO $table SET ";
    $i=0;
    foreach($data as $key=> $value){
        if($i === 0){
            $sql = $sql . " $key=?";
        }else{
            $sql = $sql . ", $key=?";
        }
        $i++;
    } 
    
    $stmt = excuteQuery($sql, $data);

    $id = $stmt->insert_id;
    return $id;
}

function update($table, $id, $data)
{
    global $conn;
    
    $sql = "UPDATE $table SET ";

    $i=0;
    foreach($data as $key=> $value){
        if($i === 0){
            $sql = $sql . " $key=?";
        }else{
            $sql = $sql . ", $key=?";
        }
        $i++;
    } 
    
    $sql = $sql . " WHERE id=?";
    $data['id'] = $id;  
    $stmt = excuteQuery($sql, $data);
    return $stmt->affected_rows;
}

function delete($table, $id)
{
    global $conn;
    
    $sql = "DELETE FROM $table WHERE id=?";

    $stmt = excuteQuery($sql, ['id' => $id]);
    return $stmt->affected_rows;
}

function addNotification($home_id, $title, $message, $type = 'info', $user_id = null) {
    $data = [
        'home_id'   => $home_id,
        'user_id'   => $user_id,
        'title'     => $title,
        'message'   => $message,
        'type'      => $type
    ];
    
    return create('notifications', $data);
}

function encryptBalance($value) {
    if ($value === null || $value === '') return null;
    $key = ENCRYPTION_KEY;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decryptBalance($value) {
    if (empty($value)) return 0;
    if (is_numeric($value)) return (float)$value; 
    
    $key = ENCRYPTION_KEY;
    $parts = explode('::', base64_decode($value), 2);
    
    if (count($parts) === 2) {
        $decrypted = openssl_decrypt($parts[0], 'aes-256-cbc', $key, 0, $parts[1]);
        if ($decrypted !== false) return (float)$decrypted;
    }
    return 0; 
}