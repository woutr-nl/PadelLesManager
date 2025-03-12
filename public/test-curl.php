<?php
$ch = curl_init("https://www.google.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

if($response === false) {
    echo "Curl Error: " . curl_error($ch);
} else {
    echo "Curl successfully fetched data!";
}

curl_close($ch);
?>
