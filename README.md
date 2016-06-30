## Login Bot

针对 zimuzu.tv 和 v2ex.com 的自动登录脚本。

#### 配置

在 config.php 中相应字段填入用户名和密码即可。

#### 使用

- 自动：`crontab cron`。

- 手动：*sh => `./login-bot.sh` 或者 php => `php -f login-bot.php`。

#### NOTICE

- PHP Version: 5.5+，并已安装 LIBCURL 扩展。

- 确保相应文件存在且权限**合理**。为了安全，建议以 root 用户身份使用，且所有文件权限为 0600（可执行文件除外）

- cron 任务所使用的环境变量和路径正确。

#### LICENSE
MIT.
