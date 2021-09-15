## Yii2 + Swoole

- 复制test目录下`ys.php`文件到项目目录,修改文件内定义目录常量,`swoole http` 配置(详细可查看src\Config.php),以及`getWebApp`方法

```shell
php ys start
php ys start -d
php ys stop
php ys restart
```

- [x] 自定义`Application`,添加`swooleServer`属性
- [x] 替换`request`组件
- [x] 替换`response`组件 
- [x] 替换`session`组件 
- [ ] 替换`mysql`组件 
- [ ] 替换`redis`组件 
- [x] 文件更新自动重启`AutoReload`

- [ ] 小心使用`static`