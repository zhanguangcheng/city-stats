<?php
/**
 * 解析中国省市区行政区划数据的一个类
 * http://www.mca.gov.cn/article/sj/tjbz/a/
 * @author  詹光成 <14712905@qq.com>
 * @date(2018-05-25)
 */
class City
{
    private $url;

    private $tableName = 'city';

    public function __construct($url = null)
    {
        $this->setUrl($url);
    }

    public function parse()
    {
        /*
        | id | pid | name |
         */
        try {
            $str = $this->get($this->url);
            $list = $this->getList($str);
            return $this->structuring($list);
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * 构建sql
     */
    public function buildSql(&$data)
    {
        $sql = "CREATE TABLE IF NOT EXISTS `$this->tableName`(\n    `id` MEDIUMINT UNSIGNED PRIMARY KEY,\n    `pid` MEDIUMINT UNSIGNED NOT NULL,\n    `name` VARCHAR(20) NOT NULL,\n    INDEX `pid`(`pid`)\n) engine=innodb default charset=utf8;\n\n";
        $len = count($data);
        foreach ($data as $k => $v) {
            if ($k % 2000 == 0) {
                $sql .= "INSERT INTO {$this->tableName} (id,pid,name) VALUES\n";
            }
            if (($k + 1) % 2000 == 0 || $len == $k + 1) {
                $sql .= "({$v['id']},{$v['pid']},'{$v['name']}');\n\n";
            } else {
                $sql .= "({$v['id']},{$v['pid']},'{$v['name']}'),\n";
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
            );
            if (!isset($pro['sub'])) continue;
            foreach ($pro['sub'] as $city) {
                $data[] = array(
                    'id' => $city['id'],
                    'pid' => $pro['id'],
                    'name' => $city['name'],
                );
                if (!isset($city['sub'])) continue;
                foreach ($city['sub'] as $area) {
                    $data[] = array(
                        'id' => $area['id'],
                        'pid' => $city['id'],
                        'name' => $area['name'],
                    );
                }
            }
        }

        return $data;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * 结构化城市数据
     * @param  array $list 列表数据
     * @return array       ['id'=>, 'name'=>'', 'sub'=> ['id', 'name'=>'', sub'=>[...]]]
     */
    public function structuring($list)
    {
        if (empty($list)) {
            throw new Exception('列表为空');
        }
        // 省份
        $province = array_filter($list, function($v) {
            return $v['id'] % 10000 === 0;
        });
        
        // 城市 & 地区
        foreach ($province as &$pro) {
            $citys = array_filter($list, function($v) use($pro) {
                return !strncmp($v['id'], $pro['id'], 2)
                    // 省直辖的县级行政单位第3,4位是90开始的，县级市就从9001，各县就从9021开始排。
                    // 11, 12, 31, 50是直辖市的前2位
                    && ($v['id'] % 100 === 0 || substr($v['id'], 2, 2) === '90' || in_array(substr($v['id'], 0, 2), array(11, 12, 31, 50)))
                    && $v['id'] % 10000 !== 0;
            });
            foreach ($citys as &$city) {
                $area = array_filter($list, function($v) use($city) {
                    return !strncmp($v['id'], $city['id'], 4)
                        && (substr($city['id'], 2, 2) !== '90' && !in_array(substr($v['id'], 0, 2), array(11, 12, 31, 50)))
                        && $v['id'] % 100 !== 0;
                });
                $area && $city['sub'] = array_values($area);
            }
            $citys && $pro['sub'] = array_values($citys);
        }
        return array_values($province);
    }

    private function get($url)
    {
        return file_get_contents($url);
    }

    private function getList($str)
    {
        $pattern = '/<td class=xl\d+>(\d+)<\/td>\n*\s*<td class=xl\d+>(.+?)<\/td>/';
        if (!preg_match_all($pattern, $str, $arr)) {
            throw new Exception('正则匹配失败');
        }

        // $result: ['id'=>110000, 'name'=>'北京市']
        $result = array();
        for ($i = 0; $i < count($arr[1]); $i++) {
            $result[] = array('id' => (int) $arr[1][$i], 'name' => $this->stripTags($arr[2][$i]));
        }
        return $result;
    }

    private function stripTags($str)
    {
        return trim(strtr($str, array(
            "<span style='mso-spacerun:yes'>" => '',
            "</span>" => '',
        )), '  ');
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
