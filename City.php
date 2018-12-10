<?php
/**
 * 解析中国省市区行政区划数据的一个类
 * 数据来源:http://www.stats.gov.cn
 * @author  詹光成 <14712905@qq.com>
 * @date(2018-12-08)
 */
class City
{
    private $url = 'http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2017/%s.html';
    private $city1_pattern = "/<td><a href='(\d\d)\.html'>(\D+?)<br\/><\/a><\/td>/";
    private $city2_pattern = "/<td><a href='\d\d\/(\d{4})\.html'>([\x{4e00}-\x{9fa5}]+?)<\/a><\/td>/u";
    private $city3_pattern = "/<tr class='countytr'><td>.*?(\d{6})\d{6}.*?>([\x{4e00}-\x{9fa5}]+?)<\//u";
    private $curl;

    private $tableName = 'city';
    
    public function __construct($curl)
    {
        $this->curl = $curl;
    }

    public function parse()
    {
        try {
            return $this->getData();
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }
    
    /**
     * 获取数据
     * @return array [id,pid,name,en,en_simple,children]
     */
    public function getData()
    {
        $url = sprintf($this->url, 'index');
        if (!preg_match_all($this->city1_pattern, $this->get($url), $matches)) {
            throw new Exception('city1 matches error');
        }
        $city_data = [];
        for ($i=0; $i<count($matches[1]); $i++) {
            $url = sprintf($this->url, $matches[1][$i]);
            if (!preg_match_all($this->city2_pattern, $this->get($url), $matches2)) {
                throw new Exception('city2 matches error');
            }
            $city2 = [];
            for ($j=0; $j<count($matches2[1]); $j++) {
                $url = sprintf($this->url, $matches[1][$i].'/'.$matches2[1][$j]);
                if (preg_match_all($this->city3_pattern, $this->get($url), $matches3)) {
                    $city3 = [];
                    for ($k=0; $k<count($matches3[1]); $k++) {
                        $city3[$matches3[1][$k]] = [
                            'id'=>$matches3[1][$k],
                            'pid'=>$matches2[1][$j] . '00',
                            'name'=>$matches3[2][$k],
                            'en'=>$this->toEn($matches3[2][$k], 'normal'),
                            'en_simple'=>$this->toEn($matches3[2][$k], 'simple'),
                        ];
                    }
                    $city2[$matches2[1][$j] . '00'] = array(
                        'id'=>$matches2[1][$j] . '00',
                        'pid'=>$matches[1][$i] . '0000',
                        'name'=>$matches2[2][$j],
                        'en'=>$this->toEn($matches2[2][$j], 'normal'),
                        'en_simple'=>$this->toEn($matches2[2][$j], 'simple'),
                        'children'=>$city3
                    );
                } else {
                    $city2[] = array(
                        'id'=>$matches2[1][$j] . '00',
                        'pid'=>$matches[1][$i] . '0000',
                        'name'=>$matches2[2][$j],
                        'en'=>$this->toEn($matches2[2][$j], 'normal'),
                        'en_simple'=>$this->toEn($matches2[2][$j], 'simple'),
                    );
                }
            }
            echo $matches[1][$i] . '0000';
            echo PHP_EOL;
            $city_data[] = array(
                'id'=>$matches[1][$i] . '0000',
                'pid'=>0,
                'name'=>$matches[2][$i],
                'en'=>$this->toEn($matches[2][$i], 'normal'),
                'en_simple'=>$this->toEn($matches[2][$i], 'simple'),
                'children'=>$city2
            );
        }
        return $city_data;
    }

    /**
     * 构建sql
     */
    public function buildSql(&$data)
    {
        $sql = "CREATE TABLE IF NOT EXISTS `$this->tableName`(\n    `id` MEDIUMINT UNSIGNED PRIMARY KEY,\n    `pid` MEDIUMINT UNSIGNED NOT NULL,\n    `name` VARCHAR(20) NOT NULL,\n    `en` VARCHAR(100) NOT NULL,\n    `en_simple` VARCHAR(20) NOT NULL,\n    INDEX `pid`(`pid`)\n) engine=innodb default charset=utf8;\n\n";
        $len = count($data);
        foreach ($data as $k => $v) {
            if ($k % 2000 == 0) {
                $sql .= "INSERT INTO {$this->tableName} (id,pid,name,en,en_simple) VALUES\n";
            }
            if (($k + 1) % 2000 == 0 || $len == $k + 1) {
                $sql .= sprintf("(%d,%d,'%s','%s','%s');\n\n", $v['id'],$v['pid'],$v['name'],$v['en'],$v['en_simple']);
            } else {
                $sql .= sprintf("(%d,%d,'%s','%s','%s'),\n", $v['id'],$v['pid'],$v['name'],$v['en'],$v['en_simple']);
            }
        }
        return $sql;
    }

    /**
     * 构建Json
     */
    public function buildJson(&$data)
    {
        return $this->jsonEncode($data);
    }

    /**
     * 标准化
     */
    public function normalization(&$arr)
    {
        if (empty($arr)) {
            throw new Exception('城市数据为空');
        }
        $data = array();
        foreach ($arr as $pro) {
            $data[] = array(
                'id' => $pro['id'],
                'pid' => 0,
                'name' => $pro['name'],
                'en' => $pro['en'],
                'en_simple' => $pro['en_simple'],
            );
            if (!isset($pro['children'])) continue;
            foreach ($pro['children'] as $city) {
                $data[] = array(
                    'id' => $city['id'],
                    'pid' => $pro['id'],
                    'name' => $city['name'],
                    'en' => $city['en'],
                    'en_simple' => $city['en_simple'],
                );
                if (!isset($city['children'])) continue;
                foreach ($city['children'] as $area) {
                    $data[] = array(
                        'id' => $area['id'],
                        'pid' => $city['id'],
                        'name' => $area['name'],
                        'en' => $area['en'],
                        'en_simple' => $area['en_simple'],
                    );
                }
            }
        }

        return $data;
    }

    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    private function get($url)
    {
        return $this->convert($this->curl->get($url)->response);
    }

    private function convert($str)
    {
        return iconv('GBK','utf-8',$str);
    }
    
    private function toEn($str, $type = 'normal')
    {
        $res = transliterator_transliterate('Any-Latin; Latin-ASCII;', $str);
        return $type === 'normal'
            ? ucwords($res)
            : implode('', array_map(function($v){return $v[0];}, explode(' ', $res)));
    }
    
    private function jsonEncode($data)
    {
        if (defined('JSON_UNESCAPED_UNICODE')) {
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        array_walk_recursive($data, function (&$item) {
            if (is_string($item)) {
                $item = mb_encode_numericentity($item, array(0x80, 0xffff, 0, 0xffff), 'UTF-8');
            }
        });
        return mb_decode_numericentity(json_encode($data), array(0x80, 0xffff, 0, 0xffff), 'UTF-8');
    }
}
