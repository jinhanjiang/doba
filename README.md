
# 一 框架结构

## 1 doba框架结构
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


## 2 项目结构（骨架）
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

## 3 自动加载类映射
引用对象的时候，根据包名区分
```
\Doba    => [项目]/doba/core/
\Doba\Dao\db1    => [项目]/common/libs/dao/db1/
\Doba\Map\db1    => [项目]/common/libs/map/db1/
\Doba\rpc   => [项目]/common/rpc/
```

# 二 快速构建项目

## 1 引入Doba框架
```
git clone http://github.com/jinhanjiang/doba
```

## 2 生成项目开发骨架

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


## 3 配置数据库链接

Doba框架支持多个数据库链接， 并快速生成DAO操作表结构

1 在/data0/website/blog/common/config/下, 创建varconfig.php

```
<?php
define('MYSQL_CONFIGS', 
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

`注意：这里配置了两个数据库链接， 这里的db1, db2要注意，会生成的表命名空间`

例如：

```
# db1下面有一张Account表，在实例化对象的时, 其中的`\Db1`, 是上面配置中的db1设置

$account = new \Doba\Dao\Db1\AccountDAO::me()->finds(array('selectCase'=>'*', 'limit'=>1));

```

2 创建好数据库链接后。生成DAO及MAP数据库表映射

```
# 访问地址
http://blog.xxx.com/doba/init.php
```

# 三 项目开发

1 默认请求到index.php这个文件中, 经过判断后， 请求最后交给到
```
访问:
http://blog.xxx.com

请求到
/data0/website/blog/web/controller/DefaultController.php

其中的DefaultController.index方法处理
```

2 通过URL定位页面
```
http://blog.xxx.com/index.php?a=blog.list
```

可以看到结尾是a=blog.list

所以可以在controller下找到 BlogController.php

其中BlogController.list处理这个请求

在views目录下有blog这个目录，且目录下有list.php这个文件渲染页面效果