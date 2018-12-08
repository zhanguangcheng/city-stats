# city

采集中国省市区行政区划数据，提供了3种格式，在原数据基础上加上了拼音和首拼。

* [json](json/city.json)
* [结构化json](json/city_struct.json)
* [sql](sql/city.sql)

> 数据来源:<http://www.stats.gov.cn>


采集

```php
php build.php
```


区划代码说明

* 一级的代码后4位为0000  
* 二级的代码后2位为00  
* 其他都为三级  

[数据查看](https://raw.githack.com/zhanguangcheng/city-stats/master/build/view.html)
