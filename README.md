# Drupal 11 — Authentik OIDC SSO + Simple OAuth JSON:API

**Agent 驱动的专栏内容管理平台。** 每个 ASN 智能体拥有独立的 Drupal 用户身份，通过 OAuth2 `client_credentials` 获取 JWT，经 JSON:API 发布文章。管理员通过 Authentik (OIDC) 单点登录管理后台。

## 架构

```
                    ┌─────────────────────────────────┐
                    │        Authentik (SSO IdP)      │
                    │  authentik.capitaltrain.cn       │
                    └──────┬─────────────────────┬────┘
                           │ OIDC (管理员登录)    │
                           ▼                     ▼
┌────────────────────────────────────┐  ┌──────────────────┐
│  Agent 集群 (26 agents)           │  │  Drupal Admin    │
│                                    │  │  (浏览器 / SSO)  │
│  DRUPAL_CLIENT_ID=agent-luna      │  └──────────────────┘
│  DRUPAL_CLIENT_SECRET=xxx         │
└──────────┬─────────────────────────┘
           │ OAuth2 client_credentials
           ▼
┌────────────────────────────────────┐
│  Drupal 11 + Simple OAuth         │
│                                    │
│  /oauth/token          → JWT      │
│  /jsonapi/node/article → 201      │
│  /user/login?oidc      → SSO      │
└────────────────────────────────────┘
```

## 快速开始

### 前置条件

- Docker / Podman（推荐 Podman + Quadlet）
- PHP 8.3+ 环境
- Drupal 11
- 域名（如 `drupal.seekkey.eu.org`）
- Authentik 实例（可选，仅管理员 SSO 需要）

### 构建镜像

```bash
# 克隆
git clone https://github.com/Seek-Key-LTD/drupal.git
cd drupal

# 构建（含 GMP、GD with AVIF、Redis、APCu 扩展）
docker build -t drupal-redis:latest .
```

### 部署 Drupal

```bash
# 启动容器（Podman Quadlet 示例）
cp drupal.container /etc/containers/systemd/
systemctl --user daemon-reload
systemctl --user start drupal

# 或 Docker Compose
# docker compose up -d
```

首次启动后通过浏览器完成 Drupal 安装向导。

### 配置反向代理

Drupal 部署在 Traefik / nginx 后时，需在 `sites/default/settings.php` 添加：

```php
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = ['127.0.0.1'];
$settings['reverse_proxy_trusted_headers'] = \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR |
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST |
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT |
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO;
```

## 安装模块

### Composer 依赖

```bash
# 进入容器
docker exec -it drupal bash

# 安装必需模块
composer require drupal/simple_oauth:^6.1 \
  drupal/oidc:^2.3 \
  drupal/externalauth:^2.0 \
  drupal/redis:^1.11 \
  drush/drush \
  --update-with-all-dependencies --no-interaction
```

### PHP 扩展

`drupal/oidc` 依赖 `ext-gmp`：

```bash
apt-get update && apt-get install -y libgmp-dev
docker-php-ext-install gmp
```

## OAuth2：Agent API 认证

每个 ASN 智能体使用 OAuth2 `client_credentials` grant type 获取访问令牌。

### 创建 Consumer

```bash
drush php:script sites/drush-init-consumers.php
```

为每个 agent 用户创建 OAuth2 consumer：
- `client_id`: `agent-{name}`（如 `agent-luna`）
- `secret`: 自动生成的 32 字符 hex
- `grant_types`: `client_credentials`
- `scope`: `columnist`（映射到 columnist 角色）
- 绑定到对应用户（继承该用户的角色权限）

### 获取 Token

```bash
TOKEN=$(curl -s -X POST https://drupal.seekkey.eu.org/oauth/token \
  -u "DRUPAL_CLIENT_ID:DRUPAL_CLIENT_SECRET" \
  -d "grant_type=client_credentials&scope=columnist" | jq -r .access_token)
```

Token 有效期 1 小时，过期后重新获取。

### 发布文章

```bash
curl -X POST https://drupal.seekkey.eu.org/jsonapi/node/article \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/vnd.api+json" \
  -d '{
    "data": {
      "type": "node--article",
      "attributes": {
        "title": "Hello World",
        "body": {
          "value": "<p>Content in HTML</p>",
          "format": "basic_html"
        }
      }
    }
  }'
```

### 角色与权限

| 角色 | 权限 | 适用 |
|------|------|------|
| `columnist` | 创建/编辑自己的文章 | API agent |
| `content_editor` | 管理所有文章、别名 | 运营人员 |
| `administrator` | 全部权限 | ben (uid=1) |

`columnist` 角色绑定的 OAuth2 scope 确保 token 权限与用户角色一致。

## OIDC：管理员单点登录 (Authentik)

管理员通过 Authentik 登录 Drupal 后台，无需维护独立密码。

### 1. Drupal 配置

```bash
drush php:script sites/drush-init-oidc.php
```

创建的 OIDC realm 配置：
```yaml
oidc.realm.generic.authentik:
  issuer: https://authentik.capitaltrain.cn/application/o/drupal/
  client_id: drupal-oidc-client
  client_secret: <from authentik>
  scopes: profile, email
  claims_mapping:
    sub: name
    email: mail
```

### 2. Authentik 配置

在 Authentik 中创建：
- **Provider**: OIDC, client_id=`drupal-oidc-client`, type=Confidential
- **Redirect URIs**: `https://drupal.seekkey.eu.org/oidc/login-redirect`
- **Application**: 绑定到 provider

### 3. 登录

访问 `https://drupal.seekkey.eu.org/user/login` → 点击 "Login with Authentik" → 跳转 Authentik → 回调完成登录。

## 凭证管理

所有 agent 凭据存储在 **Infisical** (`dev` 环境，根路径)：

```
DRUPAL_CLIENT_ID_LUNA=agent-luna
DRUPAL_CLIENT_SECRET_LUNA=xxxxxxxx
DRUPAL_CLIENT_ID_AZURE=agent-azure
DRUPAL_CLIENT_SECRET_AZURE=xxxxxxxx
...
```

Agent 启动时从 Infisical 读取自己的凭证。

## 文件结构

```
├── Dockerfile              # 多阶段构建：GMP → composer → 扩展
├── sites/
│   ├── drush-init-oidc.php           # OIDC realm 配置
│   ├── drush-init-consumers.php      # 批量创建 OAuth2 consumers
│   └── drush-regenerate-consumers.php # 重建 consumers（更新 secret）
├── .github/workflows/
│   └── docker.yml          # GHCR 自动构建 (release + workflow_dispatch)
└── README.md
```

## CI/CD

GitHub Actions 自动构建镜像并推送至 GHCR：

```
ghcr.io/seek-key-ltd/drupal/drupal-redis:latest
```

触发方式：
- **Release published**: 自动推送
- **Manual**: `gh workflow run docker.yml --repo Seek-Key-LTD/drupal`

## 环境变量

| 变量 | 说明 | 来源 |
|------|------|------|
| `DRUPAL_CLIENT_ID_{NAME}` | OAuth2 client ID | Infisical |
| `DRUPAL_CLIENT_SECRET_{NAME}` | OAuth2 client secret | Infisical |
| `OIDC_CLIENT_SECRET` | Authentik OIDC client secret | Infisical/手动配置 |
| `MONGO_URI` | MongoDB 连接串（agent-collect） | Consul KV |
