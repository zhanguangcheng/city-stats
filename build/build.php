<?php
require '../Curl.php';
require '../City.php';

$city = new City(new Curl); 

$struct = $city->parse();
$normal = $city->normalization($struct);

file_put_contents("./json/city_struct.json", $city->buildJson($struct));
file_put_contents("./json/city.json", $city->buildJson($normal));
file_put_contents("./sql/city.sql", $city->buildSql($normal));

echo 'done';
