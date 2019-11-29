
# 一 框架结构

### 1 doba框架结构
```
|-core
|   |-AutoTask.php
|   |-BaseConfig.php
|   |-BaseDAO.php
|   |-Cookie.php
|   |-Des3.php
|   |-Plugin.php
|   |-RedisClient.php
|   |-Session.php
|   |-SQL.php
|   |-Util.php
|
|-struct
|   |-同下面的生成项目结构   
|
|-init.php
|
|-readme.md
```


### 2 项目结构（骨架）
```
|-autotask
|-cache
|-callback
|-common
|   |-config
|   |   |-config.php
|   |   |-varconfig.php
|   |
|   |-libs 
|   |   |-dao
|   |   |-map
|   |
|   |-plugin
|   |   |-rpc
|   |   |   |-config.php
|   |   |
|   |   |-web      
|   |   |   |-lang   
|   |   |   |-BaseController.php
|   |   |   |-config.php
|   |   |
|   |   |-pagination
|   |   |   |-config.php   
|   |   |
|   |   |-BasePlugin.php
|   |      
|   |-rpc
|
|-doba
|-mgr
|   |-同以下web
|-mgr.php
|
|-web
|   |-controller
|   |   |-DefaultController.php
|   |
|   |-lang
|   |   |-en.php
|   |   |-zh.php
|   |   
|   |-views
|   |   |-default
|   |   |   |-index.php
|   |   |   
|   |   |-header.php
|   |   |-footer.php
|   |
|-index.php
```

### 3 自动加载类映射

引用对象的时候，根据包名区分
```
\Doba    => [项目]/doba/core/
\Doba\Dao\db1    => [项目]/common/libs/dao/db1/
\Doba\Map\db2    => [项目]/common/libs/map/db2/
\Doba\rpc   => [项目]/common/rpc/
```

# 二 快速构建项目

### 1 引入Doba框架
```
git clone https://github.com/jinhanjiang/doba
```

### 2 生成项目开发骨架

配置访问站点

例如: 要做一个博客站点，首先要在本地配置好一个能访问到的站点 
```
a 项目目录:
/data0/website/blog

b 域名 http://blog.xxx.com 指向到项目目录

c 引用doba框架到项目目录下 /data0/website/blog/doba

d 通过url初始化项目骨架
http://blog.xxx.com/doba/init.php?a=init
```
执行以上步骤后。生成项目结构


# 三 配置多数据库链接

Doba框架支持多个数据库链接， 并快速生成DAO操作表结构

### 1 在/data0/website/blog/common/config/下, 创建varconfig.php

`当用开发环境，和正式环境区分时，使用常量配置，可解决不同环境配置不同的问题`


```
<?php
define('DB_CONFIGS', 
    json_encode(
        array(
            'db1'=>array(
                'dbHost'=>'192.168.0.1',
                'dbName'=>'testdb1',
                'dbUser'=>'root',
                'dbPass'=>'123456',
            ),
            'db2'=>array(
                'dbHost'=>'192.168.0.2',
                'dbName'=>'testdb2',
                'dbUser'=>'root',
                'dbPass'=>'123456',
            ),
        )
    )
);
```

### 2 或者在/data0/website/blog/common/config/config.php中配置


`注意：这里配置了两个数据库链接， 这里的db1, db2要注意，会生成的表命名空间`

例如：

```
# db1下面有一张Account表，在实例化对象的时, 其中的`\Db1`, 是上面配置中的db1设置

$account1 = \Doba\Dao\Db1\AccountDAO::me()->finds(array('selectCase'=>'*', 'limit'=>1));

# db2下面Account表
$account2 = \Doba\Dao\Db2\AccountDAO::me()->finds(array('selectCase'=>'*', 'limit'=>1));
```

### 3 创建好数据库链接后。生成DAO及MAP数据库表映射

```
问:为什么生成DAO和MAP
答:生成DAO文件，可支持对表数据的，增(insert)，删(delete)，查(finds, get)，改(change)操作, 生成MAP是生成数库表的字段映射，生成insert, update, select的sql语句时，可对传入的表字段进行验证
```

