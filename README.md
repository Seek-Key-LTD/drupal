# Drupal + Redis 部署

## 架构说明

- **基础镜像**: `drupal:latest`（官方）
- **自定义镜像**: `drupal-redis:latest`（预装 PHP Redis 扩展）
- **Redis 模块**: `redis-8.x-1.11`（位于 `/var/www/html/modules/redis`）
- **缓存配置**: `settings.php` 中配置 Redis 为默认缓存后端

## 持久化说明

| 内容 | 持久化方式 |
|------|-----------|
| PHP Redis 扩展 | `podman commit` 到自定义镜像 |
| Redis 模块 | volume 挂载 `modules/` |
| settings.php | volume 挂载 `sites/` |

## 日常维护

### 更新 Drupal 官方镜像 + 重建自定义镜像

```bash
cd /opt/drupal

# 1. 拉取最新官方镜像
sudo podman pull drupal:latest

# 2. 用 Dockerfile 重建自定义镜像（加 --dns 解决构建时 DNS 问题）
sudo podman build --dns 8.8.8.8 -t drupal-redis:latest .

# 3. 重建容器
sudo podman-compose down
sudo podman-compose up -d

# 4. 下载最新兼容的 Redis 模块（版本号根据 Drupal 版本调整）
sudo podman exec drupal bash -c 'rm -rf /var/www/html/modules/redis && cd /var/www/html/modules && curl -sL https://ftp.drupal.org/files/projects/redis-8.x-1.11.tar.gz | tar xzf -'

# 5. 验证
curl -s -o /dev/null -w "%{http_code}" https://drupal.seekkey.eu.org
```

### 只更新 Redis 模块版本

```bash
sudo podman exec drupal bash -c 'rm -rf /var/www/html/modules/redis && cd /var/www/html/modules && curl -sL https://ftp.drupal.org/files/projects/redis-8.x-1.11.tar.gz | tar xzf -'
```

### 查看 Redis 模块兼容版本

访问 https://www.drupal.org/project/redis/releases 查看支持的 Drupal 版本。

## 文件说明

- `Dockerfile` - 构建带 PHP Redis 扩展的自定义镜像
- `docker-compose.yml` - 容器编排配置
- `modules/` - Drupal 模块（持久化）
- `profiles/` - Drupal 配置安装文件（持久化）
- `sites/` - Drupal 站点配置（持久化）
- `themes/` - Drupal 主题（持久化）