生成表映射的时候，如果分多张表，有些表不生成映射可设置规则，默认以 下划线数字(例如_0)， 或 数字结尾的表，不生成映射

如果要修改规则，可在/data0/website/blog/common/config/config.php中设置

```
# 设置以_test的表不生成DAO和MAP
class Config extends \Doba\BaseConfig {
    ...

    public function initDaoMapConfig() {
        $initDaoMapConfig = parent::initDaoMapConfig();
        return array(
            'IGNORED_TABLES'=>array('/^\w+_\d+$/i', '/^\w+\d+$/i', '/_test$/i'),
            'IGNORED_TABLES_PREFIX'=>array('/^db_/'),
            ) + $initDaoMapConfig;
    }

    ...
}
```

`在Config类中可重写父类的方法，用于更改底层配置`

### 4 执行刷新表结构操作

`每次执行，如果DAO存在不会覆盖，MAP文件每次会覆盖为最新结构`
```
# 访问地址
http://blog.xxx.com/doba/init.php
```

# 四 项目开发

### 1 默认请求到index.php这个文件中, 经过判断后， 请求最后交给到
```
访问:
http://blog.xxx.com

请求到
/data0/website/blog/web/controller/DefaultController.php

其中的DefaultController.index方法处理
```

### 2 通过URL定位页面
```
http://blog.xxx.com/index.php?a=blog.pageList
```

可以看到结尾是a=blog.pageList

所以可以在controller下找到 BlogController.php

其中BlogController.pageList处理这个请求

在views目录下有blog这个目录，且目录下有page-list.php这个文件渲染页面效果

`如果a后面的参数没有点(.)，默认请求到DefaultController下， 例如a=login`

# 五 多语言

在生成的例子中，已有多语言的配置

设置多语言在，/data0/website/blog/web/lang目录下

en.php
```
<?php
return array(
'Hi'=>'Hi',
'Hi, %1, welcome to the website developed by the %2 php framework.'=>'Hi, %1, welcome to the website developed by the %2 php framework.',
);
```
zh.php
```
<?php
return array(
'Hi'=>'您好',
'Hi, %1, welcome to the website developed by the %2 php framework.'=>'你好, %1, 欢迎访问由%2 php框架开发的网站。',
);
```

页面中显示多语言
```
<p>{{ @Hi }}</p>
<p><?php echo langi18n('Hi'); ?></p>
// 以上英语显示:Hi, 中文显示:您好

<p><?php echo langi18n('Hi, %1, welcome to the website developed by the %2 php framework.', 'Cheech', 'Doba')?></p>
// 如果翻译中包含要替换的变量，可使用以上方法
// 以上中文输出:你好, Cheech, 欢迎访问由Doba php框架开发的网站。
```
# 六 数据库增删改操作

前面我看看到可以通过 Dao来查询数据库， 其实Dao主要封装了，对数据库的（增，删，查，改）的操作，下面我们来看一下如何操作

### 1 增加数据到数据库
```
# 以上面的AccountDAO为例，创建数据
\Doba\Dao\Db1\AccountDAO::me()->insert(
    array(
        'username'=>'doba', 
        'password'=>'123456',
        'source'=>1,
        'otherId'=>12345,
        'name'=>'doba',
        'nick'=>'xiao ming',
        'createTime'=>date('Y-m-d H:i:s'),
    )
);
```

### 2 删除数据库数据
```
\Doba\Dao\Db1\AccountDAO::me()->delete(1);

# 解释:通过查看\Doba\BaseDao.php可以知道， 数据库表当中有一个主键(primary key), 在创建表结构时，可自定义，Doba框架默认为这个值为id, 当然你的表结构不是这个值，可以初始化Dao的时候修改这个值
```

### 3 查询数据库数据

同上面(2解释)，传入值为主键值
```
\Doba\Dao\Db1\AccountDAO::me()->get(1);

```

当然如果你的表当中有，其他唯一键判断，可查出一条数据，可自行封装一个方法， 例如
```
class AccountDAO extends BaseDAO {
    ...

    public function getBy($params)
    {
        if(isset($params['id'])) return parent::get((int)$params['id']);

        $plus = array();

        // source 和 otherId 保证数据库查询唯一值
        if($params['source'] && $params['otherId']) {
            $plus['source'] = $params['source'];
            $plus['otherId'] = $params['otherId'];
        }

        // 如果有其他条件，可以再加

        $objs = count($plus) > 0 ? $this->finds(array('limit'=>1) + $plus) : array(); 
        return isset($objs[0]) ? $objs[0] : NULL;
    }

    ...
}

```

除了 查询单条数据，使用finds方法可以查出多条数据，及通过多个条件查询

```
# 相等查询

\Doba\Dao\Db1\AccountDAO::me()->finds(
    array(
        'username'=>'doba', 
    )
);
# SQL语句: SELECT * FROM `Account` WHERE 1=1 AND `username`='doba'

还可以这样写
\Doba\Dao\Db1\AccountDAO::me()->finds(
    array(
        'username'=>array('value'=>'doba'), 
    )
);
# SQL语句: SELECT * FROM `Account` WHERE 1=1 AND `username`='doba'

```

可以看到上面使第二个查询传入的值为数组，可以设置多个条件
```
# 数据可传值
array('and'=>true, 'op'=>'=', 'value'=>'')
```

- and 默是(true)， 意思是，在拼接语句时用AND， 当然这里传false, 拼接用OR
- op 默认是(=)等于，字段名后面的操作符是等于
- value 实际传入的值，可以传入( integer、float、string 或 boolean 的变量 ) 不能传入 ( array、object 和 resource , NULL )

其中op，有多种传值方式
```
[eq =], [geq, >=], [gt, >], [leq, <=], [lt, <], [<>, !=], in, not in ,like, [custom]
```

我来拿实际的例子来看一下

```
# 自定义查询
\Doba\Dao\Db1\AccountDAO::me()->finds(
    array(
        'name'=>array('op'=>'custom', 'value'=>"`name` LIKE '%doba%' OR `nick` LIKE '%doba%'"),
    )
);
# SQL语句: SELECT * FROM `Account` WHERE 1=1 AND (`name` LIKE '%doba%' OR `nick` LIKE '%doba%')


# 条件查询
\Doba\Dao\Db1\AccountDAO::me()->finds(
    array(
        'name'=>array('op'=>'like', 'value'=>"doba"),
    )
);
# SQL语句: SELECT * FROM `Account` WHERE 1=1 AND `name` LIKE '%doba%')


\Doba\Dao\Db1\AccountDAO::me()->finds(
    array(
        'source'=>array('and'=>false, 'op'=>'in', 'value'=>"1,2,3"),
    )
);
# SQL语句: SELECT * FROM `Account` WHERE 1=1 OR `source` IN (1,2,3)

\Doba\Dao\Db1\AccountDAO::me()->finds(
    array(
        'id'=>array('op'=>'in', 'value'=>"1,2,3"),
    )
);
# SQL语句: SELECT * FROM `Account` WHERE 1=1 OR `id` IN (1,2,3)

\Doba\Dao\Db1\AccountDAO::me()->finds(
    array(
        'id'=>array('op'=>'gt', 'value'=>"100"), // gt 可以换成 > 是相同的效果
    )
);
# SQL语句: SELECT * FROM `Account` WHERE 1=1 AND `id`>'100'
```

上面讲到了传值通过数组来，拼接条件，接下来讲一下通过字段后缀来拼条件， 这样操作比较简洁

```
\Doba\Dao\Db1\AccountDAO::me()->finds(
    array(
        'idGt'=>100,
    )
);
# SQL语句: SELECT * FROM `Account` WHERE 1=1 AND `id`>'100'

\Doba\Dao\Db1\AccountDAO::me()->finds(
    array(
        'idIn'=>"1,2,3",
        'orderBy'=>'id DESC',
        'groupId'=>'source',
        'limit'=>5,
    )
);
# SQL语句: SELECT * FROM `Account` WHERE 1=1 OR `id` IN (1,2,3) GROUP BY source ORDER BY id DESC LIMIT 5
```

可以发现，通过字段名 + 后缀[ Gt, In , Like, Geq, Leq, Lt, Neq] 来达到相关查询, 更多方法，可以自已尝试一下


小技巧， 数据库有很多的数据，要全部更新， 但不同的条件更新不同的值可以这样写

```

$lastId = 0; $limit = 100;
while (true) {
    $objs = \Doba\Dao\AccountDAO::me()->finds(
        array(
            'selectCase'=>'id,otherId', 
            'source'=>1, 
            'idGt'=>$lastId, 
            'orderBy'=>'id ASC', 
            'limit'=> $limit
        )
    );
    if(($ct = count($objs)) > 0)
    {
        foreach($objs as $obj)
        {
            // 这里是很行数据，根据条件处理（修改，删除）

        }
        if($ct < $limit) break;
        $lastId = $obj->id,
    }
    else
    {
        break;
    }
    usleep(mt_rand(500, 2000000));
    // 脚本执行太长时间，数据库链接可能会断掉，可以用下面语句重连数据库
    \Doba\Dao\AccountDAO::me()->resetdb();
}
```


### 4 更新数据库数据

```
\Doba\Dao\Db1\AccountDAO::me()->change(
    1, // 注意这里，(重点), 这个值是主键值
    array(
        'username'=>'doba', 
        'password'=>'123456',
        'source'=>1,
        'otherId'=>12345,
        'name'=>'doba',
        'nick'=>'xiao ming',
        'createTime'=>date('Y-m-d H:i:s'),
    )
);
```

# 七 开放外部调用接口

### 1 设置固定的帐号密钥

在/data0/website/blog/common/config/config.php中设置

```
$API_CALL_CONFIG = array(
    // '帐号'=>'密钥', 可自已定义
    '10000'=>'e10adc3949ba59abbe56e057f20f883e',
)
```

### 2 PHP请求方式参考
```
define('API_KEY', '10000');
define('API_TOKEN', 'e10adc3949ba59abbe56e057f20f883e');

$content = json_encode(
    array(
        'api'=>'api.Util.ping',
        'edatas'=>array('test'=>'测试请求'),
        'timestamp'=>time(),
        'version'=>'v1.0'
    )
);
$headers = array(
    "Content-Type"=> "application/json",
    "X-Api-Key" => API_KEY,
    "X-Api-Token" => md5($content.API_TOKEN),
);
$rawHeader = "";
foreach ($headers as $h => $c){
    $rawHeader.=$h . ": " . $c . "\r\n";
}
$ctx = stream_context_create(array(
    'http' => array(
        'method'  => 'POST',
        'header'  => $rawHeader,
        'content' => $content
    )
));
$jsonResponse = file_get_contents("http://blog.xxx.com/rpc.php", false, $ctx);
print_r(@json_decode($jsonResponse, true));
```

### 3 CURL请求方式参考

```
# md5 校验 md5('111111') = 96e79218965eb72c92a549dd5a330112
# x-api-token : 816bef396f9a37feac4ffd528fd5cb86 = md5('{"api":"api.Util.ping","edatas":{"edatas":{"test":"测试"}},"timestamp":1523863299,"version":"v1.0"}4ef0f32a3d095d889866aa86e9600f2f')

# curl请求
curl -i \
    -H "Content-Type: application/json" \
    -H "X-Api-Key: 10000" \
    -H "X-Api-Token: 816bef396f9a37feac4ffd528fd5cb86" \
    -X POST \
    -d '{"api":"api.Util.ping","edatas":{"edatas":{"test":"测试"}},"timestamp":1523863299,"version":"v1.0"}' \
    http://blog.xxx.com/rpc.php


#返回结果
HTTP/1.1 200 OK
Date: Wed, 22 Nov 2019 04:56:52 GMT
Server: Apache/2.4.12 (Unix) PHP/5.6.6 OpenSSL/0.9.8zh
X-Powered-By: PHP/5.6.6
Access-Control-Allow-Origin: *
Access-Control-Allow-Headers: Content-Type
Access-Control-Allow-Methods: POST
Content-Length: 94
Content-Type: application/json

{"ErrorCode":"9999","Message":"SUCCESS","Data":{"Results":{"time":"2019-11-22 04:56:52","request":{"edatas:{"test":"测试"},"lang":"en"}}}}
```

### 4 JAVA请求方式参考

```
package doba;

import java.io.IOException;
import java.security.MessageDigest;
import java.util.HashMap;
import java.util.Map;

import org.apache.commons.httpclient.HttpClient;
import org.apache.commons.httpclient.methods.PostMethod;
import org.apache.commons.httpclient.methods.RequestEntity;
import org.apache.commons.httpclient.methods.StringRequestEntity;
import org.apache.http.client.ClientProtocolException;

import net.sf.json.JSONObject;

public class Demo {
    private static String REQUEST_URL = "http://blog.xxx.com/rpc.php";

    private static String API_KEY = "10000";
    private static String API_TOKEN = "e10adc3949ba59abbe56e057f20f883e";

    public static void main(String[] args) {
        try {
            // 封装查询条件: {"timestamp":1545131290,"api":"api.Util.ping","edatas":{"test":"测试"},"version":"v1.0"}
            Map<String,Object> edatas = new HashMap<String,Object>();
            edatas.put("test", "测试");

            Map&lt;String,Object> requestJsonEntitBean = new HashMap<String,Object>();
            requestJsonEntitBean.put("api", "api.Util.ping");
            requestJsonEntitBean.put("edatas", edatas);
            requestJsonEntitBean.put("timestamp", System.currentTimeMillis() / 1000);
            requestJsonEntitBean.put("version", "v1.0");
            
            String jsonstr = JSONObject.fromObject(requestJsonEntitBean).toString();

            System.out.println(jsonstr);

            PostMethod pm = new PostMethod(REQUEST_URL);
            pm.setRequestHeader("X-Api-Key", API_KEY);
            pm.setRequestHeader("X-Api-Token", Demo.md5(jsonstr+API_TOKEN));
            pm.setRequestHeader("User-Agent", "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"));

            System.out.println(Demo.md5(jsonstr+API_TOKEN));
            
            RequestEntity requestEntity = new StringRequestEntity(jsonstr, "application/json", "UTF-8");
            pm.setRequestEntity(requestEntity);
            HttpClient httpClient = new HttpClient();
            try {
                httpClient.executeMethod(pm);
                byte[] responseBody = pm.getResponseBody();
                String response = new String(responseBody, "UTF-8");
                if (response != null) {
                    try {
                        System.out.println(response);
                    } catch (Exception e) {
                        e.printStackTrace();
                    }
                }
            } catch (ClientProtocolException e1) {
                e1.printStackTrace();
            } catch (IOException e1) {
                e1.printStackTrace();
            } catch (Exception e) {
                System.out.println(e.getMessage());
                e.printStackTrace();
            }
        } catch (Exception e) {
            System.out.println(e.getMessage());
            e.printStackTrace();
        }
    }
    
    public final static String md5(String s) {  
        char hexDigits[]={'0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F'};         
        try {  
            byte[] btInput = s.getBytes();  
            MessageDigest mdInst = MessageDigest.getInstance("MD5");  
            mdInst.update(btInput);  
            byte[] md = mdInst.digest();  
            int j = md.length;  
            char str[] = new char[j * 2];  
            int k = 0;  
            for (int i = 0; i < j; i++) {  
                byte byte0 = md[i];  
                str[k++] = hexDigits[byte0 >>> 4 & 0xf];  
                str[k++] = hexDigits[byte0 & 0xf];  
            }  
            return new String(str);  
        } catch (Exception e) {  
            e.printStackTrace();  
            return null;  
        }  
    } 
}
```

### 5 PYTHON请求方式

```
#!/usr/bin/python
# -*- coding: UTF-8 -*-

import requests
import json
import time
import hashlib

API_KEY = "10000"
API_SECURE = "e10adc3949ba59abbe56e057f20f883e"

data = {
    "api":"api.Util.ping",
    "edatas": {
        "test":"测试"
    },
    "timestamp":time.time(),
    "version": "1.0",
}
token = hashlib.new('md5', json.dumps(data) + API_SECURE).hexdigest()
header = {
    'Content-Type': 'application/json',
    'X-Api-Key': API_KEY,
    'X-Api-Token': token,
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36',
}

url = 'https://blog.xxx.com/rpc.php'
post = requests.post(url, data=json.dumps(data), headers=header)

print(post.text)
```
